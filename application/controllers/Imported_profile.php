<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Imported_profile extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
        $this->load->model('Notification_model');
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

    private function activities($user_id)
    {
        $links=$this->db->query("SELECT DISTINCT b.id business_id
            FROM umk_business_claims c JOIN umk_businesses b ON b.id=c.business_id
            WHERE c.user_id=? AND c.status='approved'
            UNION
            SELECT COALESCE(source.canonical_business_id,source.id) business_id
            FROM iam_imported_business_accounts a JOIN umk_businesses source ON source.id=a.business_id
            WHERE a.user_id=? AND NOT EXISTS (
                SELECT 1 FROM umk_business_claims other
                WHERE other.business_id=COALESCE(source.canonical_business_id,source.id)
                  AND other.status='approved' AND other.user_id<>a.user_id
            )",array((int)$user_id,(int)$user_id))->result_array();
        if (!$links) return array();
        $base_ids=array_map('intval',array_column($links,'business_id'));
        $people=$this->db->query('SELECT DISTINCT person_id FROM umk_business_owners
            WHERE is_primary=1 AND business_id IN ('.implode(',',$base_ids).')')->result_array();
        $person_ids=array_map('intval',array_column($people,'person_id'));
        $sql='SELECT b.id,b.name,b.description,b.registry_status,
            d.name AS district_name,v.name AS village_name,
            s.id AS store_id,s.status AS store_status
            FROM umk_businesses b
            LEFT JOIN ref_districts d ON d.id=b.district_id
            LEFT JOIN ref_villages v ON v.id=b.village_id
            LEFT JOIN tko_stores s ON s.business_id=b.id
            WHERE b.is_demo=0 AND b.canonical_business_id IS NULL AND (b.id IN ('.implode(',',$base_ids).')';
        if ($person_ids)
            $sql.=' OR EXISTS (SELECT 1 FROM umk_business_owners o
                WHERE o.business_id=b.id AND o.person_id IN ('.implode(',',$person_ids).') AND o.is_primary=1)';
        $sql.=') ORDER BY (b.id IN ('.implode(',',$base_ids).')) DESC,b.id';
        return $this->db->query($sql)->result_array();
    }

    private function business($user_id,$business_id=0)
    {
        $activities=$this->activities($user_id);
        if (!$activities) return NULL;
        if (!$business_id) return $activities[0];
        foreach($activities as $activity)
            if ((int)$activity['id']===(int)$business_id) return $activity;
        return NULL;
    }

    public function index()
    {
        $user=$this->actor(); if (!$user) return;
        $activities=$this->activities($user['id']);
        $selected_id=(int)$this->input->get('usaha',TRUE);
        $business=$this->business($user['id'],$selected_id);
        if ($selected_id && !$business) { show_404(); return; }
        $updates=$business?$this->db->query('SELECT id,proposed_name,proposed_description,status,
            decision_note,created_at,reviewed_at FROM umk_profile_update_requests
            WHERE business_id=? AND user_id=? ORDER BY id DESC LIMIT 10',
            array($business['id'],$user['id']))->result_array():array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/imported_profile',compact('user','business','activities','updates'));
    }

    public function submit()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        $business=$this->business($user['id'],(int)$this->input->post('business_id',TRUE));
        if (!$business) { show_404(); return; }
        $name=trim((string)$this->input->post('name',TRUE));
        $description=trim((string)$this->input->post('description',TRUE));
        if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>190 || mb_strlen($description,'UTF-8')>5000)
        { show_error('Periksa nama dan deskripsi usaha.',400); return; }
        $this->db->trans_begin();
        $link=$this->db->query('SELECT business_id FROM iam_imported_business_accounts
            WHERE user_id=? FOR UPDATE',array($user['id']))->row_array();
        if (!$link || !$this->business($user['id'],$business['id']))
        { $this->db->trans_rollback(); show_error('Profil usaha tidak tersedia.',403); return; }
        $pending=$this->db->query("SELECT id FROM umk_profile_update_requests
            WHERE business_id=? AND status='submitted' LIMIT 1",array($business['id']))->row();
        if ($pending)
        { $this->db->trans_rollback(); show_error('Usulan sebelumnya masih menunggu peninjauan.',409); return; }
        $this->db->query("INSERT INTO umk_profile_update_requests
            (business_id,user_id,proposed_name,proposed_description,status)
            VALUES (?,?,?,?,'submitted')",array($business['id'],$user['id'],$name,$description));
        $id=(int)$this->db->insert_id();
        $this->Staff_model->audit($user['id'],'umk.profile_update_submitted',
            'umk_profile_update_requests',$id);
        $this->Notification_model->publish('profile_update','profile-update:'.$id,
            'Pembaruan profil usaha diajukan',$business['name'].' mengajukan pembaruan profil.',
            '/pengelola/registri/'.$business['id'],$this->Notification_model->admin_ids());
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Usulan gagal disimpan.',500); return; }
        $this->db->trans_commit();
        redirect('pengelola/usaha-saya?usaha='.$business['id'].'&terkirim=1','location',303);
    }

    public function password()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        $has_business=(bool)$this->business($user['id']);
        $current=(string)$this->input->post('current_password',FALSE);
        $next=(string)$this->input->post('new_password',FALSE);
        $confirm=(string)$this->input->post('confirm_password',FALSE);
        if (strlen($next)<10 || strlen($next)>72 || $next!==$confirm)
        { show_error('Kata sandi baru harus 10–72 karakter dan konfirmasinya sama.',400); return; }
        $row=$this->db->query('SELECT password_hash FROM iam_users WHERE id=? LIMIT 1',array($user['id']))->row_array();
        if (!$row || !password_verify($current,$row['password_hash']))
        { show_error('Kata sandi saat ini tidak sesuai.',400); return; }
        $hash=password_hash($next,PASSWORD_BCRYPT);
        $this->db->trans_begin();
        $this->db->query('UPDATE iam_users SET password_hash=?,auth_epoch=auth_epoch+1 WHERE id=?',array($hash,$user['id']));
        $this->Staff_model->audit($user['id'],'iam.password_changed','iam_users',$user['id']);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Kata sandi gagal diperbarui.',500); return; }
        $this->db->trans_commit();
        $epoch=(int)$this->db->query('SELECT auth_epoch FROM iam_users WHERE id=?',array($user['id']))->row('auth_epoch');
        $this->session->set_userdata('staff_auth_epoch',$epoch);
        redirect(($has_business?'pengelola/usaha-saya':'pengelola/klaim-usaha').'?kata_sandi=1','location',303);
    }

    public function decision($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(TRUE); if (!$user) return;
        $decision=(string)$this->input->post('decision',TRUE);
        $note=trim((string)$this->input->post('decision_note',TRUE));
        if (!in_array($decision,array('approved','rejected'),TRUE) ||
            mb_strlen($note,'UTF-8')>500 || ($decision==='rejected' && mb_strlen($note,'UTF-8')<10))
        { show_error('Keputusan atau catatan tidak valid.',400); return; }
        $this->db->trans_begin();
        $request=$this->db->query('SELECT id,business_id,user_id,proposed_name,proposed_description,status
            FROM umk_profile_update_requests WHERE id=? FOR UPDATE',array((int)$id))->row_array();
        if (!$request || $request['status']!=='submitted')
        { $this->db->trans_rollback(); show_error('Usulan tidak ditemukan atau sudah diputuskan.',404); return; }
        if ($decision==='approved')
            $this->db->query('UPDATE umk_businesses SET name=?,description=? WHERE id=?',
                array($request['proposed_name'],$request['proposed_description'],$request['business_id']));
        $this->db->query('UPDATE umk_profile_update_requests SET status=?,decision_note=?,
            reviewed_by=?,reviewed_at=NOW() WHERE id=?',
            array($decision,$note?:NULL,$user['id'],$request['id']));
        $this->Staff_model->audit($user['id'],'umk.profile_update_'.$decision,
            'umk_profile_update_requests',$request['id']);
        $this->Notification_model->publish('profile_update_decided','profile-update:'.$id.':decision',
            $decision==='approved'?'Pembaruan profil disetujui':'Pembaruan profil ditolak',
            'Usulan pembaruan profil usaha Anda telah ditinjau.','/pengelola/usaha-saya',
            array((int)$request['user_id']));
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Keputusan gagal disimpan.',500); return; }
        $this->db->trans_commit();
        redirect('pengelola/registri/'.$request['business_id'].'?pembaruan=1','location',303);
    }
}
