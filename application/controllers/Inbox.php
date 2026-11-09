<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inbox extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
        $this->load->model('Notification_model');
    }

    private function actor()
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302); return NULL; }
        $user=$this->Staff_model->by_id($id);
        if (!$user || $user['status']!=='active') { show_error('Akses tidak diizinkan.',403); return NULL; }
        return $user;
    }

    public function index()
    {
        $user=$this->actor(); if (!$user) return;
        $filter=(string)$this->input->get('filter',TRUE);
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        if ($filter!=='' && $filter!=='unread') { show_404(); return; }
        $unread=$filter==='unread';
        $count=$this->Notification_model->count_items($user['id'],$unread,$search);
        $unread_count=$this->Notification_model->unread_count($user['id']);
        $page=max(1,(int)$this->input->get('page',TRUE));$pages=max(1,(int)ceil($count/$per_page));$page=min($page,$pages);
        $items=$this->Notification_model->items($user['id'],$unread,$per_page,($page-1)*$per_page,$search);
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/inbox',compact('user','filter','search','per_page','count','unread_count','page','pages','items'));
    }

    public function open_item($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        $path=$this->Notification_model->read_item($user['id'],(int)$id);
        if (!$path) { show_404(); return; }
        if (!preg_match('/^\/pengelola(?:\/[a-z0-9_-]+)*$/',$path)) { show_error('Tujuan pesan tidak valid.',400); return; }
        redirect(ltrim($path,'/'),'location',303);
    }

    public function read_all()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(); if (!$user) return;
        $this->Notification_model->read_all($user['id']);
        redirect('pengelola/inbox','location',303);
    }

    public function contact($id=0)
    {
        $user=$this->actor(); if (!$user) return;
        $intent=$this->db->query('SELECT i.id,i.reference_no,i.contact_mode,i.status,i.prepared_at,i.opened_at,i.store_id,
            s.display_name AS store_name,s.slug AS store_slug,c.display_name AS customer_name,b.district_id
            FROM mkt_contact_intents i JOIN tko_stores s ON s.id=i.store_id
            JOIN umk_businesses b ON b.id=s.business_id
            LEFT JOIN crm_customers c ON c.id=i.customer_id WHERE i.id=? LIMIT 1',array((int)$id))->row_array();
        if (!$intent) { show_404(); return; }
        $allowed=in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE);
        if (!$allowed && $user['role_code']==='umkm')
            $allowed=(bool)$this->db->query("SELECT 1 FROM tko_store_managers WHERE store_id=? AND user_id=? AND status='active' LIMIT 1",array($intent['store_id'],$user['id']))->row();
        if (!$allowed && $user['role_code']==='admin_kecamatan')
            $allowed=(bool)$this->db->query("SELECT 1 FROM iam_district_roles dr JOIN iam_roles r ON r.id=dr.role_id WHERE dr.user_id=? AND dr.district_id=? AND r.code='admin_kecamatan' LIMIT 1",array($user['id'],$intent['district_id']))->row();
        if (!$allowed) { show_error('Akses minat pelanggan tidak diizinkan.',403); return; }
        $items=$this->db->query('SELECT product_name,variant_label,quantity,price_note FROM mkt_contact_items WHERE intent_id=? ORDER BY line_no',array($intent['id']))->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/contact_detail',compact('user','intent','items'));
    }
}
