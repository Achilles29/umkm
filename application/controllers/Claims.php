<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Claims extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
        $this->load->model('Notification_model');
        $this->load->helper('workflow_evidence');
    }

    private function actor($admin=FALSE)
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302); return NULL; }
        $user=$this->Staff_model->by_id($id);
        $roles=$admin?array('superadmin','admin_dinas'):array('umkm');
        if (!$user || $user['status']!=='active' || !in_array($user['role_code'],$roles,TRUE))
        { show_error('Akses tidak diizinkan.',403); return NULL; }
        return $user;
    }

    public function index()
    {
        $user=$this->Staff_model->by_id((int)$this->session->userdata('staff_user_id'));
        if (!$user || $user['status']!=='active')
        { redirect('pengelola/masuk','location',302); return; }
        if (in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        {
            $status=(string)$this->input->get('status',TRUE);
            if (!in_array($status,array('submitted','approved','rejected'),TRUE)) $status='submitted';
            $per_page=(int)$this->input->get('per_page',TRUE);
            if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
            $page=max(1,(int)$this->input->get('page',TRUE));
            $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
            $where=' WHERE c.status=?';$params=array($status);
            if ($search!=='') { $where.=' AND (b.name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
                $params[]='%'.$search.'%';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
            $from=' FROM umk_business_claims c JOIN umk_businesses b ON b.id=c.business_id
                JOIN iam_users u ON u.id=c.user_id';
            $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
            $pages=max(1,(int)ceil($total/$per_page));$page=min($page,$pages);
            $rows=$this->db->query('SELECT c.id,c.business_id,c.user_id,c.status,c.reason,c.evidence_key,
                c.decision_note,c.created_at,b.name business_name,u.full_name applicant_name,u.email,
                p.full_name owner_name,s.id store_id
                '.$from.'
                LEFT JOIN umk_business_owners o ON o.business_id=b.id AND o.is_primary=1
                LEFT JOIN umk_people p ON p.id=o.person_id
                LEFT JOIN tko_stores s ON s.business_id=b.id
                '.$where.' ORDER BY c.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
            $this->load->view('staff/claims_admin',compact('user','status','search','per_page','page','pages','total','rows'));
            return;
        }
        if ($user['role_code']!=='umkm') { show_error('Akses tidak diizinkan.',403); return; }
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        if (mb_strlen($search,'UTF-8')>=3)
            $businesses=$this->db->query("SELECT b.id,b.name,d.name district_name FROM umk_businesses b
                LEFT JOIN ref_districts d ON d.id=b.district_id
                WHERE b.canonical_business_id IS NULL AND b.is_demo=0 AND b.name LIKE ?
                ORDER BY b.name LIMIT 20",array('%'.$search.'%'))->result_array();
        else
            $businesses=$this->db->query('SELECT b.id,b.name,d.name district_name
                FROM iam_imported_business_accounts a JOIN umk_businesses source ON source.id=a.business_id
                JOIN umk_businesses b ON b.id=COALESCE(source.canonical_business_id,source.id)
                LEFT JOIN ref_districts d ON d.id=b.district_id WHERE a.user_id=? LIMIT 1',
                array($user['id']))->result_array();
        $mine=$this->db->query('SELECT c.id,c.status,c.created_at,c.decision_note,b.name business_name
            FROM umk_business_claims c JOIN umk_businesses b ON b.id=c.business_id
            WHERE c.user_id=? ORDER BY c.id DESC LIMIT 30',array($user['id']))->result_array();
        $this->load->view('staff/claims_mine',compact('user','search','businesses','mine'));
    }

    public function submit()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        $business_id=(int)$this->input->post('business_id',TRUE);
        $reason=trim((string)$this->input->post('reason',TRUE));
        if ($business_id<1 || mb_strlen($reason,'UTF-8')<20 || mb_strlen($reason,'UTF-8')>1000)
        { show_error('Pilih kegiatan usaha dan jelaskan dasar kepemilikan minimal 20 karakter.',400); return; }
        $business=$this->db->query('SELECT id FROM umk_businesses
            WHERE id=? AND canonical_business_id IS NULL AND is_demo=0 LIMIT 1',array($business_id))->row();
        if (!$business) { show_404(); return; }
        $existing=$this->db->query("SELECT id FROM umk_business_claims
            WHERE business_id=? AND (status='approved' OR (status='submitted' AND user_id=?)) LIMIT 1",
            array($business_id,$user['id']))->row();
        if ($existing) { show_error('Kegiatan ini sudah diklaim atau pengajuan Anda masih menunggu.',409); return; }
        try { $file=workflow_store_evidence('evidence','claims'); }
        catch(Exception $e) { show_error($e->getMessage(),400); return; }
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM umk_businesses WHERE id=? FOR UPDATE',array($business_id));
        $this->db->query("INSERT INTO umk_business_claims
            (business_id,user_id,status,reason,evidence_key,evidence_mime,evidence_sha256)
            VALUES (?,?,'submitted',?,?,?,?)",
            array($business_id,$user['id'],$reason,$file['key'],$file['mime'],$file['sha256']));
        $id=(int)$this->db->insert_id();
        $this->Staff_model->audit($user['id'],'umk.claim_submitted','umk_business_claims',$id);
        $this->Notification_model->publish('business_claim','business-claim:'.$id,
            'Klaim kepemilikan diajukan','Pengajuan klaim kegiatan usaha perlu diperiksa.',
            '/pengelola/klaim-usaha',$this->Notification_model->admin_ids());
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback();@unlink($file['path']);show_error('Klaim gagal disimpan.',500);return; }
        $this->db->trans_commit();
        redirect('pengelola/klaim-usaha?terkirim=1','location',303);
    }

    public function evidence($id=0)
    {
        $user=$this->actor(TRUE); if (!$user) return;
        $row=$this->db->query('SELECT evidence_key,evidence_mime,evidence_sha256
            FROM umk_business_claims WHERE id=? LIMIT 1',array((int)$id))->row_array();
        if (!$row || !$row['evidence_key']) { show_404(); return; }
        workflow_download_evidence('claims',$row['evidence_key'],$row['evidence_mime'],
            $row['evidence_sha256'],'Bukti-klaim-'.$id);
    }

    public function decide($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(TRUE); if (!$user) return;
        $decision=(string)$this->input->post('decision',TRUE);
        $note=trim((string)$this->input->post('decision_note',TRUE));
        if (!in_array($decision,array('approved','rejected'),TRUE) ||
            mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500)
        { show_error('Keputusan dan dasar pemeriksaan minimal 20 karakter diperlukan.',400); return; }
        if ($decision==='approved' && !$this->input->post('evidence_checked'))
        { show_error('Tandai bahwa bukti dan hubungan pemilik sudah diperiksa.',400); return; }
        $this->db->trans_begin();
        $claim=$this->db->query('SELECT c.*,b.name business_name,s.id store_id
            FROM umk_business_claims c JOIN umk_businesses b ON b.id=c.business_id
            LEFT JOIN tko_stores s ON s.business_id=b.id WHERE c.id=? FOR UPDATE',array((int)$id))->row_array();
        if (!$claim || $claim['status']!=='submitted')
        { $this->db->trans_rollback();show_error('Klaim tidak tersedia.',409);return; }
        if ($decision==='approved' && $this->db->query("SELECT id FROM umk_business_claims
            WHERE business_id=? AND status='approved' LIMIT 1",array($claim['business_id']))->row())
        { $this->db->trans_rollback();show_error('Kegiatan sudah memiliki klaim yang disetujui.',409);return; }
        $this->db->query('UPDATE umk_business_claims SET status=?,decision_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',
            array($decision,$note,$user['id'],$id));
        if ($decision==='approved' && $claim['store_id'])
            $this->db->query("INSERT INTO tko_store_managers
                (store_id,user_id,access_role,status,assigned_by)
                VALUES (?,?,'owner','active',?)
                ON DUPLICATE KEY UPDATE access_role='owner',status='active',assigned_by=VALUES(assigned_by)",
                array($claim['store_id'],$claim['user_id'],$user['id']));
        $this->Staff_model->audit($user['id'],'umk.claim_'.$decision,'umk_business_claims',$id,$note);
        $this->Notification_model->publish('business_claim_decided','business-claim:'.$id.':decision',
            $decision==='approved'?'Klaim usaha disetujui':'Klaim usaha ditolak',
            'Pengajuan untuk '.$claim['business_name'].' telah ditinjau.',
            '/pengelola/klaim-usaha',array((int)$claim['user_id']));
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback();show_error('Keputusan gagal disimpan.',500);return; }
        $this->db->trans_commit();
        redirect('pengelola/klaim-usaha?tersimpan=1','location',303);
    }
}
