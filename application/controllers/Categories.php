<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Categories extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
        $this->load->model('Notification_model');
        $this->load->helper('staff_scope');
    }

    private function actor()
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302); return NULL; }
        $user=$this->Staff_model->by_id($id);
        if (!$user || $user['status']!=='active' || !in_array($user['role_code'],array('superadmin','admin_dinas','admin_kecamatan','umkm'),TRUE))
        { show_error('Akses tidak diizinkan.',403); return NULL; }
        return $user;
    }

    private function is_admin($user)
    {
        return in_array($user['role_code'],array('superadmin','admin_dinas','admin_kecamatan'),TRUE);
    }

    private function owns_store($user,$store_id)
    {
        return (bool)$this->db->query("SELECT 1 FROM tko_store_managers m JOIN tko_stores s ON s.id=m.store_id
            WHERE m.user_id=? AND m.store_id=? AND m.status='active'
              AND (m.access_role='editor' OR NOT EXISTS (
                SELECT 1 FROM iam_imported_business_accounts ia JOIN umk_businesses imported ON imported.id=ia.business_id
                WHERE ia.user_id=m.user_id AND COALESCE(imported.canonical_business_id,imported.id)=s.business_id
              ) OR EXISTS (
                SELECT 1 FROM umk_business_claims claim WHERE claim.user_id=m.user_id AND claim.business_id=s.business_id AND claim.status='approved'
              )) LIMIT 1",array($user['id'],(int)$store_id))->row();
    }

    public function index()
    {
        $user=$this->actor(); if (!$user) return;
        $admin=$this->is_admin($user);
        $central=in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE);
        $scope=staff_scope_districts($this->db,$user);
        $district_sql=$user['role_code']==='admin_kecamatan'
            ? ($scope?' AND b.district_id IN ('.implode(',',array_map('intval',$scope)).') AND b.is_demo=0 AND b.canonical_business_id IS NULL':' AND 1=0') : '';
        $stores=$admin
            ? $this->db->query('SELECT s.id,s.display_name FROM tko_stores s JOIN umk_businesses b ON b.id=s.business_id WHERE 1=1'.$district_sql.' ORDER BY s.display_name')->result_array()
            : $this->db->query("SELECT s.id,s.display_name FROM tko_stores s JOIN tko_store_managers m ON m.store_id=s.id WHERE m.user_id=? AND m.status='active'
                AND (m.access_role='editor' OR NOT EXISTS (
                    SELECT 1 FROM iam_imported_business_accounts ia JOIN umk_businesses imported ON imported.id=ia.business_id
                    WHERE ia.user_id=m.user_id AND COALESCE(imported.canonical_business_id,imported.id)=s.business_id
                ) OR EXISTS (SELECT 1 FROM umk_business_claims claim WHERE claim.user_id=m.user_id AND claim.business_id=s.business_id AND claim.status='approved'))
                ORDER BY s.display_name",array($user['id']))->result_array();
        $store_id=(int)$this->input->get('store',TRUE);
        if (!$admin && !$store_id && $stores) $store_id=(int)$stores[0]['id'];
        if ($store_id && !$admin && !$this->owns_store($user,$store_id)) { show_error('Akses toko tidak diizinkan.',403); return; }
        $status=(string)$this->input->get('status',TRUE);
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        if ($status==='' ) $status=$admin?'pending':'all';
        if (!in_array($status,array('all','pending','approved','rejected'),TRUE)) { show_404(); return; }
        $where=' WHERE 1=1';$params=array();
        if ($admin)
        {
            if ($store_id) { $where.=' AND r.store_id=?';$params[]=$store_id; }
            if ($user['role_code']==='admin_kecamatan')
            {
                if ($store_id && !staff_scope_store($this->db,$user,$store_id)) { show_404();return; }
                $where.=$district_sql;
            }
        }
        else
        {
            $where.=" AND EXISTS (SELECT 1 FROM tko_store_managers m WHERE m.store_id=r.store_id AND m.user_id=? AND m.status='active')";
            $params[]=$user['id'];
            if ($store_id) { $where.=' AND r.store_id=?';$params[]=$store_id; }
        }
        if ($status!=='all') { $where.=' AND r.status=?';$params[]=$status; }
        if ($search!=='') { $where.=' AND (s.display_name LIKE ? OR c.name LIKE ? OR u.full_name LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        $joins=' FROM tko_category_requests r JOIN tko_stores s ON s.id=r.store_id JOIN umk_businesses b ON b.id=s.business_id JOIN ref_categories c ON c.id=r.category_id JOIN iam_users u ON u.id=r.requested_by';
        $total=(int)$this->db->query('SELECT COUNT(*) n'.$joins.$where,$params)->row('n');
        $page=max(1,(int)$this->input->get('page',TRUE));$pages=max(1,(int)ceil($total/$per_page));$page=min($page,$pages);
        $requests=$this->db->query('SELECT r.id,r.store_id,r.category_id,r.request_type,r.reason,r.status,r.requested_at,r.decided_at,r.decision_note,
            s.display_name AS store_name,c.name AS category_name,u.full_name AS requester_name,
            (SELECT COUNT(*) FROM prd_product_categories pc JOIN prd_products p ON p.id=pc.product_id JOIN ref_categories rc ON rc.id=pc.category_id
             WHERE p.store_id=r.store_id AND (pc.category_id=r.category_id OR rc.parent_id=r.category_id)) AS attached_products
            '.$joins.$where.' ORDER BY r.requested_at DESC,r.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $count_condition=$user['role_code']==='admin_kecamatan'
            ? ($scope?" AND bs.district_id IN (".implode(',',array_map('intval',$scope)).") AND bs.is_demo=0 AND bs.canonical_business_id IS NULL":" AND 1=0") : '';
        $catalogue=$this->db->query("SELECT c.id,c.name,c.slug,c.parent_id,p.name parent_name,
            COUNT(DISTINCT IF(sc.status='approved'".$count_condition.",sc.store_id,NULL)) store_count
            FROM ref_categories c LEFT JOIN ref_categories p ON p.id=c.parent_id
            LEFT JOIN ref_categories leaf ON leaf.id=c.id OR leaf.parent_id=c.id
            LEFT JOIN tko_store_categories sc ON sc.category_id=leaf.id
            LEFT JOIN tko_stores ss ON ss.id=sc.store_id LEFT JOIN umk_businesses bs ON bs.id=ss.business_id
            WHERE c.category_type='store' AND c.is_active=1
            GROUP BY c.id ORDER BY p.sort_order,p.name,c.sort_order,c.name")->result_array();
        $approved=array();$add_options=array();
        if ($store_id)
        {
            $approved=$this->db->query("SELECT c.id,c.name FROM tko_store_categories sc JOIN ref_categories c ON c.id=sc.category_id WHERE sc.store_id=? AND sc.status='approved' AND NOT EXISTS (SELECT 1 FROM ref_categories child WHERE child.parent_id=c.id) ORDER BY c.name",array($store_id))->result_array();
            $add_options=$this->db->query("SELECT c.id,c.name,p.name AS parent_name FROM ref_categories c LEFT JOIN ref_categories p ON p.id=c.parent_id
                WHERE c.category_type='store' AND c.is_active=1
                AND NOT EXISTS (SELECT 1 FROM ref_categories child WHERE child.parent_id=c.id)
                AND NOT EXISTS (SELECT 1 FROM tko_store_categories sc WHERE sc.store_id=? AND sc.category_id=c.id AND sc.status='approved')
                ORDER BY p.name,c.name",array($store_id))->result_array();
        }
        $error=(string)$this->session->flashdata('category_error');
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/categories',compact('user','admin','central','stores','store_id','status','search','per_page','requests','approved','add_options','catalogue','page','pages','total','error'));
    }

    public function assign()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404();return; }
        $user=$this->actor();if (!$user) return;
        if (!$this->is_admin($user)) { show_error('Akses tidak diizinkan.',403);return; }
        $store_id=(int)$this->input->post('store_id',TRUE);
        $started=FALSE;
        try
        {
            $category_id=(int)$this->input->post('category_id',TRUE);
            $action=(string)$this->input->post('action',TRUE);
            if (!in_array($action,array('add','remove'),TRUE)) throw new InvalidArgumentException('Aksi tidak valid.');
            $category=$this->db->query("SELECT id,name FROM ref_categories WHERE id=? AND category_type='store' AND is_active=1
                AND NOT EXISTS (SELECT 1 FROM ref_categories child WHERE child.parent_id=ref_categories.id) LIMIT 1",array($category_id))->row_array();
            if (!$category) throw new InvalidArgumentException('Kategori tidak tersedia.');
            $this->db->trans_begin();$started=TRUE;
            $store=$this->db->query('SELECT id FROM tko_stores WHERE id=? FOR UPDATE',array($store_id))->row_array();
            if (!$store) throw new InvalidArgumentException('Toko tidak ditemukan.');
            if (!staff_scope_store($this->db,$user,$store_id)) throw new InvalidArgumentException('Toko di luar wilayah tugas Anda.');
            if ($action==='remove')
            {
                $used=(int)$this->db->query('SELECT COUNT(*) n FROM prd_product_categories pc JOIN prd_products p ON p.id=pc.product_id WHERE p.store_id=? AND pc.category_id=?',array($store_id,$category_id))->row('n');
                if ($used) throw new InvalidArgumentException('Masih ada '.$used.' produk dalam kategori ini. Pindahkan kategorinya terlebih dahulu.');
                $remaining=(int)$this->db->query("SELECT COUNT(*) n FROM tko_store_categories WHERE store_id=? AND category_id<>? AND status='approved'",array($store_id,$category_id))->row('n');
                if (!$remaining) throw new InvalidArgumentException('Toko harus memiliki minimal satu kategori.');
                $this->db->query('DELETE FROM tko_store_categories WHERE store_id=? AND category_id=?',array($store_id,$category_id));
            }
            else
                $this->db->query("INSERT INTO tko_store_categories (store_id,category_id,tagged_by,status) VALUES (?,?,?,'approved')
                    ON DUPLICATE KEY UPDATE tagged_by=VALUES(tagged_by),status='approved',tagged_at=NOW()",array($store_id,$category_id,$user['id']));
            $this->Staff_model->audit($user['id'],'store.category_'.$action,'tko_stores',$store_id,$category['name']);
            if ($this->db->trans_status()===FALSE) throw new RuntimeException('Kategori toko gagal disimpan.');
            $this->db->trans_commit();$started=FALSE;
            redirect('pengelola/kategori?store='.$store_id.'&tersimpan=1','location',303);return;
        }
        catch(Exception $e)
        {
            if ($started) $this->db->trans_rollback();
            $this->session->set_flashdata('category_error',$e->getMessage());
            $back=staff_scope_store($this->db,$user,$store_id)?'pengelola/kategori?store='.$store_id:'pengelola/kategori';
            redirect($back,'location',303);
        }
    }

    public function submit()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        if ($user['role_code']!=='umkm') { show_error('Pengajuan hanya untuk pengelola toko.',403); return; }
        $store_id=(int)$this->input->post('store_id',TRUE);
        if (!$this->owns_store($user,$store_id)) { show_error('Akses toko tidak diizinkan.',403); return; }
        $started=FALSE;
        try
        {
            $category_id=(int)$this->input->post('category_id',TRUE);
            $type=(string)$this->input->post('request_type',TRUE);
            $reason=trim((string)$this->input->post('reason',TRUE));
            if (!in_array($type,array('add','remove'),TRUE) || mb_strlen($reason,'UTF-8')<10 || mb_strlen($reason,'UTF-8')>500)
                throw new InvalidArgumentException('Isi alasan 10 sampai 500 karakter.');
            $category=$this->db->query("SELECT c.id,c.name FROM ref_categories c WHERE c.id=? AND c.category_type='store' AND c.is_active=1
                AND NOT EXISTS (SELECT 1 FROM ref_categories child WHERE child.parent_id=c.id) LIMIT 1",array($category_id))->row_array();
            if (!$category) throw new InvalidArgumentException('Pilih kategori yang tersedia.');
            $approved=(bool)$this->db->query("SELECT 1 FROM tko_store_categories WHERE store_id=? AND category_id=? AND status='approved' LIMIT 1",array($store_id,$category_id))->row();
            if (($type==='add' && $approved) || ($type==='remove' && !$approved))
                throw new InvalidArgumentException('Status kategori sudah berubah. Muat ulang halaman.');
            $pending=(bool)$this->db->query("SELECT 1 FROM tko_category_requests WHERE store_id=? AND category_id=? AND status='pending' LIMIT 1",array($store_id,$category_id))->row();
            if ($pending) throw new InvalidArgumentException('Kategori ini sudah mempunyai pengajuan yang menunggu keputusan.');
            $store=$this->db->query('SELECT display_name FROM tko_stores WHERE id=?',array($store_id))->row_array();
            $this->db->trans_begin();
            $started=TRUE;
            $this->db->query('INSERT INTO tko_category_requests (store_id,category_id,request_type,reason,requested_by) VALUES (?,?,?,?,?)',array($store_id,$category_id,$type,$reason,$user['id']));
            $id=(int)$this->db->insert_id();
            $this->Staff_model->audit($user['id'],'store.category_request','tko_category_requests',$id,$type.' '.$category['name']);
            $this->Notification_model->publish('category_requested','category-request:'.$id,
                'Pengajuan kategori toko', $store['display_name'].' mengajukan '.($type==='add'?'penambahan':'penghapusan').' kategori '.$category['name'].'.',
                '/pengelola/kategori',$this->Notification_model->admin_ids());
            if ($this->db->trans_status()===FALSE) { throw new RuntimeException('Pengajuan gagal disimpan.'); }
            $this->db->trans_commit();$started=FALSE;
            redirect('pengelola/kategori?store='.$store_id.'&terkirim=1','location',303);return;
        }
        catch(Exception $e)
        {
            if ($started) $this->db->trans_rollback();
            $this->session->set_flashdata('category_error',$e->getMessage());
            redirect('pengelola/kategori?store='.$store_id,'location',303);
        }
    }

    public function decide($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        if (!in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE)) { show_error('Akses keputusan tidak diizinkan.',403); return; }
        $started=FALSE;
        try
        {
            $decision=(string)$this->input->post('decision',TRUE);
            $note=trim((string)$this->input->post('decision_note',TRUE));
            if (!in_array($decision,array('approved','rejected'),TRUE) || mb_strlen($note,'UTF-8')>500 || ($decision==='rejected' && mb_strlen($note,'UTF-8')<10))
                throw new InvalidArgumentException('Periksa keputusan dan alasan penolakan.');
            $this->db->trans_begin();
            $started=TRUE;
            $request=$this->db->query('SELECT r.*,s.display_name AS store_name,c.name AS category_name FROM tko_category_requests r
                JOIN tko_stores s ON s.id=r.store_id JOIN ref_categories c ON c.id=r.category_id WHERE r.id=? FOR UPDATE',array((int)$id))->row_array();
            if (!$request || $request['status']!=='pending') throw new InvalidArgumentException('Pengajuan tidak ditemukan atau sudah diputuskan.');
            if ($decision==='approved')
            {
                if ($request['request_type']==='add')
                {
                    $this->db->query("INSERT INTO tko_store_categories (store_id,category_id,tagged_by,status) VALUES (?,?,?,'approved')
                        ON DUPLICATE KEY UPDATE tagged_by=VALUES(tagged_by),status='approved',tagged_at=NOW()",array($request['store_id'],$request['category_id'],$user['id']));
                }
                else
                {
                    $used=(int)$this->db->query('SELECT COUNT(*) n FROM prd_product_categories pc JOIN prd_products p ON p.id=pc.product_id JOIN ref_categories c ON c.id=pc.category_id
                        WHERE p.store_id=? AND (pc.category_id=? OR c.parent_id=?)',array($request['store_id'],$request['category_id'],$request['category_id']))->row('n');
                    if ($used) throw new InvalidArgumentException('Masih ada '.$used.' produk dalam kategori ini. Pindahkan kategori produk terlebih dahulu.');
                    $remaining=(int)$this->db->query("SELECT COUNT(*) n FROM tko_store_categories WHERE store_id=? AND category_id<>? AND status='approved'",array($request['store_id'],$request['category_id']))->row('n');
                    if (!$remaining) throw new InvalidArgumentException('Toko harus memiliki minimal satu kategori.');
                    $this->db->query('DELETE FROM tko_store_categories WHERE store_id=? AND category_id=?',array($request['store_id'],$request['category_id']));
                }
            }
            $this->db->query('UPDATE tko_category_requests SET status=?,decided_by=?,decided_at=NOW(),decision_note=? WHERE id=?',array($decision,$user['id'],$note?:NULL,$request['id']));
            $this->Staff_model->audit($user['id'],'store.category_'.$decision,'tko_category_requests',$request['id'],$note?:$request['request_type'].' '.$request['category_name']);
            $recipients=array_merge(array((int)$request['requested_by']),$this->Notification_model->store_manager_ids($request['store_id']));
            $this->Notification_model->publish('category_'.$decision,'category-decision:'.$request['id'],
                $decision==='approved'?'Kategori toko disetujui':'Kategori toko ditolak',
                $request['category_name'].' untuk '.$request['store_name'].' '.($decision==='approved'?'disetujui.':'ditolak.').($note?' '.$note:''),
                '/pengelola/kategori',$recipients);
            if ($this->db->trans_status()===FALSE) { throw new RuntimeException('Keputusan gagal disimpan.'); }
            $this->db->trans_commit();$started=FALSE;
            redirect('pengelola/kategori?diputus=1','location',303);return;
        }
        catch(Exception $e)
        {
            if ($started) $this->db->trans_rollback();
            $this->session->set_flashdata('category_error',$e->getMessage());
            redirect('pengelola/kategori','location',303);
        }
    }
}
