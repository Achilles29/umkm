<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Events extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
        $this->load->model('Marketplace_model');
        $this->load->model('Notification_model');
    }

    private function manager()
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302); return NULL; }
        $user=$this->Staff_model->by_id($id);
        if (!$user || $user['status']!=='active' || !in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        { show_error('Akses kegiatan tidak diizinkan.',403); return NULL; }
        return $user;
    }

    private function customer()
    {
        $token=(string)$this->input->cookie('umkm_marketplace',TRUE);
        if (!preg_match('/^[a-f0-9]{64}$/',$token)) return NULL;
        $person=$this->Marketplace_model->current_customer(hash('sha256',$token,TRUE));
        if (!$person) return NULL;
        return $this->db->query('SELECT id,display_name,phone_e164 FROM crm_customers WHERE id=? LIMIT 1',array($person['id']))->row_array();
    }

    public function index()
    {
        $total=(int)$this->db->query("SELECT COUNT(*) n FROM evt_events WHERE status='published'")->row('n');
        $pages=max(1,(int)ceil($total/20));$page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $events=$this->db->query("SELECT e.id,e.slug,e.title,e.summary,e.venue,e.starts_at,e.registration_closes_at,e.capacity,d.name AS district_name,
            (SELECT COUNT(*) FROM evt_registrations r WHERE r.event_id=e.id AND r.status IN ('submitted','confirmed','attended')) AS taken
            FROM evt_events e LEFT JOIN ref_districts d ON d.id=e.district_id WHERE e.status='published'
            ORDER BY e.starts_at>=NOW() DESC,e.starts_at ASC LIMIT 20 OFFSET ".(($page-1)*20))->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('events/index',compact('events','total','page','pages'));
    }

    public function detail($slug='')
    {
        $event=$this->db->query("SELECT e.*,d.name AS district_name,
            (SELECT COUNT(*) FROM evt_registrations r WHERE r.event_id=e.id AND r.status IN ('submitted','confirmed','attended')) AS taken
            FROM evt_events e LEFT JOIN ref_districts d ON d.id=e.district_id WHERE e.slug=? AND e.status='published' LIMIT 1",array($slug))->row_array();
        if (!$event) { show_404(); return; }
        $customer=$this->customer();
        $registration=$customer ? $this->db->query('SELECT reference_no,status,submitted_at FROM evt_registrations WHERE event_id=? AND phone_e164=? LIMIT 1',array($event['id'],$customer['phone_e164']))->row_array() : NULL;
        $design=$this->event_design($event['id']);
        $form=$this->db->query("SELECT id FROM evt_form_versions WHERE event_id=? AND status='published' ORDER BY version_no DESC LIMIT 1",array($event['id']))->row_array();
        $fields=$form?$this->db->query('SELECT id,field_key,label,field_type,is_required,options_json FROM evt_form_fields WHERE form_version_id=? ORDER BY sort_order,id',array($form['id']))->result_array():array();
        $open=$this->is_open($event);
        $error=(string)$this->session->flashdata('event_error');
        $old_answers=$this->session->flashdata('event_old_answers')?:array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('events/detail',compact('event','customer','registration','design','fields','open','error','old_answers'));
    }

    private function event_design($event_id)
    {
        $row=$this->db->query("SELECT component_json FROM evt_design_versions WHERE event_id=? AND status='published' ORDER BY version_no DESC LIMIT 1",array($event_id))->row_array();
        $design=$row?json_decode($row['component_json'],TRUE):NULL;
        return is_array($design)?$design:array('theme'=>'ocean','blocks'=>array(array('type'=>'summary'),array('type'=>'facts'),array('type'=>'body')));
    }

    private function is_open($event)
    {
        $now=time();
        return $event['status']==='published' && strtotime($event['starts_at'])>$now
            && (!$event['registration_opens_at'] || strtotime($event['registration_opens_at'])<=$now)
            && (!$event['registration_closes_at'] || strtotime($event['registration_closes_at'])>=$now)
            && (!$event['capacity'] || (int)$event['taken']<(int)$event['capacity']);
    }

    public function register($slug='')
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $customer=$this->customer();
        if (!$customer) { redirect('masuk?next='.rawurlencode('/kegiatan/'.$slug),'location',303); return; }
        $this->db->trans_begin();
        $event=$this->db->query("SELECT e.*, (SELECT COUNT(*) FROM evt_registrations r WHERE r.event_id=e.id AND r.status IN ('submitted','confirmed','attended')) AS taken FROM evt_events e WHERE e.slug=? AND e.status='published' LIMIT 1 FOR UPDATE",array($slug))->row_array();
        if (!$event) { $this->db->trans_rollback(); show_404(); return; }
        $already=$this->db->query('SELECT id FROM evt_registrations WHERE event_id=? AND phone_e164=? LIMIT 1',array($event['id'],$customer['phone_e164']))->row_array();
        if ($already || !$this->is_open($event))
        {
            $this->db->trans_rollback();
            $this->session->set_flashdata('event_error',$already?'Nomor ini sudah terdaftar untuk kegiatan tersebut.':'Pendaftaran kegiatan sedang tidak tersedia atau kuota telah penuh.');
            redirect('kegiatan/'.$slug,'location',303); return;
        }
        $form=$this->db->query("SELECT id FROM evt_form_versions WHERE event_id=? AND status='published' ORDER BY version_no DESC LIMIT 1",array($event['id']))->row_array();
        if (!$form) { $this->db->trans_rollback(); show_error('Formulir kegiatan belum tersedia.',503); return; }
        $fields=$this->db->query('SELECT id,field_key,label,field_type,is_required,options_json FROM evt_form_fields WHERE form_version_id=? ORDER BY sort_order,id',array($form['id']))->result_array();
        $answers=$this->input->post('answers',TRUE);
        if (!is_array($answers)) $answers=array();
        $clean=array();
        foreach($fields as $field)
        {
            $value=isset($answers[$field['field_key']]) && is_scalar($answers[$field['field_key']]) ? trim((string)$answers[$field['field_key']]) : '';
            $type=$field['field_type'];
            $limit=$type==='textarea'?2000:300;
            $options=$type==='select'?json_decode($field['options_json'],TRUE):array();
            if (($field['is_required'] && $value==='') || mb_strlen($value,'UTF-8')>$limit ||
                ($type==='select' && $value!=='' && !in_array($value,is_array($options)?$options:array(),TRUE)) ||
                ($type==='date' && $value!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value) || !checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4)))) ||
                ($type==='number' && $value!=='' && !preg_match('/^-?[0-9]{1,9}(?:\.[0-9]{1,2})?$/',$value)) ||
                ($type==='checkbox' && $value!=='' && $value!=='Ya'))
            {
                $this->db->trans_rollback();
                $this->session->set_flashdata('event_error','Periksa jawaban untuk: '.$field['label']);
                $this->session->set_flashdata('event_old_answers',$answers);
                redirect('kegiatan/'.$slug,'location',303); return;
            }
            if ($value!=='') $clean[$field['id']]=$value;
        }
        $ref='KGT-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
        $this->db->query('INSERT INTO evt_registrations (event_id,form_version_id,customer_id,reference_no,full_name,phone_e164,status) VALUES (?,?,?,?,?,?,?)',
            array($event['id'],$form['id'],$customer['id'],$ref,$customer['display_name'],$customer['phone_e164'],'submitted'));
        $id=(int)$this->db->insert_id();
        foreach($clean as $field_id=>$value)
            $this->db->query('INSERT INTO evt_answers (registration_id,field_id,answer_text) VALUES (?,?,?)',array($id,$field_id,$value));
        $this->Notification_model->publish('event_registration','event-reg:'.$id,'Pendaftaran kegiatan baru',
            $customer['display_name'].' mendaftar kegiatan '.$event['title'].'.','/pengelola/kegiatan/'.$event['id'].'/peserta',$this->Notification_model->admin_ids());
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); show_error('Pendaftaran gagal disimpan.',500); return; }
        $this->db->trans_commit();
        redirect('kegiatan/'.$slug,'location',303);
    }

    public function admin_index()
    {
        $user=$this->manager(); if (!$user) return;
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $status=(string)$this->input->get('status',TRUE);
        if (!in_array($status,array('','draft','published'),TRUE)) $status='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $where=' WHERE 1=1';$params=array();
        if ($search!=='') { $where.=' AND (e.title LIKE ? OR e.venue LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        if ($status!=='') { $where.=' AND e.status=?';$params[]=$status; }
        $total=(int)$this->db->query('SELECT COUNT(*) n FROM evt_events e'.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));$page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $events=$this->db->query("SELECT e.*,d.name AS district_name,(SELECT COUNT(*) FROM evt_registrations r WHERE r.event_id=e.id AND r.status IN ('submitted','confirmed','attended')) AS taken FROM evt_events e LEFT JOIN ref_districts d ON d.id=e.district_id".$where." ORDER BY e.created_at DESC,e.id DESC LIMIT ".$per_page." OFFSET ".(($page-1)*$per_page),$params)->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('events/admin_index',compact('user','events','total','page','pages','search','status','per_page'));
    }

    private function parse_date($value)
    {
        if ($value==='') return NULL;
        $date=DateTime::createFromFormat('!Y-m-d\TH:i',$value);
        return $date && $date->format('Y-m-d\TH:i')===$value ? $date->format('Y-m-d H:i:s') : FALSE;
    }

    public function admin_edit($id=0)
    {
        $user=$this->manager(); if (!$user) return;
        $id=(int)$id;
        $event=$id ? $this->db->query('SELECT * FROM evt_events WHERE id=? LIMIT 1',array($id))->row_array() : NULL;
        if ($id && !$event) { show_404(); return; }
        $districts=$this->db->query('SELECT id,name FROM ref_districts WHERE is_active=1 ORDER BY name')->result_array();
        $error='';
        if ($this->input->method(TRUE)==='POST')
        {
            $title=trim((string)$this->input->post('title',TRUE));
            $summary=trim((string)$this->input->post('summary',TRUE));
            $body=trim((string)$this->input->post('body',TRUE));
            $venue=trim((string)$this->input->post('venue',TRUE));
            $district_id=(int)$this->input->post('district_id',TRUE);
            $starts=$this->parse_date((string)$this->input->post('starts_at',TRUE));
            $ends=$this->parse_date((string)$this->input->post('ends_at',TRUE));
            $opens=$this->parse_date((string)$this->input->post('registration_opens_at',TRUE));
            $closes=$this->parse_date((string)$this->input->post('registration_closes_at',TRUE));
            $capacity_raw=trim((string)$this->input->post('capacity',TRUE));
            $capacity=$capacity_raw===''?NULL:(ctype_digit($capacity_raw)?(int)$capacity_raw:FALSE);
            $status=(string)$this->input->post('status',TRUE);
            $banner_tmp=NULL;$banner_ext=NULL;
            if (isset($_FILES['banner']) && $_FILES['banner']['error']!==UPLOAD_ERR_NO_FILE)
            {
                $file=$_FILES['banner'];
                $image=$file['error']===UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name']) ? @getimagesize($file['tmp_name']) : FALSE;
                $extensions=array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp');
                if (!$image || !isset($extensions[$image['mime']]) || $file['size']<1 || $file['size']>5*1048576 || $image[0]<500 || $image[1]<250 || $image[0]>6000 || $image[1]>6000)
                    $error='Gambar harus JPG, PNG, atau WebP berukuran 500×250 sampai 6000×6000 piksel dan maksimal 5 MB.';
                else { $banner_tmp=$file['tmp_name'];$banner_ext=$extensions[$image['mime']]; }
            }
            $valid_district=!$district_id || in_array($district_id,array_map('intval',array_column($districts,'id')),TRUE);
            if (!$error && (mb_strlen($title,'UTF-8')<5 || mb_strlen($title,'UTF-8')>190 || mb_strlen($summary,'UTF-8')>2000 || mb_strlen($body,'UTF-8')>30000 || mb_strlen($venue,'UTF-8')>190 || !$starts || $ends===FALSE || $opens===FALSE || $closes===FALSE || ($ends && $ends<$starts) || ($closes && $opens && $closes<$opens) || ($closes && $closes>$starts) || $capacity===FALSE || ($capacity!==NULL && $capacity<1) || !$valid_district || !in_array($status,array('draft','published'),TRUE)))
                $error='Periksa judul, tanggal, kuota, wilayah, dan isi kegiatan.';
            if (!$error)
            {
                $this->db->trans_begin();
                if ($event)
                {
                    $this->db->query('SELECT id FROM evt_events WHERE id=? FOR UPDATE',array($id));
                    $taken=(int)$this->db->query("SELECT COUNT(*) n FROM evt_registrations WHERE event_id=? AND status IN ('submitted','confirmed','attended')",array($id))->row('n');
                    if ($capacity!==NULL && $taken>$capacity)
                    {
                        $this->db->trans_rollback();
                        $error='Kuota tidak boleh lebih kecil daripada jumlah peserta yang sudah terdaftar.';
                    }
                }
                if ($error==='')
                {
                $data=array('title'=>$title,'summary'=>$summary?:NULL,'body'=>$body?:NULL,'venue'=>$venue?:NULL,'district_id'=>$district_id?:NULL,'starts_at'=>$starts,'ends_at'=>$ends,'registration_opens_at'=>$opens,'registration_closes_at'=>$closes,'capacity'=>$capacity,'status'=>$status);
                $uploaded_path=NULL;
                if ($banner_tmp)
                {
                    $dir=FCPATH.'assets/events/';
                    if (!is_dir($dir) || !is_writable($dir)) { $this->db->trans_rollback(); $error='Direktori gambar kegiatan belum siap.'; }
                    else
                    {
                        $key='e'.bin2hex(random_bytes(12)).'.'.$banner_ext;
                        if (!move_uploaded_file($banner_tmp,$dir.$key)) { $this->db->trans_rollback(); $error='Gambar kegiatan gagal diunggah.'; }
                        else { $uploaded_path=$dir.$key;chmod($uploaded_path,0644);$data['banner_key']=$key; }
                    }
                }
                if ($error==='')
                {
                if ($event)
                {
                    $this->db->where('id',$id)->update('evt_events',$data);
                }
                else
                {
                    $data['slug']='kegiatan-'.bin2hex(random_bytes(7));$data['created_by']=$user['id'];
                    $this->db->insert('evt_events',$data);$id=(int)$this->db->insert_id();
                    $this->db->insert('evt_form_versions',array('event_id'=>$id,'version_no'=>1,'status'=>'published','created_by'=>$user['id']));
                }
                if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); if ($uploaded_path) @unlink($uploaded_path); $error='Kegiatan gagal disimpan.'; }
                else { $this->db->trans_commit(); $this->Staff_model->audit($user['id'],'event.saved','evt_events',$id); redirect('pengelola/kegiatan','location',303); return; }
                }
                }
            }
            $event=array_merge($event?:array(),array('title'=>$title,'summary'=>$summary,'body'=>$body,'venue'=>$venue,'district_id'=>$district_id,'starts_at'=>$starts?:'','ends_at'=>$ends?:'','registration_opens_at'=>$opens?:'','registration_closes_at'=>$closes?:'','capacity'=>$capacity_raw,'status'=>$status));
        }
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('events/admin_edit',compact('user','event','districts','error'));
    }

    public function admin_participants($id=0)
    {
        $user=$this->manager(); if (!$user) return;
        $event=$this->db->query('SELECT * FROM evt_events WHERE id=? LIMIT 1',array((int)$id))->row_array();
        if (!$event) { show_404(); return; }
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $status=(string)$this->input->get('status',TRUE);
        if (!in_array($status,array('','submitted','confirmed','rejected','attended'),TRUE)) $status='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $where=' WHERE event_id=?';$params=array($event['id']);
        if ($search!=='') { $where.=' AND (full_name LIKE ? OR reference_no LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        if ($status!=='') { $where.=' AND status=?';$params[]=$status; }
        $total=(int)$this->db->query('SELECT COUNT(*) n FROM evt_registrations'.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));$page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $participants=$this->db->query('SELECT id,reference_no,full_name,phone_e164,status,submitted_at FROM evt_registrations'.$where.' ORDER BY submitted_at DESC,id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $answers=array();
        if ($participants)
        {
            $ids=array_map('intval',array_column($participants,'id'));
            $rows=$this->db->query('SELECT a.registration_id,a.answer_text,f.label FROM evt_answers a JOIN evt_form_fields f ON f.id=a.field_id WHERE a.registration_id IN ('.implode(',',$ids).') ORDER BY f.sort_order,f.id')->result_array();
            foreach($rows as $row) $answers[$row['registration_id']][]=$row;
        }
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('events/admin_participants',compact('user','event','participants','answers','total','page','pages','search','status','per_page'));
    }

    public function admin_studio($id=0)
    {
        $user=$this->manager(); if (!$user) return;
        $id=(int)$id;
        $event=$this->db->query('SELECT id,title,slug,status FROM evt_events WHERE id=? LIMIT 1',array($id))->row_array();
        if (!$event) { show_404(); return; }
        $design=$this->event_design($id);
        $form=$this->db->query("SELECT id,version_no FROM evt_form_versions WHERE event_id=? AND status='published' ORDER BY version_no DESC LIMIT 1",array($id))->row_array();
        $fields=$form?$this->db->query('SELECT field_key,label,field_type,is_required,options_json FROM evt_form_fields WHERE form_version_id=? ORDER BY sort_order,id',array($form['id']))->result_array():array();
        foreach($fields as &$field) $field['options']=$field['options_json']?json_decode($field['options_json'],TRUE):array();
        unset($field);
        $error='';
        if ($this->input->method(TRUE)==='POST')
        {
            $raw_design=(string)$this->input->post('design_json',FALSE);
            $raw_fields=(string)$this->input->post('fields_json',FALSE);
            $submitted_design=json_decode($raw_design,TRUE);
            $submitted_fields=json_decode($raw_fields,TRUE);
            $themes=array('ocean','sage','sunset');
            $types=array('summary','facts','body','note','image');
            $field_types=array('text','textarea','select','number','date','checkbox');
            $clean_design=array('theme'=>'ocean','blocks'=>array());$clean_fields=array();$seen=array();$field_keys=array();
            if (strlen($raw_design)>25000 || strlen($raw_fields)>25000 || !is_array($submitted_design) || !is_array($submitted_fields) || !isset($submitted_design['blocks']) || !is_array($submitted_design['blocks']) || count($submitted_design['blocks'])>12 || count($submitted_fields)>15)
                $error='Susunan halaman atau formulir tidak valid.';
            else
            {
                $clean_design['theme']=in_array($submitted_design['theme']??'', $themes, TRUE)?$submitted_design['theme']:'ocean';
                foreach($submitted_design['blocks'] as $block)
                {
                    if (!is_array($block) || !in_array($block['type']??'', $types, TRUE)) { $error='Ada komponen halaman yang tidak dikenal.'; break; }
                    $type=$block['type'];
                    if ($type!=='note' && isset($seen[$type])) { $error='Komponen halaman tidak boleh berulang.'; break; }
                    $seen[$type]=TRUE;
                    if ($type==='note')
                    {
                        $heading=trim((string)($block['heading']??''));$body=trim((string)($block['text']??''));
                        if (mb_strlen($heading,'UTF-8')>120 || mb_strlen($body,'UTF-8')>1200 || $heading==='') { $error='Isi catatan halaman belum valid.'; break; }
                        $clean_design['blocks'][]=array('type'=>'note','heading'=>$heading,'text'=>$body);
                    }
                    else $clean_design['blocks'][]=array('type'=>$type);
                }
                if (!$error && !$clean_design['blocks']) $error='Tambahkan setidaknya satu komponen halaman.';
                if (!$error) foreach($submitted_fields as $index=>$field)
                {
                    if (!is_array($field)) { $error='Kolom formulir tidak valid.'; break; }
                    $key=(string)($field['key']??'');$label=trim((string)($field['label']??''));$type=(string)($field['type']??'');
                    $required=!empty($field['required']);$options=array();
                    if (!preg_match('/^[a-z][a-z0-9_]{2,59}$/',$key) || isset($field_keys[$key]) || mb_strlen($label,'UTF-8')<2 || mb_strlen($label,'UTF-8')>160 || !in_array($type,$field_types,TRUE)) { $error='Nama atau jenis kolom formulir tidak valid.'; break; }
                    $field_keys[$key]=TRUE;
                    if ($type==='select')
                    {
                        $options_raw=$field['options']??array();
                        if (!is_array($options_raw) || count($options_raw)<2 || count($options_raw)>15) { $error='Pilihan formulir harus berisi 2 sampai 15 opsi.'; break; }
                        foreach($options_raw as $option)
                        {
                            if (!is_scalar($option) || mb_strlen(trim((string)$option),'UTF-8')<1 || mb_strlen(trim((string)$option),'UTF-8')>80) { $error='Isi pilihan formulir tidak valid.'; break; }
                            $options[]=trim((string)$option);
                        }
                        if ($error) break;
                        if (count(array_unique($options))!==count($options)) { $error='Pilihan formulir tidak boleh berulang.'; break; }
                    }
                    $clean_fields[]=array('key'=>$key,'label'=>$label,'type'=>$type,'required'=>$required,'options'=>$options);
                }
            }
            if (!$error)
            {
                $this->db->trans_begin();
                $this->db->query('SELECT id FROM evt_events WHERE id=? FOR UPDATE',array($id));
                $design_version=(int)$this->db->query('SELECT COALESCE(MAX(version_no),0)+1 n FROM evt_design_versions WHERE event_id=?',array($id))->row('n');
                $form_version=(int)$this->db->query('SELECT COALESCE(MAX(version_no),0)+1 n FROM evt_form_versions WHERE event_id=?',array($id))->row('n');
                $this->db->insert('evt_design_versions',array('event_id'=>$id,'version_no'=>$design_version,'component_json'=>json_encode($clean_design,JSON_UNESCAPED_UNICODE),'status'=>'published','created_by'=>$user['id']));
                $this->db->insert('evt_form_versions',array('event_id'=>$id,'version_no'=>$form_version,'status'=>'published','created_by'=>$user['id']));
                $form_id=(int)$this->db->insert_id();
                foreach($clean_fields as $index=>$field)
                    $this->db->insert('evt_form_fields',array('form_version_id'=>$form_id,'field_key'=>$field['key'],'label'=>$field['label'],'field_type'=>$field['type'],'is_required'=>$field['required']?1:0,'options_json'=>$field['type']==='select'?json_encode($field['options'],JSON_UNESCAPED_UNICODE):NULL,'sort_order'=>$index+1));
                if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); $error='Studio gagal disimpan.'; }
                else { $this->db->trans_commit(); $this->Staff_model->audit($user['id'],'event.studio.saved','evt_events',$id); redirect('pengelola/kegiatan/'.$id.'/studio','location',303); return; }
            }
            $design=$submitted_design?:$design;
            $fields=$submitted_fields?:$fields;
        }
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('events/admin_studio',compact('user','event','design','fields','error','form'));
    }

    public function admin_status($id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->manager(); if (!$user) return;
        $registration=$this->db->query('SELECT id,event_id,status FROM evt_registrations WHERE id=? LIMIT 1',array((int)$id))->row_array();
        if (!$registration) { show_404(); return; }
        $status=(string)$this->input->post('status',TRUE);
        $allowed=array('submitted'=>array('confirmed','rejected'), 'confirmed'=>array('attended','rejected'), 'rejected'=>array('submitted'), 'attended'=>array());
        $this->db->trans_begin();
        $event=$this->db->query('SELECT capacity FROM evt_events WHERE id=? FOR UPDATE',array($registration['event_id']))->row_array();
        $registration=$this->db->query('SELECT id,event_id,status FROM evt_registrations WHERE id=? FOR UPDATE',array((int)$id))->row_array();
        if (!in_array($status,$allowed[$registration['status']]??array(),TRUE)) { $this->db->trans_rollback(); show_error('Perubahan status tidak diizinkan.',400); return; }
        if ($status==='submitted' && $event['capacity']!==NULL)
        {
            $taken=(int)$this->db->query("SELECT COUNT(*) n FROM evt_registrations WHERE event_id=? AND status IN ('submitted','confirmed','attended')",array($registration['event_id']))->row('n');
            if ($taken>=(int)$event['capacity']) { $this->db->trans_rollback(); show_error('Kuota kegiatan telah penuh.',409); return; }
        }
        $this->db->where('id',$registration['id'])->update('evt_registrations',array('status'=>$status));
        $this->Staff_model->audit($user['id'],'event.registration.'.$status,'evt_registrations',$registration['id']);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback(); show_error('Status peserta gagal disimpan.',500); return; }
        $this->db->trans_commit();
        $page=max(1,(int)$this->input->post('page',TRUE));
        $search=mb_substr(trim((string)$this->input->post('q',TRUE)),0,80,'UTF-8');
        $filter_status=(string)$this->input->post('filter_status',TRUE);
        if (!in_array($filter_status,array('','submitted','confirmed','rejected','attended'),TRUE)) $filter_status='';
        $per_page=(int)$this->input->post('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        redirect('pengelola/kegiatan/'.$registration['event_id'].'/peserta?'.http_build_query(array('q'=>$search,'status'=>$filter_status,'per_page'=>$per_page,'page'=>$page)),'location',303);
    }
}
