<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Location_requests extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();$this->load->library('session');
        $this->load->model('Staff_model');$this->load->model('Notification_model');
    }

    private function actor($admin=FALSE)
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302);return NULL; }
        $user=$this->Staff_model->by_id($id);
        $roles=$admin?array('superadmin','admin_dinas'):array('umkm');
        if (!$user || $user['status']!=='active' || !in_array($user['role_code'],$roles,TRUE))
        { show_error('Akses tidak diizinkan.',403);return NULL; }
        return $user;
    }

    private function owned_store($user,$store_id)
    {
        return $this->db->query("SELECT s.id,s.business_id,s.display_name,s.status
            FROM tko_stores s JOIN tko_store_managers m ON m.store_id=s.id
            WHERE s.id=? AND m.user_id=? AND m.status='active'
            AND (m.access_role='editor' OR NOT EXISTS (
                SELECT 1 FROM iam_imported_business_accounts ia
                JOIN umk_businesses imported ON imported.id=ia.business_id
                WHERE ia.user_id=m.user_id AND COALESCE(imported.canonical_business_id,imported.id)=s.business_id
            ) OR EXISTS (
                SELECT 1 FROM umk_business_claims c WHERE c.business_id=s.business_id
                  AND c.user_id=m.user_id AND c.status='approved'
            )) LIMIT 1",array((int)$store_id,$user['id']))->row_array();
    }

    public function index()
    {
        $user=$this->Staff_model->by_id((int)$this->session->userdata('staff_user_id'));
        if (!$user || $user['status']!=='active')
        { redirect('pengelola/masuk','location',302);return; }
        if (in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        {
            $status=(string)$this->input->get('status',TRUE);
            if (!in_array($status,array('pending','approved','rejected'),TRUE)) $status='pending';
            $per_page=(int)$this->input->get('per_page',TRUE);
            if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
            $page=max(1,(int)$this->input->get('page',TRUE));
            $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
            $from=' FROM gis_location_requests r JOIN tko_stores s ON s.business_id=r.business_id
                JOIN iam_users u ON u.id=r.user_id';
            $where=' WHERE r.status=?';$params=array($status);
            if ($search!=='') { $where.=' AND (s.display_name LIKE ? OR u.full_name LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
            $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
            $pages=max(1,(int)ceil($total/$per_page));$page=min($page,$pages);
            $rows=$this->db->query('SELECT r.*,s.id store_id,s.display_name store_name,u.full_name applicant_name
                '.$from.$where.' ORDER BY r.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
            $this->load->view('staff/location_requests_admin',compact('user','status','search','per_page','page','pages','total','rows'));
            return;
        }
        if ($user['role_code']!=='umkm') { show_error('Akses tidak diizinkan.',403);return; }
        $stores=$this->db->query("SELECT s.id,s.business_id,s.display_name,s.status,
            gl.visibility,gl.checked_at,
            (SELECT r.status FROM gis_location_requests r WHERE r.business_id=s.business_id ORDER BY r.id DESC LIMIT 1) request_status
            FROM tko_stores s JOIN tko_store_managers m ON m.store_id=s.id
            LEFT JOIN gis_business_locations gl ON gl.business_id=s.business_id
            WHERE m.user_id=? AND m.status='active'
            AND (m.access_role='editor' OR NOT EXISTS (
                SELECT 1 FROM iam_imported_business_accounts ia
                JOIN umk_businesses imported ON imported.id=ia.business_id
                WHERE ia.user_id=m.user_id AND COALESCE(imported.canonical_business_id,imported.id)=s.business_id
            ) OR EXISTS (
                SELECT 1 FROM umk_business_claims c WHERE c.business_id=s.business_id
                  AND c.user_id=m.user_id AND c.status='approved'
            )) ORDER BY s.display_name",array($user['id']))->result_array();
        $this->load->view('staff/location_requests_mine',compact('user','stores'));
    }

    public function submit()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404();return; }
        $user=$this->actor();if (!$user) return;
        $store=$this->owned_store($user,(int)$this->input->post('store_id',TRUE));
        if (!$store || $store['status']!=='active') { show_error('Toko tidak tersedia untuk pengajuan lokasi.',403);return; }
        $lat=trim((string)$this->input->post('latitude',TRUE));
        $lng=trim((string)$this->input->post('longitude',TRUE));
        $mode=(string)$this->input->post('display_mode',TRUE);
        $note=trim((string)$this->input->post('source_note',TRUE));
        if (!is_numeric($lat) || !is_numeric($lng) || (float)$lat < -7.5 || (float)$lat > -6.0 ||
            (float)$lng < 110.5 || (float)$lng > 112.5 ||
            !in_array($mode,array('point','area','hidden'),TRUE) ||
            mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500 ||
            ($mode==='point' && !$this->input->post('consent_confirmed')))
        { show_error('Periksa koordinat, pilihan tampilan, catatan sumber, dan persetujuan titik persis.',400);return; }
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM umk_businesses WHERE id=? FOR UPDATE',array($store['business_id']));
        if ($this->db->query("SELECT id FROM gis_location_requests WHERE business_id=? AND status='pending' LIMIT 1",
            array($store['business_id']))->row())
        { $this->db->trans_rollback();show_error('Pengajuan lokasi sebelumnya masih menunggu.',409);return; }
        $this->db->query("INSERT INTO gis_location_requests
            (business_id,user_id,latitude,longitude,display_mode,source_note,consent_at,status)
            VALUES (?,?,?,?,?,?,IF(?='point',NOW(),NULL),'pending')",
            array($store['business_id'],$user['id'],(float)$lat,(float)$lng,$mode,$note,$mode));
        $id=(int)$this->db->insert_id();
        $this->Staff_model->audit($user['id'],'gis.location_requested','gis_location_requests',$id);
        $this->Notification_model->publish('location_request','location-request:'.$id,
            'Lokasi toko diajukan',$store['display_name'].' mengajukan lokasi untuk ditinjau.',
            '/pengelola/pengajuan-lokasi',$this->Notification_model->admin_ids());
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback();show_error('Lokasi gagal diajukan.',500);return; }
        $this->db->trans_commit();
        redirect('pengelola/pengajuan-lokasi?terkirim=1','location',303);
    }

    public function decide($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404();return; }
        $user=$this->actor(TRUE);if (!$user) return;
        $decision=(string)$this->input->post('decision',TRUE);
        $note=trim((string)$this->input->post('decision_note',TRUE));
        if (!in_array($decision,array('approved','rejected'),TRUE) ||
            mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500)
        { show_error('Isi keputusan dan alasan minimal 20 karakter.',400);return; }
        if ($decision==='approved' && !$this->input->post('location_checked'))
        { show_error('Tandai bahwa koordinat dan pilihan publikasi sudah diperiksa.',400);return; }
        $this->db->trans_begin();
        $row=$this->db->query('SELECT r.*,s.id store_id,s.status store_status,b.is_demo
            FROM gis_location_requests r JOIN tko_stores s ON s.business_id=r.business_id
            JOIN umk_businesses b ON b.id=r.business_id WHERE r.id=? FOR UPDATE',array((int)$id))->row_array();
        if (!$row || $row['status']!=='pending')
        { $this->db->trans_rollback();show_error('Pengajuan tidak tersedia.',409);return; }
        if ($decision==='approved' && ($row['store_status']!=='active' || $row['is_demo']))
        { $this->db->trans_rollback();show_error('Toko belum memenuhi syarat lokasi publik.',409);return; }
        $this->db->query('UPDATE gis_location_requests SET status=?,decision_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',
            array($decision,$note,$user['id'],$id));
        if ($decision==='approved')
        {
            $point=$row['display_mode']==='point';
            $this->db->query("INSERT INTO gis_business_locations
                (business_id,latitude,longitude,public_latitude,public_longitude,visibility,
                 consent_at,consent_reference,checked_at,updated_by)
                VALUES (?,?,?,?,?,?,?, ?,NOW(),?)
                ON DUPLICATE KEY UPDATE latitude=VALUES(latitude),longitude=VALUES(longitude),
                 public_latitude=VALUES(public_latitude),public_longitude=VALUES(public_longitude),
                 visibility=VALUES(visibility),consent_at=VALUES(consent_at),
                 consent_reference=VALUES(consent_reference),checked_at=VALUES(checked_at),
                 updated_by=VALUES(updated_by)",
                array($row['business_id'],$row['latitude'],$row['longitude'],
                    $point?$row['latitude']:NULL,$point?$row['longitude']:NULL,
                    $point?'public':'hidden',$point?$row['consent_at']:NULL,
                    $point?'Pengajuan lokasi #'.$id:NULL,$user['id']));
        }
        $this->Staff_model->audit($user['id'],'gis.location_'.$decision,'gis_location_requests',$id,$note);
        $this->Notification_model->publish('location_decided','location-request:'.$id.':decision',
            $decision==='approved'?'Lokasi disetujui':'Lokasi perlu diperbaiki',
            'Pengajuan lokasi toko telah ditinjau.','/pengelola/pengajuan-lokasi',array((int)$row['user_id']));
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback();show_error('Keputusan gagal disimpan.',500);return; }
        $this->db->trans_commit();
        redirect('pengelola/pengajuan-lokasi?tersimpan=1','location',303);
    }
}
