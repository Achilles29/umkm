<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Members extends CI_Controller
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
        if (!$user || $user['status']!=='active' || !in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        { show_error('Akses database member tidak diizinkan.',403); return NULL; }
        return $user;
    }

    private function search($query,$activity='')
    {
        if ($query!=='') $this->db->group_start()->like('c.display_name',$query)->or_like('c.phone_e164',$query)->group_end();
        if ($activity==='contact') $this->db->where('EXISTS (SELECT 1 FROM mkt_contact_intents i WHERE i.customer_id=c.id)',NULL,FALSE);
        if ($activity==='event') $this->db->where('EXISTS (SELECT 1 FROM evt_registrations r WHERE r.customer_id=c.id)',NULL,FALSE);
    }

    public function index()
    {
        $user=$this->actor(); if (!$user) return;
        $query=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $activity=(string)$this->input->get('activity',TRUE);
        if (!in_array($activity,array('','contact','event'),TRUE)) $activity='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $sort=(string)$this->input->get('sort',TRUE);
        if (!in_array($sort,array('newest','oldest','last_seen'),TRUE)) $sort='newest';

        $this->db->from('crm_customers c');$this->search($query,$activity);
        $total=(int)$this->db->count_all_results();
        $pages=max(1,(int)ceil($total/$per_page));
        $page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $order=$sort==='oldest'?'c.created_at ASC,c.id ASC':($sort==='last_seen'?'c.last_seen_at DESC,c.id DESC':'c.created_at DESC,c.id DESC');
        $this->db->select("c.id,c.display_name,c.phone_e164,c.source,c.created_at,c.last_seen_at,
            (SELECT COUNT(*) FROM mkt_contact_intents i WHERE i.customer_id=c.id) AS contact_count,
            (SELECT COUNT(*) FROM evt_registrations r WHERE r.customer_id=c.id) AS event_count",FALSE);
        $this->db->from('crm_customers c');$this->search($query,$activity);
        $this->db->order_by($order,'',FALSE)->limit($per_page,($page-1)*$per_page);
        $members=$this->db->get()->result_array();
        foreach($members as &$member)
        {
            $phone=$member['phone_e164'];
            $member['phone_masked']=substr($phone,0,5).'***'.substr($phone,-3);
            unset($member['phone_e164']);
        }
        unset($member);
        $stats=array(
            'total'=>(int)$this->db->query('SELECT COUNT(*) n FROM crm_customers')->row('n'),
            'month'=>(int)$this->db->query("SELECT COUNT(*) n FROM crm_customers WHERE created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->row('n'),
            'contact'=>(int)$this->db->query('SELECT COUNT(DISTINCT customer_id) n FROM mkt_contact_intents WHERE customer_id IS NOT NULL')->row('n'),
            'event'=>(int)$this->db->query('SELECT COUNT(DISTINCT customer_id) n FROM evt_registrations WHERE customer_id IS NOT NULL')->row('n'),
        );
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/members',compact('user','query','sort','activity','per_page','total','pages','page','members','stats'));
    }

    public function detail($id=0)
    {
        $user=$this->actor(); if (!$user) return;
        $customer=$this->db->query('SELECT id,display_name,phone_e164,source,created_at,last_seen_at FROM crm_customers WHERE id=? LIMIT 1',array((int)$id))->row_array();
        if (!$customer) { show_404(); return; }
        $phone=$customer['phone_e164'];
        $customer['phone_masked']=substr($phone,0,5).'***'.substr($phone,-3);
        unset($customer['phone_e164']);
        $contact_count=(int)$this->db->query('SELECT COUNT(*) n FROM mkt_contact_intents WHERE customer_id=?',array((int)$id))->row('n');
        $event_count=(int)$this->db->query('SELECT COUNT(*) n FROM evt_registrations WHERE customer_id=?',array((int)$id))->row('n');
        $contacts=$this->db->query('SELECT i.id,i.reference_no,i.status,i.prepared_at,i.opened_at,s.display_name AS store_name FROM mkt_contact_intents i JOIN tko_stores s ON s.id=i.store_id WHERE i.customer_id=? ORDER BY i.prepared_at DESC,i.id DESC LIMIT 10',array((int)$id))->result_array();
        $events=$this->db->query('SELECT r.reference_no,r.status,r.submitted_at,e.id AS event_id,e.title,e.starts_at FROM evt_registrations r JOIN evt_events e ON e.id=r.event_id WHERE r.customer_id=? ORDER BY r.submitted_at DESC,r.id DESC LIMIT 10',array((int)$id))->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/customer',compact('user','customer','contact_count','event_count','contacts','events'));
    }
}
