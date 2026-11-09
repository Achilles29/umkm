<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registry_editor extends CI_Controller
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

    private function actor_business($business_id)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return NULL; }
        $user=$this->Staff_model->by_id((int)$this->session->userdata('staff_user_id'));
        if (!$user || $user['status']!=='active' ||
            !in_array($user['role_code'],array('superadmin','admin_dinas','admin_kecamatan'),TRUE))
        { show_error('Akses tidak diizinkan.',403); return NULL; }
        $business=staff_scope_business($this->db,$user,$business_id);
        if (!$business) { show_404(); return NULL; }
        return $user;
    }

    private function done($business_id,$tab)
    {
        redirect('pengelola/registri/'.(int)$business_id.'?tab='.$tab.'&tersimpan=1','location',303);
    }

    private function phone($raw)
    {
        $phone=preg_replace('/[\s().-]/','',$raw);
        if ($phone==='') return NULL;
        if (strpos($phone,'0')===0) $phone='+62'.substr($phone,1);
        elseif (strpos($phone,'62')===0) $phone='+'.$phone;
        if (!preg_match('/^\+62\d{8,13}$/',$phone))
            throw new InvalidArgumentException('Nomor kontak harus nomor Indonesia yang valid.');
        return $phone;
    }

    private function nik_data($nik)
    {
        if ($nik==='') return array(NULL,NULL);
        if (!preg_match('/^\d{16}$/',$nik))
            throw new InvalidArgumentException('NIK harus terdiri dari 16 angka.');
        $secret=@file_get_contents(dirname(APPPATH).'/.secrets/nik_hmac_key');
        if (strlen((string)$secret)!==32)
            throw new RuntimeException('Kunci pengenal NIK tidak tersedia.');
        return array(registry_pii_encrypt($nik),hash_hmac('sha256',"nik\x1f".$nik,$secret,TRUE));
    }

    public function person($business_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        $person=$this->db->query('SELECT p.id FROM umk_business_owners o
            JOIN umk_people p ON p.id=o.person_id WHERE o.business_id=? AND o.is_primary=1 LIMIT 1',
            array((int)$business_id))->row_array();
        if (!$person) { show_error('Pelaku belum tercatat pada kegiatan ini.',409); return; }
        if (!staff_scope_person($this->db,$user,$person['id'],TRUE))
        { show_error('Identitas pelaku yang terhubung ke wilayah lain hanya dapat diubah admin Dinas.',403); return; }
        $name=trim((string)$this->input->post('full_name',TRUE));
        $phone_raw=trim((string)$this->input->post('phone',TRUE));
        $nik=trim((string)$this->input->post('nik_new',TRUE));
        $note=trim((string)$this->input->post('change_note',TRUE));
        if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>160 ||
            mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500)
        { show_error('Periksa nama pelaku dan alasan perubahan minimal 20 karakter.',400); return; }
        try { $phone=$this->phone($phone_raw);list($cipher,$fingerprint)=$this->nik_data($nik); }
        catch(Exception $e) { show_error($e->getMessage(),400); return; }
        if ($fingerprint && $this->db->query('SELECT id FROM umk_people
            WHERE nik_fingerprint=? AND id<>? LIMIT 1',array($fingerprint,$person['id']))->row())
        { show_error('NIK sudah tercatat pada pelaku lain. Periksa hubungan identitas sebelum mengubah.',409); return; }
        $this->db->trans_begin();
        $this->db->query('UPDATE umk_people SET full_name=?,phone_e164=?,
            nik_ciphertext=IF(? IS NULL,nik_ciphertext,?),
            nik_fingerprint=IF(? IS NULL,nik_fingerprint,?) WHERE id=?',
            array($name,$phone,$cipher,$cipher,$fingerprint,$fingerprint,$person['id']));
        $this->db->query('UPDATE iam_users u
            JOIN iam_imported_business_accounts a ON a.user_id=u.id
            JOIN umk_businesses source ON source.id=a.business_id
            JOIN umk_business_owners o ON o.business_id=COALESCE(source.canonical_business_id,source.id)
                AND o.is_primary=1
            SET u.full_name=? WHERE o.person_id=?',array($name,$person['id']));
        $this->Staff_model->audit($user['id'],'umk.registry_person_updated','umk_people',
            (int)$person['id'],$note);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Pelaku gagal disimpan.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'pelaku');
    }

    public function create_person($business_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        if ($this->db->query('SELECT 1 FROM umk_business_owners WHERE business_id=? AND is_primary=1 LIMIT 1',
            array((int)$business_id))->row())
        { show_error('Kegiatan ini sudah mempunyai pelaku utama.',409); return; }
        $name=trim((string)$this->input->post('full_name',TRUE));
        $phone_raw=trim((string)$this->input->post('phone',TRUE));
        $nik=trim((string)$this->input->post('nik_new',TRUE));
        $note=trim((string)$this->input->post('change_note',TRUE));
        if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>160 ||
            mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500)
        { show_error('Periksa nama pelaku dan dasar pencatatan minimal 20 karakter.',400); return; }
        try { $phone=$this->phone($phone_raw);list($cipher,$fingerprint)=$this->nik_data($nik); }
        catch(Exception $e) { show_error($e->getMessage(),400); return; }
        if ($fingerprint && $this->db->query('SELECT id FROM umk_people WHERE nik_fingerprint=? LIMIT 1',
            array($fingerprint))->row())
        { show_error('NIK sudah tercatat. Hubungkan dengan pelaku yang ada setelah memeriksa identitas.',409); return; }
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM umk_businesses WHERE id=? FOR UPDATE',array((int)$business_id));
        if ($this->db->query('SELECT 1 FROM umk_business_owners
            WHERE business_id=? AND is_primary=1 LIMIT 1',array((int)$business_id))->row())
        { $this->db->trans_rollback(); show_error('Kegiatan ini sudah mempunyai pelaku utama.',409); return; }
        $district=(int)$this->db->query('SELECT district_id FROM umk_businesses WHERE id=?',array((int)$business_id))->row('district_id');
        $this->db->query('INSERT INTO umk_people (full_name,phone_e164,nik_ciphertext,nik_fingerprint,registration_district_id)
            VALUES (?,?,?,?,?)',array($name,$phone,$cipher,$fingerprint,$district?:NULL));
        $person_id=(int)$this->db->insert_id();
        $this->db->query("INSERT INTO umk_business_owners
            (business_id,person_id,relationship,is_primary) VALUES (?,?,'owner',1)",
            array((int)$business_id,$person_id));
        $this->Staff_model->sync_imported_account_name($business_id);
        $this->Staff_model->audit($user['id'],'umk.registry_person_added','umk_people',
            $person_id,$note);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Pelaku gagal ditambahkan.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'pelaku');
    }

    public function link_person($business_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        $person_id=(int)$this->input->post('person_id',TRUE);
        $note=trim((string)$this->input->post('change_note',TRUE));
        if ($person_id<1 || mb_strlen($note,'UTF-8')<20 || mb_strlen($note,'UTF-8')>500)
        { show_error('Pilih pelaku dan jelaskan bukti hubungan minimal 20 karakter.',400); return; }
        if (!$this->db->query('SELECT id FROM umk_people WHERE id=? LIMIT 1',array($person_id))->row())
        { show_error('Pelaku tidak ditemukan.',404); return; }
        if (!staff_scope_person($this->db,$user,$person_id,TRUE))
        { show_error('Pelaku di luar wilayah tugas atau terhubung ke wilayah lain.',403); return; }
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM umk_businesses WHERE id=? FOR UPDATE',array((int)$business_id));
        if ($this->db->query('SELECT 1 FROM umk_business_owners
            WHERE business_id=? AND is_primary=1 LIMIT 1',array((int)$business_id))->row())
        { $this->db->trans_rollback(); show_error('Kegiatan ini sudah mempunyai pelaku utama.',409); return; }
        $this->db->query("INSERT INTO umk_business_owners
            (business_id,person_id,relationship,is_primary) VALUES (?,?,'owner',1)",
            array((int)$business_id,$person_id));
        $this->Staff_model->sync_imported_account_name($business_id);
        $this->Staff_model->audit($user['id'],'umk.registry_person_linked','umk_businesses',
            (int)$business_id,$note);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Hubungan pelaku gagal disimpan.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'pelaku');
    }

    public function profile($business_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        $fields=array();
        foreach(array('name','legal_name','legal_form','sector_label','description','address','contact_phone',
                      'website_url','social_media_url','export_destinations','export_potential',
                      'production_capacity') as $key)
            $fields[$key]=trim((string)$this->input->post($key,TRUE));
        $limits=array('name'=>190,'legal_name'=>190,'sector_label'=>190,'description'=>5000,'address'=>1000,
                      'website_url'=>500,'social_media_url'=>500,'export_destinations'=>2000,
                      'export_potential'=>2000,'production_capacity'=>190);
        foreach($limits as $key=>$limit)
            if (mb_strlen($fields[$key],'UTF-8')>$limit)
            { show_error('Isian '.$key.' terlalu panjang.',400); return; }
        if (mb_strlen($fields['name'],'UTF-8')<2 ||
            !in_array($fields['legal_form'],array('','perorangan','cv','pt','koperasi','firma','yayasan','lainnya'),TRUE))
        { show_error('Periksa nama kegiatan dan bentuk usaha.',400); return; }
        $district=(int)$this->input->post('district_id',TRUE);
        $village=(int)$this->input->post('village_id',TRUE);
        $scale=(int)$this->input->post('scale_id',TRUE);
        if ($district && !$this->db->query('SELECT 1 FROM ref_districts WHERE id=? AND is_active=1',array($district))->row())
        { show_error('Kecamatan tidak dikenal.',400); return; }
        if (!staff_scope_has_district(staff_scope_districts($this->db,$user),$district))
        { show_error('Kecamatan di luar wilayah tugas Anda.',403); return; }
        if ($village && (!$district || !$this->db->query('SELECT 1 FROM ref_villages
            WHERE id=? AND district_id=? AND is_active=1',array($village,$district))->row()))
        { show_error('Desa tidak sesuai kecamatan.',400); return; }
        if ($scale && !$this->db->query('SELECT 1 FROM ref_business_scales WHERE id=?',array($scale))->row())
        { show_error('Skala usaha tidak dikenal.',400); return; }
        $year=trim((string)$this->input->post('started_year',TRUE));
        $employees=trim((string)$this->input->post('employee_count',TRUE));
        $revenue=trim((string)$this->input->post('annual_revenue_idr',TRUE));
        if (($year!=='' && (!ctype_digit($year) || (int)$year<1900 || (int)$year>(int)date('Y'))) ||
            ($employees!=='' && (!ctype_digit($employees) || (int)$employees>1000000)) ||
            ($revenue!=='' && !preg_match('/^\d{1,15}(?:\.\d{1,2})?$/',$revenue)))
        { show_error('Periksa tahun berdiri, jumlah tenaga kerja, dan omzet tahunan.',400); return; }
        try { $phone=$this->phone($fields['contact_phone']);$cipher=registry_pii_encrypt($fields['address']); }
        catch(InvalidArgumentException $e) { show_error($e->getMessage(),400); return; }
        catch(Exception $e) { show_error($e->getMessage(),500); return; }
        $this->db->trans_begin();
        $this->db->query('UPDATE umk_businesses SET name=?,legal_name=?,legal_form=?,sector_label=?,description=?,
            district_id=?,village_id=?,address_ciphertext=?,scale_id=?,started_year=?,
            employee_count=?,annual_revenue_idr=?,contact_phone_e164=?,website_url=?,
            social_media_url=?,export_destinations=?,export_potential=?,production_capacity=?
            WHERE id=?',array($fields['name'],$fields['legal_name']?:NULL,$fields['legal_form']?:NULL,
            $fields['sector_label']?:NULL,
            $fields['description']?:NULL,$district?:NULL,$village?:NULL,$cipher,$scale?:NULL,
            $year===''?NULL:(int)$year,$employees===''?NULL:(int)$employees,
            $revenue===''?NULL:$revenue,$phone?:NULL,$fields['website_url']?:NULL,
            $fields['social_media_url']?:NULL,$fields['export_destinations']?:NULL,
            $fields['export_potential']?:NULL,$fields['production_capacity']?:NULL,(int)$business_id));
        $this->Staff_model->audit($user['id'],'umk.registry_profile_updated','umk_businesses',(int)$business_id);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Profil gagal disimpan.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'profil');
    }

    public function kbli($business_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        $raw=trim((string)$this->input->post('kbli_codes',TRUE));
        $codes=$raw===''?array():array_values(array_unique(preg_split('/[\s,;]+/',$raw)));
        if (count($codes)>20) { show_error('Maksimal 20 kode KBLI per kegiatan.',400); return; }
        foreach($codes as $code) if (!preg_match('/^\d{5}$/',$code))
        { show_error('KBLI harus terdiri dari lima angka per kode.',400); return; }
        $version=$this->db->query('SELECT id FROM ref_kbli_versions WHERE is_current=1 ORDER BY id DESC LIMIT 1')->row_array();
        if (!$version) { show_error('Master KBLI aktif belum tersedia.',500); return; }
        $matches=array();
        foreach($codes as $code)
        {
            $found=$this->db->query('SELECT id FROM ref_kbli_codes WHERE version_id=? AND code=? LIMIT 1',
                array($version['id'],$code))->row_array();
            if (!$found) { show_error('Kode KBLI '.$code.' tidak ada pada master aktif.',400); return; }
            $matches[]=(int)$found['id'];
        }
        $this->db->trans_begin();
        $this->db->query('DELETE FROM umk_business_kbli WHERE business_id=?',array((int)$business_id));
        foreach($matches as $index=>$id)
            $this->db->query('INSERT INTO umk_business_kbli (business_id,kbli_code_id,is_primary)
                VALUES (?,?,?)',array((int)$business_id,$id,$index===0?1:0));
        $this->Staff_model->audit($user['id'],'umk.registry_kbli_updated','umk_businesses',(int)$business_id);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('KBLI gagal disimpan.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'klasifikasi');
    }

    public function commodity($business_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        $name=trim((string)$this->input->post('commodity_name',TRUE));
        $code=trim((string)$this->input->post('kbki_code',TRUE));
        if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>1000 ||
            ($code!=='' && !preg_match('/^(?:\d{5}|\d{7}|\d{10})$/',$code)))
        { show_error('Periksa nama komoditas dan kode KBKI 5, 7, atau 10 angka.',400); return; }
        $count=(int)$this->db->query('SELECT COUNT(*) n FROM umk_business_commodities
            WHERE business_id=?',array((int)$business_id))->row('n');
        if ($count>=30) { show_error('Maksimal 30 komoditas per kegiatan.',400); return; }
        $this->db->trans_begin();
        $this->db->query('INSERT INTO umk_business_commodities
            (business_id,commodity_name,kbki_code,kbki_version,kbki_review_status,is_primary)
            VALUES (?,?,?,? ,?,?)',array((int)$business_id,$name,$code?:NULL,
            $code?'KBKI 2015':NULL,$code?'needs_review':'not_mapped',$count===0?1:0));
        $id=(int)$this->db->insert_id();
        $this->Staff_model->audit($user['id'],'umk.registry_commodity_added','umk_business_commodities',$id);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Komoditas gagal disimpan.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'klasifikasi');
    }

    public function delete_commodity($business_id=0,$commodity_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        $row=$this->db->query('SELECT id,is_primary FROM umk_business_commodities
            WHERE id=? AND business_id=? LIMIT 1',array((int)$commodity_id,(int)$business_id))->row_array();
        if (!$row) { show_404(); return; }
        $this->db->trans_begin();
        $this->db->query('DELETE FROM umk_business_commodities WHERE id=?',array((int)$commodity_id));
        if ($row['is_primary'])
            $this->db->query('UPDATE umk_business_commodities SET is_primary=1
                WHERE business_id=? ORDER BY id LIMIT 1',array((int)$business_id));
        $this->Staff_model->audit($user['id'],'umk.registry_commodity_removed','umk_business_commodities',(int)$commodity_id);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Komoditas gagal dihapus.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'klasifikasi');
    }

    public function update_commodity($business_id=0,$commodity_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        $name=trim((string)$this->input->post('commodity_name',TRUE));
        $code=trim((string)$this->input->post('kbki_code',TRUE));
        $primary=(string)$this->input->post('is_primary',TRUE)==='1';
        if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>1000 ||
            ($code!=='' && !preg_match('/^(?:\d{5}|\d{7}|\d{10})$/',$code)))
        { show_error('Periksa nama komoditas dan kode KBKI 5, 7, atau 10 angka.',400); return; }
        $row=$this->db->query('SELECT id,kbki_code FROM umk_business_commodities
            WHERE id=? AND business_id=? LIMIT 1',array((int)$commodity_id,(int)$business_id))->row_array();
        if (!$row) { show_404(); return; }
        $this->db->trans_begin();
        if ($primary)
            $this->db->query('UPDATE umk_business_commodities SET is_primary=0 WHERE business_id=?',
                array((int)$business_id));
        $status=$code===''?'not_mapped':'needs_review';
        $this->db->query('UPDATE umk_business_commodities SET commodity_name=?,kbki_code=?,
            kbki_version=?,kbki_review_status=?,is_primary=IF(?=1,1,is_primary)
            WHERE id=? AND business_id=?',array($name,$code?:NULL,$code?'KBKI 2015':NULL,
            $status,$primary?1:0,(int)$commodity_id,(int)$business_id));
        $this->Staff_model->audit($user['id'],'umk.registry_commodity_updated',
            'umk_business_commodities',(int)$commodity_id);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Komoditas gagal diperbarui.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'klasifikasi');
    }

    public function nib($business_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        $number=trim((string)$this->input->post('nib_number',TRUE));
        $checked=(string)$this->input->post('document_checked',TRUE)==='1';
        if ($checked && $user['role_code']==='admin_kecamatan')
        { show_error('Pemeriksaan NIB hanya untuk admin Dinas.',403); return; }
        $note=trim((string)$this->input->post('verification_note',TRUE));
        if (!preg_match('/^\d{13}$/',$number) || mb_strlen($note,'UTF-8')>500 ||
            ($checked && mb_strlen($note,'UTF-8')<20))
        { show_error('NIB harus 13 angka. Jika dokumen diperiksa, tulis bukti pemeriksaan minimal 20 karakter.',400); return; }
        $this->db->trans_begin();
        $existing=$this->db->query('SELECT id,status FROM umk_business_licenses
            WHERE business_id=? AND license_type=\'NIB\' AND license_number=?
            ORDER BY id DESC LIMIT 1 FOR UPDATE',array((int)$business_id,$number))->row_array();
        if ($existing && $existing['status']==='verified')
        { $this->db->trans_rollback(); show_error('NIB ini sudah diverifikasi.',409); return; }
        if ($existing && $checked)
            $this->db->query("UPDATE umk_business_licenses SET status='verified',verified_at=NOW(),
                verified_by=?,evidence_basis='document_checked',verification_note=? WHERE id=?",
                array($user['id'],$note,$existing['id']));
        elseif ($existing)
        { $this->db->trans_rollback(); show_error('NIB ini sudah menunggu pemeriksaan.',409); return; }
        else
            $this->db->query('INSERT INTO umk_business_licenses
                (business_id,license_type,license_number,status,verified_at,verified_by,evidence_basis,verification_note)
                VALUES (?,\'NIB\',?,?,?,?,\'document_checked\',?)',
                array((int)$business_id,$number,$checked?'verified':'submitted',
                $checked?date('Y-m-d H:i:s'):NULL,$checked?$user['id']:NULL,$note?:NULL));
        $this->Staff_model->audit($user['id'],'umk.registry_nib_'.($checked?'verified':'submitted'),
            'umk_businesses',(int)$business_id);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('NIB gagal disimpan.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'perizinan');
    }

    public function upload_nib_document($business_id=0)
    {
        $user=$this->actor_business($business_id); if (!$user) return;
        if (empty($_FILES['nib_document']) || $_FILES['nib_document']['error']!==UPLOAD_ERR_OK ||
            !is_uploaded_file($_FILES['nib_document']['tmp_name']) ||
            $_FILES['nib_document']['size']<1 || $_FILES['nib_document']['size']>5*1024*1024)
        { show_error('Pilih berkas NIB PDF, JPG, atau PNG maksimal 5 MB.',400); return; }
        $signature=file_get_contents($_FILES['nib_document']['tmp_name'],FALSE,NULL,0,16);
        if (strncmp($signature,'%PDF-',5)===0) { $mime='application/pdf';$extension='pdf'; }
        elseif (strncmp($signature,"\xff\xd8\xff",3)===0) { $mime='image/jpeg';$extension='jpg'; }
        elseif (strncmp($signature,"\x89PNG\r\n\x1a\n",8)===0) { $mime='image/png';$extension='png'; }
        else
        { show_error('Berkas NIB harus PDF, JPG, atau PNG.',400); return; }
        $directory=dirname(APPPATH).'/var/documents/nib';
        if (!is_dir($directory) || !is_writable($directory))
        { show_error('Penyimpanan dokumen belum siap.',500); return; }
        $filename=bin2hex(random_bytes(16)).'.'.$extension;
        $path=$directory.'/'.$filename;
        $sha=hash_file('sha256',$_FILES['nib_document']['tmp_name'],TRUE);
        if (!move_uploaded_file($_FILES['nib_document']['tmp_name'],$path))
        { show_error('Berkas NIB gagal disimpan.',500); return; }
        chmod($path,0640);
        $this->db->trans_begin();
        $this->db->query("INSERT INTO umk_documents
            (business_id,document_type,storage_key,mime_type,sha256,uploaded_by)
            VALUES (?,'NIB',?,?,?,?)",array((int)$business_id,'nib/'.$filename,$mime,$sha,$user['id']));
        $id=(int)$this->db->insert_id();
        $this->Staff_model->audit($user['id'],'umk.registry_nib_document_uploaded','umk_documents',$id);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); unlink($path); show_error('Dokumen NIB gagal dicatat.',500); return; }
        $this->db->trans_commit();
        $this->done($business_id,'perizinan');
    }

    public function download_nib_document($business_id=0,$document_id=0)
    {
        if ($this->input->method(TRUE)!=='GET') { show_404(); return; }
        $user=$this->Staff_model->by_id((int)$this->session->userdata('staff_user_id'));
        if (!$user || $user['status']!=='active' ||
            !in_array($user['role_code'],array('superadmin','admin_dinas','admin_kecamatan'),TRUE))
        { show_error('Akses tidak diizinkan.',403); return; }
        if (!staff_scope_business($this->db,$user,$business_id))
        { show_404(); return; }
        $doc=$this->db->query("SELECT d.storage_key,d.mime_type,d.sha256 FROM umk_documents d
            JOIN umk_businesses b ON b.id=d.business_id
            WHERE d.id=? AND d.business_id=? AND d.document_type='NIB'
              AND b.is_demo=0 AND b.canonical_business_id IS NULL LIMIT 1",
            array((int)$document_id,(int)$business_id))->row_array();
        if (!$doc || !preg_match('#^nib/[a-f0-9]{32}\.(pdf|jpg|png)$#',$doc['storage_key']))
        { show_404(); return; }
        $path=dirname(APPPATH).'/var/documents/'.$doc['storage_key'];
        if (!is_file($path) || !hash_equals($doc['sha256'],hash_file('sha256',$path,TRUE)))
        { show_error('Dokumen tidak tersedia atau tidak sesuai catatan.',500); return; }
        $ext=pathinfo($path,PATHINFO_EXTENSION);
        header('Content-Type: '.$doc['mime_type']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="NIB-'.$business_id.'.'.$ext.'"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit;
    }
}
