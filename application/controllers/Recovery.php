<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Recovery extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();$this->load->library('session');
        $this->load->model('Staff_model');$this->load->model('Notification_model');
        $this->load->helper('workflow_evidence');
    }

    private function admin()
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302);return NULL; }
        $user=$this->Staff_model->by_id($id);
        if (!$user || $user['status']!=='active' ||
            !in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        { show_error('Akses tidak diizinkan.',403);return NULL; }
        return $user;
    }

    public function form()
    {
        $this->output->set_header('Cache-Control: no-store');
        $this->load->view('staff/recovery_form',array('error'=>'','sent'=>(bool)$this->input->get('terkirim',TRUE)));
    }

    public function submit()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404();return; }
        $email=strtolower(trim((string)$this->input->post('email',TRUE)));
        $note=trim((string)$this->input->post('requester_note',TRUE));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email,'UTF-8')>190 ||
            mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500)
        { $this->load->view('staff/recovery_form',array('error'=>'Periksa email dan keterangan minimal 20 karakter.','sent'=>FALSE));return; }
        $ip_hash=hash('sha256',$email."\x1f".$this->input->ip_address(),TRUE);
        $count=(int)$this->db->query('SELECT COUNT(*) n FROM iam_recovery_attempts
            WHERE ip_hash=? AND attempted_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)',array($ip_hash))->row('n');
        if ($count>=3)
        { $this->load->view('staff/recovery_form',array('error'=>'Terlalu banyak permintaan. Coba lagi besok.','sent'=>FALSE));return; }
        $this->db->query('INSERT INTO iam_recovery_attempts (ip_hash) VALUES (?)',array($ip_hash));
        try { $file=workflow_store_evidence('evidence','recovery'); }
        catch(Exception $e)
        { $this->load->view('staff/recovery_form',array('error'=>$e->getMessage(),'sent'=>FALSE));return; }
        $account=$this->db->query("SELECT u.id FROM iam_users u
            JOIN iam_user_roles ur ON ur.user_id=u.id JOIN iam_roles r ON r.id=ur.role_id
            WHERE u.email=? AND r.code='umkm' LIMIT 1",array($email))->row_array();
        if (!$account)
        { @unlink($file['path']);redirect('pengelola/pulihkan-akun?terkirim=1','location',303);return; }
        $pending=$this->db->query("SELECT id FROM iam_recovery_requests
            WHERE user_id=? AND status='pending' LIMIT 1",array($account['id']))->row();
        if ($pending)
        { @unlink($file['path']);redirect('pengelola/pulihkan-akun?terkirim=1','location',303);return; }
        $business=$this->db->query('SELECT COALESCE(b.canonical_business_id,b.id) business_id
            FROM iam_imported_business_accounts a JOIN umk_businesses b ON b.id=a.business_id
            WHERE a.user_id=? LIMIT 1',array($account['id']))->row_array();
        $this->db->trans_begin();
        $this->db->query("INSERT INTO iam_recovery_requests
            (user_id,business_id,requester_note,evidence_key,evidence_mime,evidence_sha256,request_ip_hash,status)
            VALUES (?,?,?,?,?,?,?,'pending')",
            array($account['id'],$business?$business['business_id']:NULL,$note,
                $file['key'],$file['mime'],$file['sha256'],$ip_hash));
        $request_id=(int)$this->db->insert_id();
        $this->Notification_model->publish('account_recovery','account-recovery:'.$request_id,
            'Pemulihan akun diajukan','Permintaan pemulihan akun UMKM perlu pemeriksaan identitas.',
            '/pengelola/pemulihan-akun',$this->Notification_model->admin_ids());
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback();@unlink($file['path']);show_error('Permintaan gagal disimpan.',500);return; }
        $this->db->trans_commit();
        redirect('pengelola/pulihkan-akun?terkirim=1','location',303);
    }

    public function queue()
    {
        $user=$this->admin();if (!$user)return;
        $this->output->set_header('Cache-Control: no-store');
        $status=(string)$this->input->get('status',TRUE);
        if (!in_array($status,array('pending','approved','rejected'),TRUE)) $status='pending';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $page=max(1,(int)$this->input->get('page',TRUE));
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $from=' FROM iam_recovery_requests r JOIN iam_users u ON u.id=r.user_id
            LEFT JOIN umk_businesses b ON b.id=r.business_id';
        $where=' WHERE r.status=?';$params=array($status);
        if ($search!=='') { $where.=' AND (u.full_name LIKE ? OR u.email LIKE ? OR b.name LIKE ?)';
            $params[]='%'.$search.'%';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));$page=min($page,$pages);
        $rows=$this->db->query('SELECT r.id,r.status,r.requester_note,r.decision_note,r.created_at,
            u.full_name,u.email,b.name business_name '.$from.$where.
            ' ORDER BY r.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $temporary_password=(string)$this->session->flashdata('recovery_temporary_password');
        $temporary_account=(string)$this->session->flashdata('recovery_temporary_account');
        $this->load->view('staff/recovery_queue',compact('user','status','search','per_page','page','pages','total','rows','temporary_password','temporary_account'));
    }

    public function evidence($id=0)
    {
        $user=$this->admin();if (!$user)return;
        $row=$this->db->query('SELECT evidence_key,evidence_mime,evidence_sha256
            FROM iam_recovery_requests WHERE id=? LIMIT 1',array((int)$id))->row_array();
        if (!$row) { show_404();return; }
        workflow_download_evidence('recovery',$row['evidence_key'],$row['evidence_mime'],
            $row['evidence_sha256'],'Bukti-pemulihan-'.$id);
    }

    public function decide($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404();return; }
        $user=$this->admin();if (!$user)return;
        $decision=(string)$this->input->post('decision',TRUE);
        $note=trim((string)$this->input->post('decision_note',TRUE));
        if (!in_array($decision,array('approved','rejected'),TRUE) ||
            mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500)
        { show_error('Keputusan dan alasan minimal 20 karakter diperlukan.',400);return; }
        if ($decision==='approved' && !$this->input->post('identity_checked'))
        { show_error('Tandai bahwa identitas pemohon sudah diperiksa.',400);return; }
        $this->db->trans_begin();
        $request=$this->db->query('SELECT * FROM iam_recovery_requests WHERE id=? FOR UPDATE',array((int)$id))->row_array();
        if (!$request || $request['status']!=='pending')
        { $this->db->trans_rollback();show_error('Permintaan tidak tersedia.',409);return; }
        $temporary_password=NULL;
        if ($decision==='approved')
        {
            $temporary_password=bin2hex(random_bytes(12));
            $this->db->query('UPDATE iam_users SET password_hash=?,auth_epoch=auth_epoch+1,status=\'active\' WHERE id=?',
                array(password_hash($temporary_password,PASSWORD_BCRYPT),$request['user_id']));
        }
        $this->db->query('UPDATE iam_recovery_requests SET status=?,decision_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',
            array($decision,$note,$user['id'],$id));
        $this->Staff_model->audit($user['id'],'iam.recovery_'.$decision,'iam_recovery_requests',$id,$note);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback();show_error('Keputusan gagal disimpan.',500);return; }
        $this->db->trans_commit();
        if ($temporary_password!==NULL)
        {
            $email=(string)$this->db->query('SELECT email FROM iam_users WHERE id=?',
                array($request['user_id']))->row('email');
            $this->session->set_flashdata('recovery_temporary_password',$temporary_password);
            $this->session->set_flashdata('recovery_temporary_account',$email);
        }
        redirect('pengelola/pemulihan-akun?tersimpan=1','location',303);
    }
}
