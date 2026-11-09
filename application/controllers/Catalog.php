<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Catalog extends CI_Controller
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

    private function can_manage($user,$store_id)
    {
        if (in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE)) return TRUE;
        if ($user['role_code']==='admin_kecamatan') return (bool)staff_scope_store($this->db,$user,$store_id);
        return (bool)$this->db->query("SELECT 1 FROM tko_store_managers m
            JOIN tko_stores s ON s.id=m.store_id
            WHERE m.user_id=? AND m.store_id=? AND m.status='active'
              AND ".$this->merchant_access_sql()." LIMIT 1",array($user['id'],$store_id))->row();
    }

    private function merchant_access_sql()
    {
        return "(m.access_role='editor' OR NOT EXISTS (
            SELECT 1 FROM iam_imported_business_accounts ia
            JOIN umk_businesses imported ON imported.id=ia.business_id
            WHERE ia.user_id=m.user_id AND COALESCE(imported.canonical_business_id,imported.id)=s.business_id
        ) OR EXISTS (
            SELECT 1 FROM umk_business_claims claim
            WHERE claim.user_id=m.user_id AND claim.business_id=s.business_id AND claim.status='approved'
        ))";
    }

    private function district_stores($user)
    {
        $scope=staff_scope_districts($this->db,$user);
        if (!$scope) return array();
        return $this->db->query("SELECT s.id,s.display_name FROM tko_stores s JOIN umk_businesses b ON b.id=s.business_id
            WHERE s.status IN ('draft','active') AND b.is_demo=0 AND b.canonical_business_id IS NULL
              AND b.district_id IN (".implode(',',array_map('intval',$scope)).") ORDER BY s.display_name")->result_array();
    }

    private function district_filter($user,$business_alias)
    {
        if ($user['role_code']!=='admin_kecamatan') return '';
        $scope=staff_scope_districts($this->db,$user);
        return $scope?' AND '.$business_alias.'.is_demo=0 AND '.$business_alias.'.canonical_business_id IS NULL AND '
            .$business_alias.'.district_id IN ('.implode(',',array_map('intval',$scope)).')':' AND 1=0';
    }

    private function product($id)
    {
        return $this->db->query('SELECT p.*,s.display_name AS store_name,s.slug AS store_slug FROM prd_products p JOIN tko_stores s ON s.id=p.store_id WHERE p.id=? LIMIT 1',array((int)$id))->row_array();
    }

    public function index()
    {
        $user=$this->actor(); if (!$user) return;
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $store_id=(int)$this->input->get('store',TRUE);
        $status=(string)$this->input->get('status',TRUE);
        if (!in_array($status,array('','draft','pending','active','inactive'),TRUE)) $status='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $page=max(1,(int)$this->input->get('page',TRUE));
        $where=' WHERE 1=1'; $args=array();
        if ($user['role_code']==='admin_kecamatan')
            $where.=' AND EXISTS (SELECT 1 FROM umk_businesses b WHERE b.id=s.business_id'.$this->district_filter($user,'b').')';
        if ($user['role_code']==='umkm') { $where.=" AND EXISTS (SELECT 1 FROM tko_store_managers m WHERE m.store_id=s.id AND m.user_id=? AND m.status='active' AND ".$this->merchant_access_sql().")";$args[]=$user['id']; }
        if ($search!=='') { $where.=' AND (p.name LIKE ? OR s.display_name LIKE ?)'; $args[]='%'.$search.'%';$args[]='%'.$search.'%'; }
        if ($store_id) { $where.=' AND s.id=?'; $args[]=$store_id; }
        if ($status!=='') { $where.=' AND p.status=?'; $args[]=$status; }
        $total=(int)$this->db->query('SELECT COUNT(*) n FROM prd_products p JOIN tko_stores s ON s.id=p.store_id'.$where,$args)->row('n');
        $pages=max(1,(int)ceil($total/$per_page)); $page=min($page,$pages);
        $rows=$this->db->query('SELECT p.id,p.name,p.status,p.price_min,p.price_max,s.display_name AS store_name,s.id AS store_id,(SELECT COUNT(*) FROM prd_variants v WHERE v.product_id=p.id AND v.is_active=1) variant_count,(SELECT COUNT(*) FROM prd_media m WHERE m.product_id=p.id) media_count FROM prd_products p JOIN tko_stores s ON s.id=p.store_id'.$where.' ORDER BY p.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$args)->result_array();
        $stores=$user['role_code']==='umkm'
            ? $this->db->query("SELECT s.id,s.display_name FROM tko_stores s JOIN tko_store_managers m ON m.store_id=s.id WHERE m.user_id=? AND m.status='active' AND ".$this->merchant_access_sql()." ORDER BY s.display_name",array($user['id']))->result_array()
            : ($user['role_code']==='admin_kecamatan'?$this->district_stores($user)
                :$this->db->query("SELECT id,display_name FROM tko_stores WHERE status IN ('draft','active') ORDER BY display_name")->result_array());
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/catalog',compact('user','rows','stores','search','store_id','status','page','pages','total','per_page'));
    }

    public function access()
    {
        $user=$this->actor(); if (!$user) return;
        if (!in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE)) { show_error('Akses tidak diizinkan.',403); return; }
        $error='';
        if ($this->input->method(TRUE)==='POST')
        {
            $started=FALSE;
            try
            {
                $store_id=(int)$this->input->post('store_id',TRUE);
                $manager_id=(int)$this->input->post('user_id',TRUE);
                $action=(string)$this->input->post('action',TRUE);
                if (!in_array($action,array('assign','revoke'),TRUE)) throw new InvalidArgumentException('Aksi tidak valid.');
                $store=$this->db->query('SELECT id,display_name FROM tko_stores WHERE id=? LIMIT 1',array($store_id))->row();
                $manager=$this->db->query("SELECT u.id FROM iam_users u JOIN iam_user_roles ur ON ur.user_id=u.id JOIN iam_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.code='umkm' LIMIT 1",array($manager_id))->row();
                if (!$store || !$manager) throw new InvalidArgumentException('Toko atau akun pelaku UMKM tidak valid.');
                $this->db->trans_begin();
                $started=TRUE;
                if ($action==='assign') $this->db->query("INSERT INTO tko_store_managers (store_id,user_id,access_role,status,assigned_by) VALUES (?,?,'owner','active',?) ON DUPLICATE KEY UPDATE status='active',assigned_by=VALUES(assigned_by),assigned_at=NOW()",array($store_id,$manager_id,$user['id']));
                else $this->db->query("UPDATE tko_store_managers SET status='revoked' WHERE store_id=? AND user_id=?",array($store_id,$manager_id));
                $this->Staff_model->audit($user['id'],'catalog.access_'.$action,'tko_store_managers',$store_id,'Akun #'.$manager_id);
                $this->Notification_model->publish('store_access_'.$action,'store-access:'.$store_id.':'.$manager_id.':'.bin2hex(random_bytes(4)),
                    $action==='assign'?'Akses toko diberikan':'Akses toko dicabut',
                    'Akses pengelolaan '.$store->display_name.' '.($action==='assign'?'diberikan.':'dicabut.'),
                    '/pengelola/toko',array($manager_id));
                if ($this->db->trans_status()===FALSE) throw new RuntimeException('Hak akses gagal disimpan.');
                $this->db->trans_commit();$started=FALSE;
                redirect('pengelola/akses-toko?tersimpan=1','location',303);return;
            }
            catch (Exception $e) { if ($started) $this->db->trans_rollback(); $error=$e->getMessage(); }
        }
        $stores=$this->db->query("SELECT id,display_name FROM tko_stores WHERE status='active' ORDER BY display_name")->result_array();
        $users=$this->db->query("SELECT u.id,u.full_name,u.email FROM iam_users u JOIN iam_user_roles ur ON ur.user_id=u.id JOIN iam_roles r ON r.id=ur.role_id WHERE r.code='umkm' AND u.status='active' ORDER BY u.full_name")->result_array();
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $status=(string)$this->input->get('status',TRUE);
        if (!in_array($status,array('','active','revoked'),TRUE)) $status='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $where=' WHERE 1=1';$params=array();
        if ($search!=='') { $where.=' AND (s.display_name LIKE ? OR u.full_name LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        if ($status!=='') { $where.=' AND m.status=?';$params[]=$status; }
        $from=' FROM tko_store_managers m JOIN tko_stores s ON s.id=m.store_id JOIN iam_users u ON u.id=m.user_id';
        $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));$page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $assignments=$this->db->query('SELECT m.store_id,m.user_id,m.status,s.display_name,u.full_name'.$from.$where.' ORDER BY s.display_name,u.full_name LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/store_access',compact('user','stores','users','assignments','error','search','status','per_page','total','page','pages'));
    }

    public function create()
    {
        $user=$this->actor(); if (!$user) return;
        $stores=$user['role_code']==='umkm'
            ? $this->db->query("SELECT s.id,s.display_name FROM tko_stores s JOIN tko_store_managers m ON m.store_id=s.id WHERE m.user_id=? AND m.status='active' AND ".$this->merchant_access_sql()." ORDER BY s.display_name",array($user['id']))->result_array()
            : ($user['role_code']==='admin_kecamatan'?$this->district_stores($user)
                :$this->db->query("SELECT id,display_name FROM tko_stores WHERE status IN ('draft','active') ORDER BY display_name")->result_array());
        $store_id=(int)$this->input->get_post('store_id',TRUE);
        if (!$store_id && count($stores)===1) $store_id=(int)$stores[0]['id'];
        if ($store_id && !$this->can_manage($user,$store_id)) { show_error('Akses toko tidak diizinkan.',403); return; }
        $categories=$store_id ? $this->db->query("SELECT c.id,c.name FROM ref_categories c JOIN tko_store_categories sc ON sc.store_id=? AND sc.category_id=c.id AND sc.status='approved' WHERE c.is_active=1 AND NOT EXISTS (SELECT 1 FROM ref_categories child WHERE child.parent_id=c.id) ORDER BY c.name",array($store_id))->result_array() : array();
        $error='';
        if ($this->input->method(TRUE)==='POST')
        {
            $started=FALSE;
            try
            {
                $name=trim((string)$this->input->post('name',TRUE));
                $description=trim((string)$this->input->post('description',TRUE));
                $category_id=(int)$this->input->post('category_id',TRUE);
                $price=trim((string)$this->input->post('base_price',TRUE));
                if (!$store_id || !$categories || !in_array($category_id,array_map('intval',array_column($categories,'id')),TRUE) || mb_strlen($name)<2 || mb_strlen($name)>190 || mb_strlen($description)>10000 || ($price!=='' && (!is_numeric($price)||$price<0||$price>999999999999))) throw new InvalidArgumentException('Periksa toko, kategori, nama, deskripsi, dan harga.');
                $store_name=$this->db->query('SELECT display_name FROM tko_stores WHERE id=?',array($store_id))->row('display_name');
                $this->db->trans_begin();
                $started=TRUE;
                $slug='item-'.bin2hex(random_bytes(8));
                $this->db->query("INSERT INTO prd_products (store_id,slug,name,description,base_price,price_min,price_max,status) VALUES (?,?,?,?,?,?,?,'draft')",array($store_id,$slug,$name,$description,$price===''?NULL:$price,$price===''?NULL:$price,$price===''?NULL:$price));
                $id=(int)$this->db->insert_id();
                $this->db->query('INSERT INTO prd_product_categories (product_id,category_id) VALUES (?,?)',array($id,$category_id));
                if ($this->db->trans_status()===FALSE) throw new RuntimeException('Produk gagal dibuat.');
                $this->Staff_model->audit($user['id'],'catalog.product_create','prd_products',$id);
                if ($user['role_code']==='umkm')
                    $this->Notification_model->publish('product_draft','product:'.$id,'Produk baru dibuat',
                        $name.' dibuat sebagai draf untuk '.$store_name.'.','/pengelola/produk/'.$id,
                        $this->Notification_model->admin_ids());
                if ($this->db->trans_status()===FALSE) throw new RuntimeException('Produk gagal dibuat.');
                $this->db->trans_commit();
                $started=FALSE;
                if (!empty($_FILES['media']['name'][0]))
                {
                    try { $this->upload_media(array('id'=>$id,'name'=>$name),$user); }
                    catch (Exception $e)
                    {
                        $this->session->set_flashdata('catalog_error','Produk tersimpan sebagai draf, tetapi foto belum terunggah: '.$e->getMessage());
                        redirect('pengelola/produk/'.$id,'location',303);return;
                    }
                }
                redirect('pengelola/produk/'.$id,'location',303);return;
            }
            catch (Exception $e) { if ($started) $this->db->trans_rollback(); $error=$e->getMessage(); }
        }
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/product_create',compact('user','stores','store_id','categories','error'));
    }

    public function stores()
    {
        $user=$this->actor(); if (!$user) return;
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $requested_status=$this->input->get('status',TRUE);
        $status=$requested_status===NULL ? 'all' : (string)$requested_status;
        if (!in_array($status,array('all','draft','active','inactive','suspended'),TRUE)) $status='active';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $from=' FROM tko_stores s JOIN umk_businesses b ON b.id=s.business_id';$where=' WHERE 1=1';$params=array();
        $where.=$this->district_filter($user,'b');
        if ($user['role_code']==='umkm') { $where.=" AND EXISTS (SELECT 1 FROM tko_store_managers m WHERE m.store_id=s.id AND m.user_id=? AND m.status='active' AND ".$this->merchant_access_sql().")";$params[]=$user['id']; }
        if ($search!=='') { $where.=' AND (s.display_name LIKE ? OR b.name LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        if ($status!=='all') { $where.=' AND s.status=?';$params[]=$status; }
        $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));$page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $stores=$this->db->query('SELECT s.id,s.slug,s.display_name,s.description,s.status,
            b.id business_id,b.name business_name,b.is_demo,
            (SELECT p.full_name FROM umk_business_owners o JOIN umk_people p ON p.id=o.person_id
             WHERE o.business_id=b.id AND o.is_primary=1 LIMIT 1) owner_name'.$from.$where.
            ' ORDER BY s.display_name LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/stores',compact('user','stores','search','status','per_page','total','page','pages'));
    }

    public function store_profile($id=0)
    {
        $user=$this->actor(); if (!$user) return;
        $store=$this->db->query('SELECT s.id,s.slug,s.display_name,s.description,s.whatsapp_e164,
            s.contact_label,s.status,b.id business_id,b.name business_name,b.is_demo,
            (SELECT p.full_name FROM umk_business_owners o JOIN umk_people p ON p.id=o.person_id
             WHERE o.business_id=b.id AND o.is_primary=1 LIMIT 1) owner_name
            FROM tko_stores s JOIN umk_businesses b ON b.id=s.business_id
            WHERE s.id=? LIMIT 1',array((int)$id))->row_array();
        if (!$store) { show_404(); return; }
        if (!$this->can_manage($user,$store['id'])) { show_error('Akses toko tidak diizinkan.',403); return; }
        $error='';$days=array(1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu');
        if ($this->input->method(TRUE)==='POST')
        {
            try
            {
                $name=trim((string)$this->input->post('display_name',TRUE));
                $description=trim((string)$this->input->post('description',TRUE));
                $contact=trim((string)$this->input->post('contact_label',TRUE));
                $phone=preg_replace('/\D+/','',(string)$this->input->post('whatsapp',TRUE));
                if (strpos($phone,'0')===0) $phone='62'.substr($phone,1);
                if (mb_strlen($name)<2 || mb_strlen($name)>190 || mb_strlen($description)>5000 || mb_strlen($contact)>120 || !preg_match('/^62[0-9]{8,13}$/',$phone)) throw new InvalidArgumentException('Periksa nama toko, deskripsi, dan nomor WhatsApp Indonesia.');
                $hours=array();
                foreach($days as $day=>$label)
                {
                    $open=trim((string)$this->input->post('open_'.$day,TRUE));
                    $close=trim((string)$this->input->post('close_'.$day,TRUE));
                    $closed=(bool)$this->input->post('closed_'.$day,TRUE);
                    if ($closed) { $hours[]=array($day,NULL,NULL,1); continue; }
                    if ($open==='' && $close==='') continue;
                    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$open) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$close) || $open===$close) throw new InvalidArgumentException('Isi jam buka dan tutup yang valid untuk '.$label.'.');
                    $hours[]=array($day,$open,$close,0);
                }
                $this->db->trans_begin();
                $this->db->query('UPDATE tko_stores SET display_name=?,description=?,whatsapp_e164=?,contact_label=? WHERE id=?',array($name,$description,'+'.$phone,$contact,$store['id']));
                $this->db->query('DELETE FROM tko_store_hours WHERE store_id=?',array($store['id']));
                foreach($hours as $row) $this->db->query('INSERT INTO tko_store_hours (store_id,day_of_week,opens_at,closes_at,is_closed) VALUES (?,?,?,?,?)',array($store['id'],$row[0],$row[1],$row[2],$row[3]));
                if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();throw new RuntimeException('Profil toko gagal disimpan.'); }
                $this->Staff_model->audit($user['id'],'store.profile_update','tko_stores',$store['id']);
                $this->db->trans_commit();
                redirect('pengelola/toko/'.$store['id'].'?tersimpan=1','location',303);return;
            }
            catch (Exception $e) { $error=$e->getMessage(); }
        }
        $store=$this->db->query('SELECT s.id,s.slug,s.display_name,s.description,s.whatsapp_e164,
            s.contact_label,s.status,b.id business_id,b.name business_name,b.is_demo,
            (SELECT p.full_name FROM umk_business_owners o JOIN umk_people p ON p.id=o.person_id
             WHERE o.business_id=b.id AND o.is_primary=1 LIMIT 1) owner_name
            FROM tko_stores s JOIN umk_businesses b ON b.id=s.business_id
            WHERE s.id=?',array((int)$id))->row_array();
        $hours_rows=$this->db->query('SELECT day_of_week,opens_at,closes_at,is_closed FROM tko_store_hours WHERE store_id=?',array($store['id']))->result_array();
        $hours=array();foreach($hours_rows as $row)$hours[(int)$row['day_of_week']]=$row;
        $category_rows=$this->db->query("SELECT c.name FROM tko_store_categories sc JOIN ref_categories c ON c.id=sc.category_id WHERE sc.store_id=? AND sc.status='approved' ORDER BY c.name",array($store['id']))->result_array();
        $has_verified_nib=(bool)$this->db->query("SELECT 1 FROM umk_business_licenses WHERE business_id=? AND license_type='NIB' AND status='verified' AND verified_at IS NOT NULL AND verified_by IS NOT NULL LIMIT 1",array($store['business_id']))->row();
        $activation_error=(string)$this->session->flashdata('store_activation_error');
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/store_profile',compact('user','store','hours','days','error','category_rows','has_verified_nib','activation_error'));
    }

    public function edit($id=0)
    {
        $user=$this->actor(); if (!$user) return;
        $product=$this->product($id); if (!$product) { show_404(); return; }
        if (!$this->can_manage($user,$product['store_id'])) { show_error('Akses produk tidak diizinkan.',403); return; }
        $error=(string)$this->session->flashdata('catalog_error');
        if ($this->input->method(TRUE)==='POST')
        {
            try
            {
                $action=(string)$this->input->post('action',TRUE);
                if ($product['status']==='pending' && $action!=='cancel_review')
                    throw new InvalidArgumentException('Produk sedang ditinjau. Batalkan pengajuan sebelum mengubahnya.');
                if (in_array($user['role_code'],array('umkm','admin_kecamatan'),TRUE) && $product['status']==='active' && $action!=='withdraw')
                    throw new InvalidArgumentException('Tarik produk dari etalase sebelum mengubahnya.');
                if ($action==='save') $this->save_product($product,$user);
                elseif ($action==='variant') $this->save_variant($product,$user);
                elseif ($action==='variant_delete') $this->delete_variant($product,$user);
                elseif ($action==='upload') $this->upload_media($product,$user);
                elseif ($action==='media_cover' || $action==='media_delete' || $action==='media_up' || $action==='media_down') $this->update_media($product,$user,$action);
                elseif ($action==='submit_review') $this->submit_review($product,$user);
                elseif ($action==='cancel_review') $this->cancel_review($product,$user);
                elseif ($action==='withdraw') $this->withdraw($product,$user);
                else throw new InvalidArgumentException('Aksi tidak dikenal.');
                redirect('pengelola/produk/'.$id.'?tersimpan=1','location',303); return;
            }
            catch (Exception $e) { $error=$e->getMessage(); }
        }
        $product=$this->product($id);
        $variants=$this->db->query('SELECT * FROM prd_variants WHERE product_id=? ORDER BY sort_order,id',array($id))->result_array();
        foreach ($variants as &$variant)
        {
            $attrs=$this->db->query('SELECT attribute_name,attribute_value FROM prd_variant_attributes WHERE variant_id=? ORDER BY attribute_name',array($variant['id']))->result_array();
            $variant['attributes_text']=implode("\n",array_map(function($a){return $a['attribute_name'].': '.$a['attribute_value'];},$attrs));
        } unset($variant);
        $specs=$this->db->query('SELECT spec_name,spec_value FROM prd_product_specs WHERE product_id=? ORDER BY sort_order,spec_name',array($id))->result_array();
        $specs_text=implode("\n",array_map(function($a){return $a['spec_name'].': '.$a['spec_value'];},$specs));
        $category_options=$this->db->query("SELECT c.id,c.name FROM ref_categories c JOIN tko_store_categories sc ON sc.store_id=? AND sc.category_id=c.id AND sc.status='approved' WHERE c.is_active=1 AND NOT EXISTS (SELECT 1 FROM ref_categories child WHERE child.parent_id=c.id) ORDER BY c.name",array($product['store_id']))->result_array();
        $current_category=(int)$this->db->query('SELECT category_id FROM prd_product_categories WHERE product_id=? LIMIT 1',array($id))->row('category_id');
        $media=$this->db->query('SELECT * FROM prd_media WHERE product_id=? ORDER BY media_type="image" DESC,is_cover DESC,sort_order,id',array($id))->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $locked=$product['status']==='pending' || (in_array($user['role_code'],array('umkm','admin_kecamatan'),TRUE) && $product['status']==='active');
        $this->load->view('staff/product',compact('user','product','variants','specs_text','category_options','current_category','media','error','locked'));
    }

    private function lines($text,$max,$name_max,$value_max)
    {
        $result=array();
        foreach (preg_split('/\r\n|\r|\n/',trim($text)) as $line)
        {
            if (trim($line)==='') continue;
            $parts=explode(':',$line,2);
            if (count($parts)!==2 || !trim($parts[0]) || !trim($parts[1])) throw new InvalidArgumentException('Gunakan format Nama: nilai pada setiap baris.');
            $key=trim($parts[0]);$value=trim($parts[1]);
            if (mb_strlen($key)>$name_max || mb_strlen($value)>$value_max || isset($result[$key])) throw new InvalidArgumentException('Spesifikasi atau atribut terlalu panjang atau berulang.');
            $result[$key]=$value;
            if (count($result)>$max) throw new InvalidArgumentException('Terlalu banyak spesifikasi atau atribut.');
        }
        return $result;
    }

    private function save_product($product,$user)
    {
        $name=trim((string)$this->input->post('name',TRUE));
        $description=trim((string)$this->input->post('description',TRUE));
        $unit=trim((string)$this->input->post('unit_label',TRUE));
        $note=trim((string)$this->input->post('availability_note',TRUE));
        $price=trim((string)$this->input->post('price_min',TRUE));
        $status=(string)$this->input->post('status',TRUE);
        $category_id=(int)$this->input->post('category_id',TRUE);
        $specs=$this->lines((string)$this->input->post('specs',TRUE),20,80,255);
        if (mb_strlen($name)<2 || mb_strlen($name)>190 || mb_strlen($description)>10000 || mb_strlen($unit)>40 || mb_strlen($note)>160 || !in_array($status,array('draft','active','inactive'),TRUE) || ($status==='active' && $product['status']!=='active') || ($price!=='' && (!is_numeric($price) || $price<0 || $price>999999999999))) throw new InvalidArgumentException('Periksa nama, harga, status, dan panjang isian. Publikasi baru memerlukan peninjauan.');
        $category=$this->db->query("SELECT c.id FROM ref_categories c JOIN tko_store_categories sc ON sc.store_id=? AND sc.category_id=c.id AND sc.status='approved' WHERE c.id=? AND c.is_active=1 AND NOT EXISTS (SELECT 1 FROM ref_categories child WHERE child.parent_id=c.id) LIMIT 1",array($product['store_id'],$category_id))->row();
        if (!$category) throw new InvalidArgumentException('Pilih kategori yang disetujui untuk toko ini.');
        $this->db->trans_begin();
        $this->db->query('UPDATE prd_products SET name=?,description=?,base_price=?,unit_label=?,availability_note=?,status=?,published_at=IF(?="active",COALESCE(published_at,NOW()),published_at) WHERE id=?',array($name,$description,$price===''?NULL:$price,$unit,$note,$status,$status,$product['id']));
        $this->db->query('DELETE FROM prd_product_specs WHERE product_id=?',array($product['id']));
        $i=0;foreach ($specs as $key=>$value) $this->db->query('INSERT INTO prd_product_specs (product_id,spec_name,spec_value,sort_order) VALUES (?,?,?,?)',array($product['id'],$key,$value,$i++));
        $this->db->query('DELETE FROM prd_product_categories WHERE product_id=?',array($product['id']));
        $this->db->query('INSERT INTO prd_product_categories (product_id,category_id) VALUES (?,?)',array($product['id'],$category_id));
        $this->refresh_prices($product['id']);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();throw new RuntimeException('Produk gagal disimpan.'); }
        $this->Staff_model->audit($user['id'],'catalog.product_update','prd_products',$product['id']);
        $this->db->trans_commit();
    }

    private function submit_review($product,$user)
    {
        if (!in_array($product['status'],array('draft','inactive'),TRUE))
            throw new InvalidArgumentException('Simpan produk sebagai draf sebelum mengajukan peninjauan.');
        if (mb_strlen(trim((string)$product['description']),'UTF-8')<20 ||
            !$this->db->query("SELECT 1 FROM prd_media WHERE product_id=? AND media_type='image' LIMIT 1",array($product['id']))->row() ||
            !$this->db->query("SELECT 1 FROM prd_product_categories pc
                JOIN ref_categories c ON c.id=pc.category_id AND c.is_active=1
                JOIN tko_store_categories sc ON sc.category_id=c.id AND sc.store_id=? AND sc.status='approved'
                WHERE pc.product_id=? LIMIT 1",array($product['store_id'],$product['id']))->row())
            throw new InvalidArgumentException('Lengkapi deskripsi minimal 20 karakter, kategori, dan satu foto produk.');
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM prd_products WHERE id=? FOR UPDATE',array($product['id']));
        $this->db->query("INSERT INTO prd_review_requests (product_id,user_id,status) VALUES (?,?,'pending')",
            array($product['id'],$user['id']));
        $review_id=(int)$this->db->insert_id();
        $this->db->query("UPDATE prd_products SET status='pending',published_at=NULL WHERE id=?",array($product['id']));
        $this->Staff_model->audit($user['id'],'catalog.review_submitted','prd_review_requests',$review_id);
        $this->Notification_model->publish('product_review','product-review:'.$review_id,
            'Produk menunggu peninjauan',$product['name'].' diajukan untuk tampil di etalase.',
            '/pengelola/tinjauan-produk',$this->Notification_model->admin_ids());
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); throw new RuntimeException('Pengajuan produk gagal disimpan.'); }
        $this->db->trans_commit();
    }

    private function cancel_review($product,$user)
    {
        if ($product['status']!=='pending') throw new InvalidArgumentException('Tidak ada pengajuan yang menunggu.');
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM prd_products WHERE id=? FOR UPDATE',array($product['id']));
        $this->db->query("UPDATE prd_review_requests SET status='cancelled',reviewed_at=NOW(),
            decision_note='Dibatalkan oleh pengelola toko' WHERE product_id=? AND status='pending'",
            array($product['id']));
        $this->db->query("UPDATE prd_products SET status='draft' WHERE id=?",array($product['id']));
        $this->Staff_model->audit($user['id'],'catalog.review_cancelled','prd_products',$product['id']);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); throw new RuntimeException('Pengajuan gagal dibatalkan.'); }
        $this->db->trans_commit();
    }

    private function withdraw($product,$user)
    {
        if ($product['status']!=='active') throw new InvalidArgumentException('Produk tidak sedang tampil.');
        $this->db->trans_begin();
        $this->db->query("UPDATE prd_products SET status='draft',published_at=NULL WHERE id=?",array($product['id']));
        $this->Staff_model->audit($user['id'],'catalog.product_withdrawn','prd_products',$product['id']);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); throw new RuntimeException('Produk gagal ditarik.'); }
        $this->db->trans_commit();
    }

    public function reviews()
    {
        $user=$this->actor(); if (!$user) return;
        if (!in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        { show_error('Akses tidak diizinkan.',403); return; }
        $status=(string)$this->input->get('status',TRUE);
        if (!in_array($status,array('pending','approved','rejected','cancelled'),TRUE)) $status='pending';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $page=max(1,(int)$this->input->get('page',TRUE));
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $where=' WHERE r.status=?';$params=array($status);
        if ($search!=='') { $where.=' AND (p.name LIKE ? OR s.display_name LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        $from=' FROM prd_review_requests r JOIN prd_products p ON p.id=r.product_id
            JOIN tko_stores s ON s.id=p.store_id';
        $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));$page=min($page,$pages);
        $rows=$this->db->query('SELECT r.id,r.product_id,r.status,r.created_at,r.decision_note,
            p.name,p.status product_status,s.display_name store_name,u.full_name requester_name
            '.$from.' JOIN iam_users u ON u.id=r.user_id
            '.$where.' ORDER BY r.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $this->load->view('staff/product_reviews',compact('user','rows','status','search','per_page','page','pages','total'));
    }

    public function review($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        if (!in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        { show_error('Akses tidak diizinkan.',403); return; }
        $decision=(string)$this->input->post('decision',TRUE);
        $note=trim((string)$this->input->post('decision_note',TRUE));
        if (!in_array($decision,array('approved','rejected'),TRUE) || mb_strlen($note,'UTF-8')<10 || mb_strlen($note,'UTF-8')>500)
        { show_error('Keputusan dan alasan minimal 10 karakter diperlukan.',400); return; }
        $this->db->trans_begin();
        $row=$this->db->query('SELECT r.*,p.name,p.status product_status,s.status store_status
            FROM prd_review_requests r JOIN prd_products p ON p.id=r.product_id
            JOIN tko_stores s ON s.id=p.store_id WHERE r.id=? FOR UPDATE',array((int)$id))->row_array();
        if (!$row || $row['status']!=='pending' || $row['product_status']!=='pending')
        { $this->db->trans_rollback(); show_error('Pengajuan tidak tersedia.',409); return; }
        if ($decision==='approved' && $row['store_status']!=='active')
        { $this->db->trans_rollback(); show_error('Toko harus aktif sebelum produk diterbitkan.',409); return; }
        if ($decision==='approved' && !$this->db->query("SELECT 1 FROM prd_product_categories pc
            JOIN ref_categories c ON c.id=pc.category_id AND c.is_active=1
            JOIN prd_products p ON p.id=pc.product_id
            JOIN tko_store_categories sc ON sc.category_id=c.id AND sc.store_id=p.store_id AND sc.status='approved'
            WHERE pc.product_id=? LIMIT 1",array($row['product_id']))->row())
        { $this->db->trans_rollback(); show_error('Kategori produk tidak lagi disetujui untuk toko.',409); return; }
        $this->db->query('UPDATE prd_review_requests SET status=?,decision_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',
            array($decision,$note,$user['id'],$id));
        $this->db->query("UPDATE prd_products SET status=?,published_at=IF(?='active',NOW(),NULL) WHERE id=?",
            array($decision==='approved'?'active':'draft',$decision==='approved'?'active':'draft',$row['product_id']));
        $this->Staff_model->audit($user['id'],'catalog.review_'.$decision,'prd_review_requests',$id,$note);
        $this->Notification_model->publish('product_review_decided','product-review:'.$id.':decision',
            $decision==='approved'?'Produk disetujui':'Produk perlu diperbaiki',
            'Pengajuan produk '.$row['name'].' telah ditinjau.','/pengelola/produk/'.$row['product_id'],array((int)$row['user_id']));
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Keputusan gagal disimpan.',500); return; }
        $this->db->trans_commit();
        redirect('pengelola/tinjauan-produk?tersimpan=1','location',303);
    }

    private function save_variant($product,$user)
    {
        $id=(int)$this->input->post('variant_id',TRUE);
        $label=trim((string)$this->input->post('label',TRUE));
        $sku=trim((string)$this->input->post('sku',TRUE));
        $price=trim((string)$this->input->post('price',TRUE));
        $stock=trim((string)$this->input->post('stock_qty',TRUE));
        $attrs=$this->lines((string)$this->input->post('attributes',TRUE),10,60,120);
        if (mb_strlen($label)<1 || mb_strlen($label)>190 || mb_strlen($sku)>80 || ($price!=='' && (!is_numeric($price)||$price<0||$price>999999999999)) || ($stock!=='' && (!ctype_digit($stock)||$stock>100000000))) throw new InvalidArgumentException('Periksa varian, harga, dan stok.');
        if ($id && !$this->db->query('SELECT id FROM prd_variants WHERE id=? AND product_id=?',array($id,$product['id']))->row()) throw new InvalidArgumentException('Varian tidak ditemukan.');
        $this->db->trans_begin();
        if ($id) $this->db->query('UPDATE prd_variants SET label=?,sku=?,price=?,stock_qty=?,is_active=? WHERE id=?',array($label,$sku?:NULL,$price===''?NULL:$price,$stock===''?NULL:$stock,$this->input->post('is_active')?1:0,$id));
        else { $this->db->query('INSERT INTO prd_variants (product_id,label,sku,price,stock_qty,is_active) VALUES (?,?,?,?,?,1)',array($product['id'],$label,$sku?:NULL,$price===''?NULL:$price,$stock===''?NULL:$stock));$id=(int)$this->db->insert_id(); }
        $this->db->query('DELETE FROM prd_variant_attributes WHERE variant_id=?',array($id));
        foreach ($attrs as $key=>$value) $this->db->query('INSERT INTO prd_variant_attributes (variant_id,attribute_name,attribute_value) VALUES (?,?,?)',array($id,$key,$value));
        $this->refresh_prices($product['id']);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();throw new RuntimeException('Varian gagal disimpan. Periksa apakah SKU sudah dipakai.'); }
        $this->Staff_model->audit($user['id'],'catalog.variant_update','prd_variants',$id);
        $this->db->trans_commit();
    }

    private function delete_variant($product,$user)
    {
        $id=(int)$this->input->post('variant_id',TRUE);
        $this->db->query('UPDATE prd_variants SET is_active=0 WHERE id=? AND product_id=?',array($id,$product['id']));
        $this->refresh_prices($product['id']);
        $this->Staff_model->audit($user['id'],'catalog.variant_deactivate','prd_variants',$id);
    }

    private function refresh_prices($product_id)
    {
        $row=$this->db->query('SELECT base_price FROM prd_products WHERE id=?',array($product_id))->row_array();
        $range=$this->db->query('SELECT COUNT(*) n,MIN(COALESCE(price,?)) low_price,MAX(COALESCE(price,?)) high_price FROM prd_variants WHERE product_id=? AND is_active=1',array($row['base_price'],$row['base_price'],$product_id))->row_array();
        $low=$range['n']?$range['low_price']:$row['base_price'];
        $high=$range['n']?$range['high_price']:$row['base_price'];
        $this->db->query('UPDATE prd_products SET price_min=?,price_max=? WHERE id=?',array($low,$high,$product_id));
    }

    private function upload_media($product,$user)
    {
        if (empty($_FILES['media']['name']) || !is_array($_FILES['media']['name'])) throw new InvalidArgumentException('Pilih foto atau video.');
        $count=count($_FILES['media']['name']); if ($count>10) throw new InvalidArgumentException('Maksimal 10 berkas sekali unggah.');
        $existing=$this->db->query("SELECT media_type,COUNT(*) n FROM prd_media WHERE product_id=? GROUP BY media_type",array($product['id']))->result_array();
        $totals=array('image'=>0,'video'=>0);foreach($existing as $row)$totals[$row['media_type']]=(int)$row['n'];
        $files=array();
        for($i=0;$i<$count;$i++)
        {
            if ($_FILES['media']['error'][$i]!==UPLOAD_ERR_OK || !is_uploaded_file($_FILES['media']['tmp_name'][$i])) throw new InvalidArgumentException('Unggahan gagal. Periksa ukuran berkas.');
            $tmp=$_FILES['media']['tmp_name'][$i];$size=(int)$_FILES['media']['size'][$i];
            $image=@getimagesize($tmp);
            $header=file_get_contents($tmp,FALSE,NULL,0,4096);
            $mime=$image && isset($image['mime']) ? $image['mime'] : NULL;
            if (!$mime && strlen($header)>=12 && substr($header,4,4)==='ftyp') $mime='video/mp4';
            if (!$mime && substr($header,0,4)==="\x1A\x45\xDF\xA3" && strpos($header,'webm')!==FALSE) $mime='video/webm';
            $types=array('image/jpeg'=>array('image','jpg'),'image/png'=>array('image','png'),'image/webp'=>array('image','webp'),'video/mp4'=>array('video','mp4'),'video/webm'=>array('video','webm'));
            if (!isset($types[$mime])) throw new InvalidArgumentException('Gunakan JPG, PNG, WebP, MP4, atau WebM.');
            list($type,$ext)=$types[$mime];
            if ($size<1 || $size>($type==='image'?5:30)*1048576) throw new InvalidArgumentException('Foto maksimal 5 MB dan video maksimal 30 MB.');
            if ($type==='image' && (!$image || $image['mime']!==$mime)) throw new InvalidArgumentException('Foto tidak valid.');
            $totals[$type]++; if ($totals[$type]>($type==='image'?8:2)) throw new InvalidArgumentException('Maksimal 8 foto dan 2 video per produk.');
            $files[]=array($tmp,$mime,$type,$ext);
        }
        $dir=FCPATH.'assets/products/';if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Direktori unggahan belum siap.');
        $has_cover=(bool)$this->db->query("SELECT id FROM prd_media WHERE product_id=? AND media_type='image' AND is_cover=1 LIMIT 1",array($product['id']))->row();
        $stored=array();
        $this->db->trans_begin();
        try
        {
            foreach ($files as $file)
            {
                list($tmp,$mime,$type,$ext)=$file;
                $key='p'.$product['id'].'-'.bin2hex(random_bytes(12)).'.'.$ext;
                if (!move_uploaded_file($tmp,$dir.$key)) throw new RuntimeException('Berkas gagal disimpan.');
                $stored[]=$dir.$key;
                chmod($dir.$key,0644);
                $cover=$type==='image' && !$has_cover ? 1:0;
                if ($cover) $has_cover=TRUE;
                $this->db->query('INSERT INTO prd_media (product_id,media_type,storage_key,mime_type,alt_text,sort_order,is_cover,created_by) VALUES (?,?,?,?,?,?,?,?)',array($product['id'],$type,$key,$mime,$product['name'],(int)$this->db->query('SELECT COALESCE(MAX(sort_order),0)+1 n FROM prd_media WHERE product_id=?',array($product['id']))->row('n'),$cover,$user['id']));
                if ($this->db->error()['code']) throw new RuntimeException('Media gagal dicatat.');
            }
            $this->Staff_model->audit($user['id'],'catalog.media_upload','prd_products',$product['id'],count($files).' berkas');
            if ($this->db->trans_status()===FALSE) throw new RuntimeException('Media gagal dicatat.');
            $this->db->trans_commit();
        }
        catch (Exception $e)
        {
            $this->db->trans_rollback();
            foreach ($stored as $path) @unlink($path);
            throw $e;
        }
    }

    private function update_media($product,$user,$action)
    {
        $id=(int)$this->input->post('media_id',TRUE);
        $item=$this->db->query('SELECT * FROM prd_media WHERE id=? AND product_id=?',array($id,$product['id']))->row_array();
        if (!$item) throw new InvalidArgumentException('Media tidak ditemukan.');
        if ($action==='media_cover')
        {
            if ($item['media_type']!=='image') throw new InvalidArgumentException('Sampul harus berupa foto.');
            $this->db->trans_begin();
            $this->db->query("UPDATE prd_media SET is_cover=0 WHERE product_id=? AND media_type='image'",array($product['id']));
            $this->db->query('UPDATE prd_media SET is_cover=1 WHERE id=?',array($id));
            $this->db->trans_commit();
        }
        elseif ($action==='media_delete')
        {
            $this->db->query('DELETE FROM prd_media WHERE id=?',array($id));
            if ($item['is_cover'] && $item['media_type']==='image')
            {
                $next=$this->db->query("SELECT id FROM prd_media WHERE product_id=? AND media_type='image' ORDER BY sort_order,id LIMIT 1",array($product['id']))->row_array();
                if ($next) $this->db->query('UPDATE prd_media SET is_cover=1 WHERE id=?',array($next['id']));
            }
            @unlink(FCPATH.'assets/products/'.$item['storage_key']);
        }
        else $this->db->query('UPDATE prd_media SET sort_order=? WHERE id=?',array(max(0,(int)$item['sort_order']+($action==='media_up'?-2:2)),$id));
        $this->Staff_model->audit($user['id'],'catalog.'.$action,'prd_media',$id);
    }
}
