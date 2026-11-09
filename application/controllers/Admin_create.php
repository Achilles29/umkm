<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_create extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
        $this->load->helper('registry_pii');
        $this->load->helper('staff_scope');
    }

    private function actor()
    {
        $user=$this->Staff_model->by_id((int)$this->session->userdata('staff_user_id'));
        if (!$user) { redirect('pengelola/masuk','location',302); return NULL; }
        if ($user['status']!=='active' || !in_array($user['role_code'],array('superadmin','admin_dinas','admin_kecamatan'),TRUE))
        { show_error('Akses tidak diizinkan.',403); return NULL; }
        return $user;
    }

    private function phone($raw)
    {
        $value=preg_replace('/[\s().-]/','',trim($raw));
        if ($value==='') return NULL;
        if (strpos($value,'0')===0) $value='+62'.substr($value,1);
        elseif (strpos($value,'62')===0) $value='+'.$value;
        if (!preg_match('/^\+62\d{8,13}$/',$value)) throw new InvalidArgumentException('Nomor telepon Indonesia tidak valid.');
        return $value;
    }

    public function person()
    {
        $user=$this->actor(); if (!$user) return;
        $scope=staff_scope_districts($this->db,$user);
        $districts=$this->db->query('SELECT id,name FROM ref_districts WHERE is_active=1'.
            ($scope===NULL?'':($scope?' AND id IN ('.implode(',',array_map('intval',$scope)).')':' AND 1=0')).' ORDER BY name')->result_array();
        $error='';
        if ($this->input->method(TRUE)==='POST')
        {
            $started=FALSE;
            try
            {
                $name=trim((string)$this->input->post('full_name',TRUE));
                $nik=trim((string)$this->input->post('nik',TRUE));
                $note=trim((string)$this->input->post('source_note',TRUE));
                $registration_district=(int)$this->input->post('registration_district_id',TRUE);
                if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>160 ||
                    mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500 ||
                    ($nik!=='' && !preg_match('/^\d{16}$/',$nik)))
                    throw new InvalidArgumentException('Periksa nama, NIK 16 angka bila tersedia, dan sumber pencatatan minimal 20 karakter.');
                $phone=$this->phone((string)$this->input->post('phone',TRUE));
                if (($scope!==NULL && !in_array($registration_district,$scope,TRUE)) ||
                    ($registration_district && !$this->db->query('SELECT 1 FROM ref_districts WHERE id=? AND is_active=1',array($registration_district))->row()))
                    throw new InvalidArgumentException('Pilih kecamatan sesuai wilayah tugas.');
                $fingerprint=NULL;$cipher=NULL;
                if ($nik!=='')
                {
                    $secret=@file_get_contents(dirname(APPPATH).'/.secrets/nik_hmac_key');
                    if (strlen((string)$secret)!==32) throw new RuntimeException('Kunci identitas belum tersedia.');
                    $fingerprint=hash_hmac('sha256',"nik\x1f".$nik,$secret,TRUE);
                    if ($this->db->query('SELECT id FROM umk_people WHERE nik_fingerprint=?',array($fingerprint))->row())
                        throw new InvalidArgumentException('NIK sudah tercatat. Gunakan profil pelaku yang ada.');
                    $cipher=registry_pii_encrypt($nik);
                }
                $this->db->trans_begin();$started=TRUE;
                $this->db->query('INSERT INTO umk_people (full_name,phone_e164,nik_ciphertext,nik_fingerprint,registration_district_id) VALUES (?,?,?,?,?)',
                    array($name,$phone,$cipher,$fingerprint,$registration_district?:NULL));
                $id=(int)$this->db->insert_id();
                $this->Staff_model->audit($user['id'],'umk.person_created','umk_people',$id,$note);
                if ($this->db->trans_status()===FALSE) throw new RuntimeException('Pelaku gagal disimpan.');
                $this->db->trans_commit();$started=FALSE;
                redirect('pengelola/pelaku/'.$id.'?dibuat=1','location',303);return;
            }
            catch(Exception $e) { if ($started) $this->db->trans_rollback();$error=$e->getMessage(); }
        }
        $this->load->view('staff/person_create',compact('user','error','districts'));
    }

    public function edit_person($person_id=0)
    {
        $user=$this->actor(); if (!$user) return;
        if (!staff_scope_person($this->db,$user,$person_id,TRUE)) { show_error('Identitas pelaku di luar kewenangan Anda.',403);return; }
        $person=$this->db->query('SELECT p.id,p.full_name,p.phone_e164,p.nik_fingerprint,d.name district_name
            FROM umk_people p LEFT JOIN ref_districts d ON d.id=p.registration_district_id WHERE p.id=?',array((int)$person_id))->row_array();
        if (!$person) { show_404();return; }
        $error='';
        if ($this->input->method(TRUE)==='POST')
        {
            $started=FALSE;
            try
            {
                $name=trim((string)$this->input->post('full_name',TRUE));
                $nik=trim((string)$this->input->post('nik_new',TRUE));
                $note=trim((string)$this->input->post('change_note',TRUE));
                if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>160 ||
                    mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500 ||
                    ($nik!=='' && !preg_match('/^\d{16}$/',$nik)))
                    throw new InvalidArgumentException('Periksa nama, NIK baru bila diisi, dan dasar perubahan minimal 20 karakter.');
                $phone=$this->phone((string)$this->input->post('phone',TRUE));
                $fingerprint=NULL;$cipher=NULL;
                if ($nik!=='')
                {
                    $secret=@file_get_contents(dirname(APPPATH).'/.secrets/nik_hmac_key');
                    if (strlen((string)$secret)!==32) throw new RuntimeException('Kunci identitas belum tersedia.');
                    $fingerprint=hash_hmac('sha256',"nik\x1f".$nik,$secret,TRUE);
                    if ($this->db->query('SELECT id FROM umk_people WHERE nik_fingerprint=? AND id<>?',array($fingerprint,(int)$person_id))->row())
                        throw new InvalidArgumentException('NIK sudah tercatat pada pelaku lain.');
                    $cipher=registry_pii_encrypt($nik);
                }
                $this->db->trans_begin();$started=TRUE;
                if (!staff_scope_person($this->db,$user,$person_id,TRUE)) throw new InvalidArgumentException('Cakupan pelaku berubah. Muat ulang halaman.');
                $this->db->query('UPDATE umk_people SET full_name=?,phone_e164=?,
                    nik_ciphertext=IF(? IS NULL,nik_ciphertext,?),nik_fingerprint=IF(? IS NULL,nik_fingerprint,?) WHERE id=?',
                    array($name,$phone,$cipher,$cipher,$fingerprint,$fingerprint,(int)$person_id));
                $this->db->query('UPDATE iam_users u JOIN iam_imported_business_accounts a ON a.user_id=u.id
                    JOIN umk_businesses source ON source.id=a.business_id
                    JOIN umk_business_owners o ON o.business_id=COALESCE(source.canonical_business_id,source.id) AND o.is_primary=1
                    SET u.full_name=? WHERE o.person_id=?',array($name,(int)$person_id));
                $this->Staff_model->audit($user['id'],'umk.person_updated','umk_people',(int)$person_id,$note);
                if ($this->db->trans_status()===FALSE) throw new RuntimeException('Identitas pelaku gagal disimpan.');
                $this->db->trans_commit();$started=FALSE;
                redirect('pengelola/pelaku/'.(int)$person_id.'?tersimpan=1','location',303);return;
            }
            catch(Exception $e) { if ($started) $this->db->trans_rollback();$error=$e->getMessage(); }
        }
        $this->load->view('staff/person_edit',compact('user','person','error'));
    }

    public function business()
    {
        $user=$this->actor(); if (!$user) return;
        $scope=staff_scope_districts($this->db,$user);
        $error='';
        $person_id=(int)$this->input->get_post('person_id',TRUE);
        $person=$person_id?$this->db->query('SELECT id,full_name FROM umk_people WHERE id=?',array($person_id))->row_array():NULL;
        if ($person_id && (!$person || !staff_scope_person($this->db,$user,$person_id))) { show_404(); return; }
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $matches=array();
        if ($search!=='')
        {
            $scope_where='';
            if ($scope!==NULL)
            {
                $ids=$scope?implode(',',array_map('intval',$scope)):'0';
                $scope_where=' AND ((p.registration_district_id IN ('.$ids.') AND NOT EXISTS (
                    SELECT 1 FROM umk_business_owners existing JOIN umk_businesses eb ON eb.id=existing.business_id
                    WHERE existing.person_id=p.id AND existing.is_primary=1 AND eb.is_demo=0 AND eb.canonical_business_id IS NULL
                )) OR EXISTS (
                    SELECT 1 FROM umk_business_owners own JOIN umk_businesses b ON b.id=own.business_id
                    WHERE own.person_id=p.id AND own.is_primary=1 AND b.is_demo=0
                      AND b.canonical_business_id IS NULL AND b.district_id IN ('.$ids.')))';
            }
            $activity_count=$scope===NULL?'COUNT(DISTINCT o.business_id)'
                :'COUNT(DISTINCT IF(ob.district_id IN ('.$ids.') AND ob.is_demo=0 AND ob.canonical_business_id IS NULL,o.business_id,NULL))';
            $matches=$this->db->query('SELECT p.id,p.full_name,'.$activity_count.' activity_count
                FROM umk_people p LEFT JOIN umk_business_owners o ON o.person_id=p.id
                LEFT JOIN umk_businesses ob ON ob.id=o.business_id
                WHERE p.full_name LIKE ?'.$scope_where.' GROUP BY p.id ORDER BY p.full_name,p.id LIMIT 30',array('%'.$search.'%'))->result_array();
        }
        $districts=$this->db->query('SELECT id,name FROM ref_districts WHERE is_active=1'.
            ($scope===NULL?'':($scope?' AND id IN ('.implode(',',array_map('intval',$scope)).')':' AND 1=0')).' ORDER BY name')->result_array();
        $villages=$this->db->query('SELECT id,district_id,name FROM ref_villages WHERE is_active=1'.
            ($scope===NULL?'':($scope?' AND district_id IN ('.implode(',',array_map('intval',$scope)).')':' AND 1=0')).' ORDER BY name')->result_array();
        $scales=$this->db->query('SELECT id,name FROM ref_business_scales ORDER BY sort_order,id')->result_array();
        if ($this->input->method(TRUE)==='POST')
        {
            $started=FALSE;
            try
            {
                $name=trim((string)$this->input->post('name',TRUE));
                $address=trim((string)$this->input->post('address',TRUE));
                $note=trim((string)$this->input->post('source_note',TRUE));
                $district=(int)$this->input->post('district_id',TRUE);
                $village=(int)$this->input->post('village_id',TRUE);
                $scale=(int)$this->input->post('scale_id',TRUE);
                if (!$person || mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>190 ||
                    mb_strlen($address,'UTF-8')>1000 || mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500)
                    throw new InvalidArgumentException('Pilih pelaku terdaftar dan periksa nama, alamat, serta sumber pencatatan minimal 20 karakter.');
                if (!$this->db->query('SELECT 1 FROM ref_districts WHERE id=? AND is_active=1',array($district))->row())
                    throw new InvalidArgumentException('Pilih kecamatan yang valid.');
                if (!staff_scope_has_district($scope,$district))
                    throw new InvalidArgumentException('Kecamatan di luar wilayah tugas Anda.');
                if ($village && !$this->db->query('SELECT 1 FROM ref_villages WHERE id=? AND district_id=? AND is_active=1',array($village,$district))->row())
                    throw new InvalidArgumentException('Desa harus sesuai kecamatan.');
                if ($scale && !$this->db->query('SELECT 1 FROM ref_business_scales WHERE id=?',array($scale))->row())
                    throw new InvalidArgumentException('Skala usaha tidak valid.');
                $contact_phone=$this->phone((string)$this->input->post('contact_phone',TRUE));
                $address_cipher=registry_pii_encrypt($address);
                $this->db->trans_begin();$started=TRUE;
                if (!$this->db->query('SELECT id FROM umk_people WHERE id=? FOR UPDATE',array($person_id))->row())
                    throw new InvalidArgumentException('Pelaku tidak ditemukan.');
                if (!staff_scope_person($this->db,$user,$person_id))
                    throw new InvalidArgumentException('Pelaku di luar wilayah tugas Anda.');
                $this->db->query("INSERT INTO umk_businesses (name,district_id,village_id,scale_id,address_ciphertext,contact_phone_e164,registry_status,data_source)
                    VALUES (?,?,?,?,?,?,'draft','manual')",
                    array($name,$district,$village?:NULL,$scale?:NULL,$address_cipher,$contact_phone));
                $business_id=(int)$this->db->insert_id();
                $this->db->query("INSERT INTO umk_business_owners (business_id,person_id,relationship,is_primary) VALUES (?,?,'owner',1)",array($business_id,$person_id));
                $this->Staff_model->audit($user['id'],'umk.business_created','umk_businesses',$business_id,$note);
                if ($this->db->trans_status()===FALSE) throw new RuntimeException('Data pelaku dan kegiatan gagal disimpan.');
                $this->db->trans_commit();$started=FALSE;
                redirect('pengelola/registri/'.$business_id.'?tab=profil&tersimpan=1','location',303);return;
            }
            catch(Exception $e)
            {
                if ($started) $this->db->trans_rollback();
                $error=$e->getMessage();
            }
        }
        $this->load->view('staff/business_create',compact('user','person','person_id','search','matches','districts','villages','scales','error'));
    }

    public function store()
    {
        $user=$this->actor(); if (!$user) return;
        $scope=staff_scope_districts($this->db,$user);
        $error='';
        $person_id=(int)$this->input->get('person_id',TRUE);
        $person=$person_id?$this->db->query('SELECT id,full_name FROM umk_people WHERE id=?',array($person_id))->row_array():NULL;
        if ($person_id && (!$person || !staff_scope_person($this->db,$user,$person_id))) { show_404();return; }
        $business_id=(int)$this->input->get_post('business_id',TRUE);
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $business=$business_id?$this->db->query('SELECT b.id,b.name,b.district_id,s.id store_id,o.person_id,p.full_name owner_name FROM umk_businesses b
            LEFT JOIN tko_stores s ON s.business_id=b.id
            LEFT JOIN umk_business_owners o ON o.business_id=b.id AND o.is_primary=1
            LEFT JOIN umk_people p ON p.id=o.person_id
            WHERE b.id=? AND b.is_demo=0 AND b.canonical_business_id IS NULL',array($business_id))->row_array():NULL;
        if ($business_id && (!$business || $business['store_id'] ||
            !staff_scope_has_district($scope,$business['district_id']) ||
            ($person_id && (int)$business['person_id']!==$person_id)))
        { show_404(); return; }
        $matches=array();
        if ($person || $search!=='')
        {
            $where=$person?' AND o.person_id=?':' AND b.name LIKE ?';
            if ($scope!==NULL) $where.=$scope?' AND b.district_id IN ('.implode(',',array_map('intval',$scope)).')':' AND 1=0';
            $value=$person?$person_id:'%'.$search.'%';
            $matches=$this->db->query('SELECT b.id,b.name,d.name district_name,p.full_name owner_name FROM umk_businesses b
                JOIN umk_business_owners o ON o.business_id=b.id AND o.is_primary=1
                JOIN umk_people p ON p.id=o.person_id
                LEFT JOIN ref_districts d ON d.id=b.district_id LEFT JOIN tko_stores s ON s.business_id=b.id
                WHERE b.is_demo=0 AND b.canonical_business_id IS NULL AND s.id IS NULL'.$where.' ORDER BY b.name,b.id LIMIT 30',array($value))->result_array();
        }
        $categories=$this->db->query("SELECT c.id,c.name,p.name parent_name FROM ref_categories c LEFT JOIN ref_categories p ON p.id=c.parent_id
            WHERE c.category_type='store' AND c.is_active=1 AND NOT EXISTS (SELECT 1 FROM ref_categories child WHERE child.parent_id=c.id)
            ORDER BY p.sort_order,p.name,c.sort_order,c.name")->result_array();
        if ($this->input->method(TRUE)==='POST')
        {
            $started=FALSE;
            try
            {
                if (!$business || !$business['person_id']) throw new InvalidArgumentException('Pilih kegiatan usaha yang sudah terhubung ke pelaku dan belum mempunyai toko.');
                $name=trim((string)$this->input->post('display_name',TRUE));
                $description=trim((string)$this->input->post('description',TRUE));
                $contact=trim((string)$this->input->post('contact_label',TRUE));
                $category_id=(int)$this->input->post('category_id',TRUE);
                $phone=$this->phone((string)$this->input->post('whatsapp',TRUE));
                if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>190 || mb_strlen($description,'UTF-8')>5000 ||
                    mb_strlen($contact,'UTF-8')>120 || !$phone)
                    throw new InvalidArgumentException('Periksa nama, deskripsi, kontak, dan WhatsApp toko.');
                if (!in_array($category_id,array_map('intval',array_column($categories,'id')),TRUE))
                    throw new InvalidArgumentException('Pilih kategori toko yang aktif.');
                $this->db->trans_begin();$started=TRUE;
                $locked=$this->db->query('SELECT id FROM umk_businesses WHERE id=? FOR UPDATE',array($business_id))->row();
                if (!$locked || $this->db->query('SELECT id FROM tko_stores WHERE business_id=?',array($business_id))->row())
                    throw new InvalidArgumentException('Kegiatan ini sudah mempunyai toko.');
                if (!staff_scope_business($this->db,$user,$business_id))
                    throw new InvalidArgumentException('Kegiatan di luar wilayah tugas Anda.');
                if (!$this->db->query('SELECT person_id FROM umk_business_owners WHERE business_id=? AND is_primary=1 LIMIT 1',array($business_id))->row())
                    throw new InvalidArgumentException('Hubungkan pelaku utama ke kegiatan ini sebelum membuat toko.');
                $slug='toko-'.bin2hex(random_bytes(8));
                $this->db->query("INSERT INTO tko_stores (business_id,slug,display_name,description,whatsapp_e164,contact_label,status)
                    VALUES (?,?,?,?,?,?,'draft')",array($business_id,$slug,$name,$description?:NULL,$phone,$contact?:NULL));
                $store_id=(int)$this->db->insert_id();
                $this->db->query("INSERT INTO tko_store_categories (store_id,category_id,tagged_by,status) VALUES (?,?,?,'approved')",
                    array($store_id,$category_id,$user['id']));
                $this->Staff_model->audit($user['id'],'store.created','tko_stores',$store_id,'Kegiatan #'.$business_id);
                if ($this->db->trans_status()===FALSE) throw new RuntimeException('Toko gagal disimpan.');
                $this->db->trans_commit();$started=FALSE;
                redirect('pengelola/toko/'.$store_id.'?dibuat=1','location',303);return;
            }
            catch(Exception $e) { if ($started) $this->db->trans_rollback();$error=$e->getMessage(); }
        }
        $this->load->view('staff/store_create',compact('user','person','person_id','business','business_id','search','matches','categories','error'));
    }

    public function activate_store($store_id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404();return; }
        $user=$this->actor(); if (!$user) return;
        if ($user['role_code']==='admin_kecamatan') { show_error('Aktivasi toko hanya untuk admin Dinas.',403);return; }
        $started=FALSE;
        try
        {
            $visit=trim((string)$this->input->post('visit_date',TRUE));
            $findings=trim((string)$this->input->post('findings',TRUE));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$visit) || strtotime($visit)===FALSE ||
                date('Y-m-d',strtotime($visit))!==$visit || $visit>date('Y-m-d') ||
                mb_strlen($findings,'UTF-8')<20 || mb_strlen($findings,'UTF-8')>2000 ||
                (string)$this->input->post('checked',TRUE)!=='1')
                throw new InvalidArgumentException('Isi tanggal kunjungan, hasil pemeriksaan minimal 20 karakter, dan konfirmasi.');
            $this->db->trans_begin();$started=TRUE;
            $store=$this->db->query('SELECT s.id,s.status,s.business_id FROM tko_stores s WHERE s.id=? FOR UPDATE',array((int)$store_id))->row_array();
            if (!$store || $store['status']!=='draft') throw new InvalidArgumentException('Hanya toko draf yang dapat diaktifkan.');
            if (!$this->db->query('SELECT 1 FROM umk_business_owners WHERE business_id=? AND is_primary=1 LIMIT 1',array($store['business_id']))->row())
                throw new InvalidArgumentException('Hubungkan pelaku utama ke kegiatan usaha sebelum mengaktifkan toko.');
            if (!$this->db->query("SELECT 1 FROM umk_business_licenses WHERE business_id=? AND license_type='NIB'
                AND status='verified' AND verified_at IS NOT NULL AND verified_by IS NOT NULL LIMIT 1",array($store['business_id']))->row())
                throw new InvalidArgumentException('NIB resmi harus diperiksa pada profil kegiatan sebelum toko diaktifkan.');
            if (!$this->db->query("SELECT 1 FROM tko_store_categories sc JOIN ref_categories c ON c.id=sc.category_id
                WHERE sc.store_id=? AND sc.status='approved' AND c.is_active=1 LIMIT 1",array($store_id))->row())
                throw new InvalidArgumentException('Toko harus mempunyai kategori aktif.');
            $this->db->query("INSERT INTO tko_verifications (store_id,visit_date,decision,findings,decided_by,decided_at,evidence_basis)
                VALUES (?,?,'approved',?,?,NOW(),'field_visit')",array($store_id,$visit,$findings,$user['id']));
            $this->db->query("UPDATE tko_stores SET status='active',published_at=NOW() WHERE id=?",array($store_id));
            $this->Staff_model->audit($user['id'],'store.activated','tko_stores',(int)$store_id,$findings);
            if ($this->db->trans_status()===FALSE) throw new RuntimeException('Toko gagal diaktifkan.');
            $this->db->trans_commit();$started=FALSE;
            redirect('pengelola/toko/'.(int)$store_id.'?diaktifkan=1','location',303);return;
        }
        catch(Exception $e)
        {
            if ($started) $this->db->trans_rollback();
            $this->session->set_flashdata('store_activation_error',$e->getMessage());
            redirect('pengelola/toko/'.(int)$store_id,'location',303);
        }
    }
}
