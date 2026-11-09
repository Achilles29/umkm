<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Qr_admin extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
    }

    private function actor()
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302); return NULL; }
        $user=$this->Staff_model->by_id($id);
        if (!$user || $user['status']!=='active' ||
            !in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        { show_error('Akses tidak diizinkan.',403); return NULL; }
        return $user;
    }

    public function index()
    {
        $user=$this->actor(); if (!$user) return;
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $where='';$params=array();
        if ($search!=='')
        {
            $where=' WHERE p.code LIKE ? OR p.label LIKE ? OR p.location_name LIKE ?';
            $params=array('%'.$search.'%','%'.$search.'%','%'.$search.'%');
        }
        $total=(int)$this->db->query('SELECT COUNT(*) n FROM mkt_qr_placements p'.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));
        $page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $rows=$this->db->query('SELECT p.id,p.code,p.label,p.placement_kind,p.location_name,p.is_active,p.created_at,
            COALESCE(SUM(s.scan_count),0) scan_count,MAX(s.visit_date) last_scan
            FROM mkt_qr_placements p LEFT JOIN mkt_qr_daily_stats s ON s.placement_id=p.id'.$where.
            ' GROUP BY p.id,p.code,p.label,p.placement_kind,p.location_name,p.is_active,p.created_at
              ORDER BY p.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $this->load->view('staff/qr_admin',compact('user','rows','search','per_page','total','page','pages'));
    }

    public function create()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        $code=strtolower(trim((string)$this->input->post('code',TRUE)));
        if ($code==='') $code='qr-'.bin2hex(random_bytes(5));
        $label=trim((string)$this->input->post('label',TRUE));
        $location=trim((string)$this->input->post('location_name',TRUE));
        $kind=(string)$this->input->post('placement_kind',TRUE);
        if (!preg_match('/^[a-z0-9-]{3,40}$/',$code) || mb_strlen($label)<3 ||
            mb_strlen($label)>160 || mb_strlen($location)>160 ||
            !in_array($kind,array('general','hotel','event','other'),TRUE))
        { show_error('Periksa kode, nama penempatan, jenis, dan lokasi QR.',400); return; }
        if ($this->db->query('SELECT id FROM mkt_qr_placements WHERE code=? LIMIT 1',array($code))->row())
        { show_error('Kode QR sudah digunakan.',409); return; }
        $this->db->trans_begin();
        $this->db->query("INSERT INTO mkt_qr_placements
            (code,label,placement_kind,location_name,destination_path,is_active)
            VALUES (?,?,?,?, '/masuk',1)",array($code,$label,$kind,$location?:NULL));
        $id=(int)$this->db->insert_id();
        $this->Staff_model->audit($user['id'],'mkt.qr_created','mkt_qr_placements',$id);
        if ($this->db->trans_status()===FALSE || !$this->generate($code))
        { $this->db->trans_rollback(); show_error('QR gagal dibuat.',500); return; }
        $this->db->trans_commit();
        redirect('pengelola/qr?dibuat=1','location',303);
    }

    public function toggle($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        $row=$this->db->query('SELECT id,is_active FROM mkt_qr_placements WHERE id=? LIMIT 1',
            array((int)$id))->row_array();
        if (!$row) { show_404(); return; }
        $this->db->trans_begin();
        $this->db->query('UPDATE mkt_qr_placements SET is_active=? WHERE id=?',
            array($row['is_active']?0:1,$id));
        $this->Staff_model->audit($user['id'],'mkt.qr_'.($row['is_active']?'disabled':'enabled'),
            'mkt_qr_placements',$id);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Status QR gagal disimpan.',500); return; }
        $this->db->trans_commit();
        redirect('pengelola/qr?tersimpan=1','location',303);
    }

    private function generate($code)
    {
        $script=dirname(APPPATH).'/scripts/generate_qr.py';
        $process=proc_open(array('/usr/bin/python3',$script,$code),
            array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
        if (!is_resource($process)) return FALSE;
        fclose($pipes[0]);
        stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]);
        return proc_close($process)===0 && is_file(FCPATH.'assets/qr-'.$code.'.svg');
    }
}
