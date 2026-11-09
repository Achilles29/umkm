<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Service_tickets extends CI_Controller
{
    private $types=array('consultation'=>'Konsultasi','facilitation'=>'Fasilitasi','complaint'=>'Pengaduan');
    private $statuses=array('submitted'=>'Dikirim','review'=>'Pemeriksaan','needs_revision'=>'Perlu perbaikan',
        'in_progress'=>'Diproses','waiting_external'=>'Menunggu pihak lain','completed'=>'Selesai','rejected'=>'Ditolak');

    public function __construct()
    {
        parent::__construct();
        $this->load->database();$this->load->library('session');
        $this->load->model('Staff_model');$this->load->model('Notification_model');
    }

    private function actor()
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302);return NULL; }
        $user=$this->Staff_model->by_id($id);
        if (!$user || $user['status']!=='active')
        { show_error('Akses tidak diizinkan.',403);return NULL; }
        return $user;
    }

    private function district_ids($user)
    {
        if ($user['role_code']!=='admin_kecamatan') return array();
        $rows=$this->db->query("SELECT dr.district_id FROM iam_district_roles dr
            JOIN iam_roles r ON r.id=dr.role_id AND r.code='admin_kecamatan'
            WHERE dr.user_id=?",array($user['id']))->result_array();
        return array_map('intval',array_column($rows,'district_id'));
    }

    private function scope_sql($user,&$params)
    {
        if ($user['role_code']==='umkm') { $params[]=$user['id'];return 't.requester_id=?'; }
        if ($user['role_code']==='admin_kecamatan')
        {
            $ids=$this->district_ids($user);
            return $ids?'t.district_id IN ('.implode(',',$ids).')':'1=0';
        }
        if (in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE)) return '1=1';
        return '1=0';
    }

    private function ticket($id,$user,$lock=FALSE)
    {
        $params=array();$scope=$this->scope_sql($user,$params);
        array_unshift($params,(int)$id);
        return $this->db->query('SELECT t.*,u.full_name requester_name,b.name business_name,
            a.full_name assignee_name FROM lay_tickets t
            JOIN iam_users u ON u.id=t.requester_id
            LEFT JOIN umk_businesses b ON b.id=t.business_id
            LEFT JOIN iam_users a ON a.id=t.assigned_to
            WHERE t.id=? AND '.$scope.' LIMIT 1'.($lock?' FOR UPDATE':''),$params)->row_array();
    }

    private function business_options($user)
    {
        return $this->db->query('SELECT DISTINCT b.id,b.name,d.name district_name
            FROM umk_businesses b LEFT JOIN ref_districts d ON d.id=b.district_id
            WHERE b.is_demo=0 AND b.canonical_business_id IS NULL AND
              (EXISTS (SELECT 1 FROM umk_business_claims c WHERE c.business_id=b.id
                  AND c.user_id=? AND c.status=\'approved\')
               OR EXISTS (SELECT 1 FROM iam_imported_business_accounts ia
                  JOIN umk_businesses source ON source.id=ia.business_id
                  WHERE ia.user_id=? AND COALESCE(source.canonical_business_id,source.id)=b.id))
            ORDER BY b.name LIMIT 100',array($user['id'],$user['id']))->result_array();
    }

    private function recipients($district_id)
    {
        $ids=$this->Notification_model->admin_ids();
        if ($district_id)
        {
            $rows=$this->db->query("SELECT DISTINCT dr.user_id FROM iam_district_roles dr
                JOIN iam_users u ON u.id=dr.user_id AND u.status='active'
                JOIN iam_roles r ON r.id=dr.role_id AND r.code='admin_kecamatan'
                WHERE dr.district_id=?",array($district_id))->result_array();
            $ids=array_merge($ids,array_map('intval',array_column($rows,'user_id')));
        }
        return array_values(array_unique($ids));
    }

    public function index()
    {
        $user=$this->actor();if (!$user)return;
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $status=(string)$this->input->get('status',TRUE);
        if ($status!=='' && !isset($this->statuses[$status])) $status='';
        $type=(string)$this->input->get('type',TRUE);
        if ($type!=='' && !isset($this->types[$type])) $type='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $page=max(1,(int)$this->input->get('page',TRUE));
        $params=array();$where=' WHERE '.$this->scope_sql($user,$params);
        if ($search!=='') { $where.=' AND (t.reference_no LIKE ? OR t.subject LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        if ($status!=='') { $where.=' AND t.status=?';$params[]=$status; }
        if ($type!=='') { $where.=' AND t.ticket_type=?';$params[]=$type; }
        $total=(int)$this->db->query('SELECT COUNT(*) n FROM lay_tickets t'.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));$page=min($page,$pages);
        $rows=$this->db->query('SELECT t.id,t.reference_no,t.subject,t.ticket_type,t.status,
            t.created_at,t.assigned_to,u.full_name requester_name
            FROM lay_tickets t JOIN iam_users u ON u.id=t.requester_id'.$where.
            ' ORDER BY t.updated_at DESC,t.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $types=$this->types;$statuses=$this->statuses;
        $this->load->view('staff/tickets',compact('user','rows','types','statuses','search','status','type','per_page','page','pages','total'));
    }

    public function create()
    {
        $user=$this->actor();if (!$user)return;
        if ($user['role_code']!=='umkm') { show_error('Permohonan dari akun UMKM.',403);return; }
        $businesses=$this->business_options($user);
        $types=$this->types;
        if ($this->input->method(TRUE)==='POST')
        {
            $business_id=(int)$this->input->post('business_id',TRUE);
            $type=(string)$this->input->post('ticket_type',TRUE);
            $subject=trim((string)$this->input->post('subject',TRUE));
            $description=trim((string)$this->input->post('description',TRUE));
            if (!isset($types[$type]) || mb_strlen($subject,'UTF-8')<5 ||
                mb_strlen($subject,'UTF-8')>190 || mb_strlen($description,'UTF-8')<20 ||
                mb_strlen($description,'UTF-8')>10000 ||
                ($business_id && !in_array($business_id,array_map('intval',array_column($businesses,'id')),TRUE)))
            { show_error('Periksa jenis, kegiatan usaha, judul, dan uraian minimal 20 karakter.',400);return; }
            $district_id=NULL;
            if ($business_id) $district_id=$this->db->query('SELECT district_id FROM umk_businesses WHERE id=?',array($business_id))->row('district_id');
            $reference='LAY-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(5)));
            $this->db->trans_begin();
            $this->db->query("INSERT INTO lay_tickets
                (reference_no,requester_id,business_id,district_id,ticket_type,subject,description,status)
                VALUES (?,?,?,?,?,?,?,'submitted')",
                array($reference,$user['id'],$business_id?:NULL,$district_id,$type,$subject,$description));
            $id=(int)$this->db->insert_id();
            $this->db->query("INSERT INTO lay_ticket_updates
                (ticket_id,actor_id,new_status,note,visibility) VALUES (?,?,'submitted','Permohonan dikirim','public')",
                array($id,$user['id']));
            $this->Staff_model->audit($user['id'],'lay.ticket_created','lay_tickets',$id);
            $this->Notification_model->publish('ticket_created','ticket:'.$id,
                'Permohonan layanan baru',$reference.' · '.$subject,
                '/pengelola/layanan/'.$id,$this->recipients($district_id));
            if ($this->db->trans_status()===FALSE)
            { $this->db->trans_rollback();show_error('Permohonan gagal disimpan.',500);return; }
            $this->db->trans_commit();
            redirect('pengelola/layanan/'.$id.'?terkirim=1','location',303);return;
        }
        $this->load->view('staff/ticket_create',compact('user','businesses','types'));
    }

    public function detail($id=0)
    {
        $user=$this->actor();if (!$user)return;
        $ticket=$this->ticket($id,$user);
        if (!$ticket) { show_404();return; }
        $visibility=$user['role_code']==='umkm'?" AND visibility='public'":'';
        $updates=$this->db->query('SELECT up.*,u.full_name actor_name FROM lay_ticket_updates up
            LEFT JOIN iam_users u ON u.id=up.actor_id WHERE up.ticket_id=?'.$visibility.
            ' ORDER BY up.id',array($ticket['id']))->result_array();
        $staff=array();
        if ($user['role_code']!=='umkm')
            $staff=$this->db->query("SELECT DISTINCT u.id,u.full_name FROM iam_users u
                JOIN iam_user_roles ur ON ur.user_id=u.id JOIN iam_roles r ON r.id=ur.role_id
                WHERE u.status='active' AND r.code IN ('superadmin','admin_dinas')
                ORDER BY u.full_name LIMIT 200")->result_array();
        $statuses=$this->statuses;$types=$this->types;
        $this->load->view('staff/ticket_detail',compact('user','ticket','updates','staff','statuses','types'));
    }

    public function update($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404();return; }
        $user=$this->actor();if (!$user)return;
        $note=trim((string)$this->input->post('note',TRUE));
        $status=(string)$this->input->post('status',TRUE);
        $visibility=(string)$this->input->post('visibility',TRUE);
        $assignee=(int)$this->input->post('assigned_to',TRUE);
        if (mb_strlen($note,'UTF-8')<10 || mb_strlen($note,'UTF-8')>3000)
        { show_error('Catatan harus berisi 10 sampai 3.000 karakter.',400);return; }
        $this->db->trans_begin();
        $ticket=$this->ticket($id,$user,TRUE);
        if (!$ticket) { $this->db->trans_rollback();show_404();return; }
        if ($user['role_code']==='umkm')
        {
            if (in_array($ticket['status'],array('completed','rejected'),TRUE))
            { $this->db->trans_rollback();show_error('Permohonan sudah ditutup.',409);return; }
            $new_status=$ticket['status']==='needs_revision'?'submitted':$ticket['status'];
            $visibility='public';$assignee=(int)$ticket['assigned_to'];
        }
        else
        {
            if (!isset($this->statuses[$status]) || $status==='submitted' ||
                !in_array($visibility,array('public','internal'),TRUE) ||
                ($visibility==='internal' && $status!==$ticket['status'] &&
                    in_array($status,array('needs_revision','completed','rejected'),TRUE)) ||
                (in_array($ticket['status'],array('completed','rejected'),TRUE) && $status!==$ticket['status']))
            { $this->db->trans_rollback();show_error('Perubahan status tidak valid.',400);return; }
            $new_status=$status;
            if ($user['role_code']==='admin_kecamatan' && $assignee!==(int)$ticket['assigned_to'])
            { $this->db->trans_rollback();show_error('Penugasan hanya untuk admin Dinas.',403);return; }
            if ($assignee && !$this->db->query("SELECT 1 FROM iam_users u
                JOIN iam_user_roles ur ON ur.user_id=u.id JOIN iam_roles r ON r.id=ur.role_id
                WHERE u.id=? AND u.status='active' AND r.code IN ('superadmin','admin_dinas') LIMIT 1",
                array($assignee))->row())
            { $this->db->trans_rollback();show_error('Petugas tujuan tidak valid.',400);return; }
        }
        $this->db->query('UPDATE lay_tickets SET status=?,assigned_to=?,closed_at=IF(? IN (\'completed\',\'rejected\'),NOW(),NULL) WHERE id=?',
            array($new_status,$assignee?:NULL,$new_status,$ticket['id']));
        $this->db->query('INSERT INTO lay_ticket_updates
            (ticket_id,actor_id,previous_status,new_status,note,visibility) VALUES (?,?,?,?,?,?)',
            array($ticket['id'],$user['id'],$ticket['status'],$new_status,$note,$visibility));
        $this->Staff_model->audit($user['id'],'lay.ticket_updated','lay_tickets',$ticket['id'],$note);
        $recipients=$user['role_code']==='umkm'?$this->recipients($ticket['district_id']):array((int)$ticket['requester_id']);
        if ($assignee && $assignee!==(int)$user['id']) $recipients[]=$assignee;
        $this->Notification_model->publish('ticket_updated','ticket:'.$ticket['id'].':'.bin2hex(random_bytes(4)),
            'Permohonan layanan diperbarui',$ticket['reference_no'].' · '.$ticket['subject'],
            '/pengelola/layanan/'.$ticket['id'],$recipients);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback();show_error('Tindak lanjut gagal disimpan.',500);return; }
        $this->db->trans_commit();
        redirect('pengelola/layanan/'.$ticket['id'].'?tersimpan=1','location',303);
    }
}
