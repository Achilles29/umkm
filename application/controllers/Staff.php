<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Staff extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
        $this->load->helper('staff_scope');
    }

    public function login()
    {
        if ($this->current_user())
        {
            redirect('pengelola', 'location', 302);
            return;
        }
        $error = '';
        if ($this->input->method(TRUE) === 'POST')
        {
            $email = strtolower(trim((string) $this->input->post('email', TRUE)));
            $password = (string) $this->input->post('password', FALSE);
            if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '')
            {
                $error = 'Periksa alamat email dan kata sandi.';
            }
            elseif ($this->Staff_model->too_many_attempts($email, $this->input->ip_address()))
            {
                $error = 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.';
            }
            else
            {
                $user = $this->Staff_model->by_email($email);
                if (!$user || $user['status'] !== 'active' || !$user['password_hash'] ||
                    !password_verify($password, $user['password_hash']))
                {
                    $this->Staff_model->failed_attempt($email, $this->input->ip_address());
                    $error = 'Email atau kata sandi tidak sesuai.';
                }
                else
                {
                    $this->Staff_model->clear_attempts($email, $this->input->ip_address());
                    $this->session->sess_regenerate(TRUE);
                    $this->session->set_userdata('staff_user_id', (int) $user['id']);
                    $this->session->set_userdata('staff_auth_epoch', (int) $user['auth_epoch']);
                    $this->db->query('UPDATE iam_users SET last_login_at = NOW() WHERE id = ?', array($user['id']));
                    $this->Staff_model->audit($user['id'], 'staff.login', 'iam_users', $user['id']);
                    redirect('pengelola', 'location', 303);
                    return;
                }
            }
        }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('staff/login', array('error' => $error));
    }

    public function dashboard()
    {
        $user = $this->require_user();
        if (!$user) return;
        $this->load->model('Notification_model');
        $unread_count=$this->Notification_model->unread_count($user['id']);
        $recent_notifications=$this->Notification_model->items($user['id'],FALSE,5,0);
        $districts = array();
        if ($user['role_code'] === 'admin_kecamatan')
        {
            $districts = $this->db->query(
                "SELECT d.id, d.name, d.code FROM iam_district_roles AS dr
                 JOIN ref_districts AS d ON d.id = dr.district_id
                 JOIN iam_roles AS r ON r.id = dr.role_id
                 WHERE dr.user_id = ? AND r.code = 'admin_kecamatan' ORDER BY d.name",
                array($user['id'])
            )->result_array();
        }
        $business_count = NULL;
        $unlinked_store_count=0;
        $stats=array();
        if (in_array($user['role_code'], array('superadmin','admin_dinas'), TRUE))
        {
            $unlinked_store_count=(int)$this->db->query("SELECT COUNT(*) n FROM tko_stores s
                JOIN umk_businesses b ON b.id=s.business_id
                WHERE s.status='active' AND b.is_demo=0 AND NOT EXISTS (
                    SELECT 1 FROM umk_business_owners o WHERE o.business_id=b.id AND o.is_primary=1
                )")->row('n');
            $business_count = (int) $this->db->query('SELECT COUNT(*) AS n FROM umk_businesses WHERE is_demo = 0 AND canonical_business_id IS NULL')->row('n');
            $actor_count=(int)$this->db->query('SELECT COUNT(DISTINCT o.person_id) n
                FROM umk_business_owners o JOIN umk_businesses b ON b.id=o.business_id
                WHERE b.is_demo=0 AND b.canonical_business_id IS NULL AND o.is_primary=1')->row('n');
            $stats=array(
                array('label'=>'Pelaku UMKM','value'=>$actor_count,'note'=>'Identitas terhubung','icon'=>'♙'),
                array('label'=>'Kegiatan usaha','value'=>$business_count,'note'=>'Data registri kanonis','icon'=>'▦'),
                array('label'=>'Member','value'=>(int)$this->db->query('SELECT COUNT(*) n FROM crm_customers')->row('n'),'note'=>'Akun pengunjung','icon'=>'♙'),
                array('label'=>'Toko etalase','value'=>(int)$this->db->query("SELECT COUNT(*) n FROM tko_stores WHERE status='active'")->row('n'),'note'=>'Toko yang tampil','icon'=>'◈'),
                array('label'=>'Produk tampil','value'=>(int)$this->db->query("SELECT COUNT(*) n FROM prd_products p JOIN tko_stores s ON s.id=p.store_id WHERE p.status='active' AND p.published_at IS NOT NULL AND s.status='active'")->row('n'),'note'=>'Katalog publik','icon'=>'▤'),
                array('label'=>'Pesan baru','value'=>$unread_count,'note'=>'Perlu dilihat di inbox','icon'=>'✉')
            );
        }
        elseif ($user['role_code'] === 'admin_kecamatan')
        {
            $ids = array_column($districts, 'id');
            $business_count = $ids ? (int) $this->db->where('is_demo', 0)->where('canonical_business_id IS NULL',NULL,FALSE)->where_in('district_id', $ids)->count_all_results('umk_businesses') : 0;
            $actor_count=$ids?(int)$this->db->query('SELECT COUNT(DISTINCT o.person_id) n
                FROM umk_businesses b JOIN umk_business_owners o ON o.business_id=b.id AND o.is_primary=1
                WHERE b.is_demo=0 AND b.canonical_business_id IS NULL AND b.district_id IN ('.
                implode(',',array_map('intval',$ids)).')')->row('n'):0;
            $store_count=$ids ? (int)$this->db->query('SELECT COUNT(*) n FROM tko_stores s JOIN umk_businesses b ON b.id=s.business_id WHERE s.status="active" AND b.district_id IN ('.implode(',',array_map('intval',$ids)).')')->row('n') : 0;
            $stats=array(
                array('label'=>'Pelaku wilayah','value'=>$actor_count,'note'=>'Dalam tugas kecamatan','icon'=>'♙'),
                array('label'=>'Kegiatan usaha','value'=>$business_count,'note'=>'Dalam tugas kecamatan','icon'=>'▦'),
                array('label'=>'Kecamatan tugas','value'=>count($districts),'note'=>'Wilayah yang diberikan','icon'=>'◎'),
                array('label'=>'Toko etalase','value'=>$store_count,'note'=>'Dalam wilayah tugas','icon'=>'◈'),
                array('label'=>'Pesan baru','value'=>$unread_count,'note'=>'Perlu dilihat di inbox','icon'=>'✉')
            );
        }
        elseif ($user['role_code'] === 'umkm')
        {
            $profile_count=(int)$this->db->query('SELECT COUNT(DISTINCT b.id) n
                FROM iam_imported_business_accounts a JOIN umk_businesses source ON source.id=a.business_id
                LEFT JOIN umk_business_owners base_owner ON base_owner.business_id=COALESCE(source.canonical_business_id,source.id) AND base_owner.is_primary=1
                JOIN umk_businesses b ON b.is_demo=0 AND b.canonical_business_id IS NULL
                  AND (b.id=COALESCE(source.canonical_business_id,source.id) OR EXISTS
                    (SELECT 1 FROM umk_business_owners o WHERE o.business_id=b.id
                     AND o.person_id=base_owner.person_id AND o.is_primary=1))
                WHERE a.user_id=?',array($user['id']))->row('n');
            $store_count=(int)$this->db->query("SELECT COUNT(*) n FROM tko_store_managers WHERE user_id=? AND status='active'",array($user['id']))->row('n');
            $product_count=(int)$this->db->query("SELECT COUNT(*) n FROM prd_products p JOIN tko_store_managers m ON m.store_id=p.store_id WHERE m.user_id=? AND m.status='active'",array($user['id']))->row('n');
            $request_count=(int)$this->db->query("SELECT COUNT(*) n FROM tko_category_requests r JOIN tko_store_managers m ON m.store_id=r.store_id WHERE m.user_id=? AND m.status='active' AND r.status='pending'",array($user['id']))->row('n');
            $stats=array(
                array('label'=>'Kegiatan usaha saya','value'=>$profile_count,'note'=>'Data registri yang terhubung','icon'=>'▦'),
                array('label'=>'Toko saya','value'=>$store_count,'note'=>'Akses pengelolaan aktif','icon'=>'◈'),
                array('label'=>'Produk','value'=>$product_count,'note'=>'Di toko yang dikelola','icon'=>'▤'),
                array('label'=>'Pengajuan menunggu','value'=>$request_count,'note'=>'Kategori dalam tinjauan','icon'=>'◇'),
                array('label'=>'Pesan baru','value'=>$unread_count,'note'=>'Perlu dilihat di inbox','icon'=>'✉')
            );
        }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('staff/dashboard', compact('user','districts','stats','unread_count','recent_notifications','unlinked_store_count'));
    }

    public function area()
    {
        $user = $this->require_user();
        if (!$user) return;
        if (!in_array($user['role_code'], array('superadmin','admin_dinas'), TRUE))
        {
            show_error('Akses tidak diizinkan.', 403);
            return;
        }
        $error = '';
        $success = '';
        if ($this->input->method(TRUE) === 'POST')
        {
            try
            {
                $count = $this->import_area_file($user);
                $success = $count.' batas wilayah berhasil disimpan.';
            }
            catch (Exception $e)
            {
                $error = $e->getMessage();
            }
        }
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $type=(string)$this->input->get('type',TRUE);
        if (!in_array($type,array('','district','village'),TRUE)) $type='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $where=' WHERE 1=1';$params=array();
        if ($search!=='') { $where.=' AND (area_code LIKE ? OR source_name LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        if ($type!=='') { $where.=' AND area_type=?';$params[]=$type; }
        $total=(int)$this->db->query('SELECT COUNT(*) n FROM gis_area_boundaries'.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));$page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $areas=$this->db->query('SELECT area_type, area_code, source_name, imported_at FROM gis_area_boundaries'.$where.' ORDER BY imported_at DESC, id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('staff/area', array('user' => $user, 'error' => $error,
                                              'success' => $success, 'areas' => $areas,
                                              'search'=>$search,'type'=>$type,'per_page'=>$per_page,'total'=>$total,'page'=>$page,'pages'=>$pages));
    }

    public function banner()
    {
        $user = $this->require_user();
        if (!$user) return;
        if (!in_array($user['role_code'], array('superadmin','admin_dinas'), TRUE))
        {
            show_error('Akses tidak diizinkan.', 403);
            return;
        }
        $error = '';
        $current = $this->db->query("SELECT * FROM cms_banners WHERE slot = 'marketplace' LIMIT 1")->row_array();
        if (!$current) { show_error('Banner belum tersedia.', 503); return; }
        if ($this->input->method(TRUE) === 'POST')
        {
            $eyebrow = trim((string) $this->input->post('eyebrow', TRUE));
            $title = trim((string) $this->input->post('title', TRUE));
            $subtitle = trim((string) $this->input->post('subtitle', TRUE));
            $cta_label = trim((string) $this->input->post('cta_label', TRUE));
            $target = (string) $this->input->post('cta_target', TRUE);
            $active = $this->input->post('is_active') ? 1 : 0;
            $image_path = $current['image_path'];
            $new_file = NULL;
            try
            {
                if (mb_strlen($eyebrow, 'UTF-8') < 2 || mb_strlen($eyebrow, 'UTF-8') > 80 ||
                    mb_strlen($title, 'UTF-8') < 5 || mb_strlen($title, 'UTF-8') > 140 ||
                    mb_strlen($subtitle, 'UTF-8') > 255 ||
                    mb_strlen($cta_label, 'UTF-8') < 2 || mb_strlen($cta_label, 'UTF-8') > 60 ||
                    !in_array($target, array('products','stores','map'), TRUE))
                {
                    throw new InvalidArgumentException('Periksa teks banner dan tujuan tombol.');
                }
                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE)
                {
                    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK ||
                        $_FILES['image']['size'] > 3 * 1024 * 1024 ||
                        !is_uploaded_file($_FILES['image']['tmp_name']))
                    {
                        throw new InvalidArgumentException('Gambar harus JPG, PNG, atau WebP maksimal 3 MB.');
                    }
                    $info = @getimagesize($_FILES['image']['tmp_name']);
                    $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
                    if (!$info || !isset($extensions[$info['mime']]) || $info[0] < 600 || $info[1] < 220 ||
                        $info[0] > 5000 || $info[1] > 3000)
                    {
                        throw new InvalidArgumentException('Gambar harus JPG, PNG, atau WebP berukuran 600×220 sampai 5000×3000 piksel.');
                    }
                    $new_file = bin2hex(random_bytes(16)).'.'.$extensions[$info['mime']];
                    $destination = FCPATH.'assets/banners/'.$new_file;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination))
                    {
                        throw new RuntimeException('Gambar gagal disimpan.');
                    }
                    chmod($destination, 0644);
                    $image_path = $new_file;
                }
                elseif ($this->input->post('remove_image'))
                {
                    $image_path = NULL;
                }
                $this->db->query('UPDATE cms_banners SET eyebrow = ?, title = ?, subtitle = ?,
                                  cta_label = ?, cta_target = ?, image_path = ?, is_active = ?, updated_by = ?
                                  WHERE id = ?',
                    array($eyebrow, $title, $subtitle, $cta_label, $target, $image_path,
                          $active, $user['id'], $current['id']));
                if ($this->db->error()['code']) throw new RuntimeException('Banner gagal diperbarui.');
                $this->Staff_model->audit($user['id'], 'cms.banner_update', 'cms_banners', $current['id']);
                if ($current['image_path'] && $current['image_path'] !== $image_path)
                {
                    $old = FCPATH.'assets/banners/'.basename($current['image_path']);
                    if (is_file($old)) unlink($old);
                }
                redirect('pengelola/banner?tersimpan=1', 'location', 303);
                return;
            }
            catch (Exception $exception)
            {
                if ($new_file && is_file(FCPATH.'assets/banners/'.$new_file))
                    unlink(FCPATH.'assets/banners/'.$new_file);
                $error = $exception->getMessage();
            }
        }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('staff/banner', array('user' => $user, 'banner' => $current,
            'error' => $error, 'saved' => (bool) $this->input->get('tersimpan', TRUE)));
    }

    public function locations()
    {
        $user = $this->require_user();
        if (!$user) return;
        if (!in_array($user['role_code'], array('superadmin','admin_dinas'), TRUE))
        {
            show_error('Akses tidak diizinkan.', 403);
            return;
        }
        $query = mb_substr(trim((string) $this->input->get('q', TRUE)), 0, 80, 'UTF-8');
        $point=(string)$this->input->get('point',TRUE);
        if (!in_array($point,array('','public','pending'),TRUE)) $point='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $page = max(1, (int) $this->input->get('page', TRUE));
        $where=' WHERE 1=1';$params=array();
        if ($query!=='') { $where.=' AND s.display_name LIKE ?';$params[]='%'.$query.'%'; }
        $public="gl.visibility='public' AND gl.consent_at IS NOT NULL AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL AND gl.public_longitude IS NOT NULL";
        if ($point==='public') $where.=' AND ('.$public.')';
        if ($point==='pending') $where.=' AND COALESCE(('.$public.'),0)=0';
        $total = (int) $this->db->query('SELECT COUNT(*) AS n FROM tko_stores AS s LEFT JOIN gis_business_locations AS gl ON gl.business_id=s.business_id'.$where, $params)->row('n');
        $pages = max(1, (int) ceil($total / $per_page));
        $page=min($page,$pages);
        $rows = $this->db->query(
            'SELECT s.id, s.display_name, s.status, b.is_demo, d.name AS district_name,
                    gl.visibility, gl.consent_at, gl.checked_at, gl.public_latitude, gl.public_longitude
             FROM tko_stores AS s JOIN umk_businesses AS b ON b.id = s.business_id
             LEFT JOIN ref_districts AS d ON d.id = b.district_id
             LEFT JOIN gis_business_locations AS gl ON gl.business_id = b.id'.$where.
            ' ORDER BY s.id DESC LIMIT '.$per_page.' OFFSET '.(int)(($page-1)*$per_page), $params
        )->result_array();
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('staff/locations', array('user' => $user, 'rows' => $rows,
            'query' => $query, 'point'=>$point, 'page' => $page, 'pages' => $pages, 'total' => $total,'per_page'=>$per_page));
    }

    public function contact_settings()
    {
        $user = $this->require_user();
        if (!$user) return;
        if (!in_array($user['role_code'], array('superadmin','admin_dinas'), TRUE))
        {
            show_error('Akses tidak diizinkan.', 403);
            return;
        }
        $error = '';
        if ($this->input->method(TRUE) === 'POST')
        {
            $input = trim((string)$this->input->post('test_whatsapp', TRUE));
            $digits = preg_replace('/\D+/', '', $input);
            if (strpos($digits, '0') === 0) $digits = '62'.substr($digits, 1);
            if ($input !== '' && (!preg_match('/^62[0-9]{8,13}$/', $digits) || $digits === '6280000000000'))
            {
                $error = 'Masukkan nomor WhatsApp Indonesia yang valid atau kosongkan untuk menonaktifkan.';
            }
            else
            {
                $number = $input === '' ? NULL : '+'.$digits;
                $this->db->query('UPDATE mkt_contact_settings SET test_whatsapp_e164 = ?, updated_by = ? WHERE id = 1',
                                 array($number, $user['id']));
                $this->Staff_model->audit($user['id'], 'mkt.test_destination_update',
                                          'mkt_contact_settings', 1, $number ? 'Nomor tujuan uji diperbarui' : 'Nomor tujuan uji dihapus');
                redirect('pengelola/kontak?tersimpan=1', 'location', 303);
                return;
            }
        }
        $row = $this->db->query('SELECT test_whatsapp_e164, updated_at FROM mkt_contact_settings WHERE id = 1')->row_array();
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('staff/contact_settings', array('user' => $user, 'settings' => $row,
            'error' => $error, 'saved' => (bool)$this->input->get('tersimpan', TRUE)));
    }

    public function location($store_id = 0)
    {
        $user = $this->require_user();
        if (!$user) return;
        if (!in_array($user['role_code'], array('superadmin','admin_dinas'), TRUE))
        {
            show_error('Akses tidak diizinkan.', 403);
            return;
        }
        $store = $this->db->query(
            'SELECT s.id, s.display_name, s.slug, s.status, b.id AS business_id, b.district_id,
                    b.is_demo, d.name AS district_name, gl.latitude, gl.longitude,
                    gl.visibility, gl.consent_reference, gl.checked_at
             FROM tko_stores AS s JOIN umk_businesses AS b ON b.id = s.business_id
             LEFT JOIN ref_districts AS d ON d.id = b.district_id
             LEFT JOIN gis_business_locations AS gl ON gl.business_id = b.id
             WHERE s.id = ? LIMIT 1', array((int)$store_id))->row_array();
        if (!$store) { show_404(); return; }
        $error = '';
        if ($this->input->method(TRUE) === 'POST')
        {
            try
            {
                $lat_input = trim((string)$this->input->post('latitude', TRUE));
                $lng_input = trim((string)$this->input->post('longitude', TRUE));
                $publish = (bool)$this->input->post('publish');
                $reference = mb_substr(trim((string)$this->input->post('consent_reference', TRUE)),0,190,'UTF-8');
                if (($lat_input === '') !== ($lng_input === '') ||
                    ($lat_input !== '' && (!is_numeric($lat_input) || !is_numeric($lng_input))))
                    throw new InvalidArgumentException('Isi kedua koordinat atau kosongkan keduanya.');
                $lat = $lat_input === '' ? NULL : (float)$lat_input;
                $lng = $lng_input === '' ? NULL : (float)$lng_input;
                if ($lat !== NULL && ($lat < -7.5 || $lat > -6.0 || $lng < 110.5 || $lng > 112.5))
                    throw new InvalidArgumentException('Koordinat berada di luar rentang pemeriksaan Rembang.');
                if ($publish && ($lat === NULL || $store['is_demo'] ||
                    !$this->input->post('consent_confirmed') || mb_strlen($reference,'UTF-8') < 8))
                    throw new InvalidArgumentException('Publikasi memerlukan toko riil, koordinat, dan rujukan persetujuan pemilik yang sudah diperiksa.');
                $this->db->query(
                    "INSERT INTO gis_business_locations
                     (business_id,latitude,longitude,public_latitude,public_longitude,
                      visibility,consent_at,consent_reference,checked_at,updated_by)
                     VALUES (?,?,?,?,?,?,IF(? = 1,NOW(),NULL),?,IF(? = 1,NOW(),NULL),?)
                     ON DUPLICATE KEY UPDATE latitude=VALUES(latitude),longitude=VALUES(longitude),
                       public_latitude=VALUES(public_latitude),public_longitude=VALUES(public_longitude),
                       visibility=VALUES(visibility),consent_at=VALUES(consent_at),
                       consent_reference=VALUES(consent_reference),checked_at=VALUES(checked_at),
                       updated_by=VALUES(updated_by)",
                    array($store['business_id'],$lat,$lng,$publish?$lat:NULL,$publish?$lng:NULL,
                          $publish?'public':'hidden',$publish?1:0,$publish?$reference:NULL,
                          $publish?1:0,$user['id']));
                if ($this->db->error()['code']) throw new RuntimeException('Lokasi gagal disimpan.');
                $this->Staff_model->audit($user['id'], 'gis.location_update', 'gis_business_locations',
                    $store['business_id'], $publish ? 'Publik; rujukan: '.$reference : 'Disimpan internal');
                redirect('pengelola/lokasi/'.$store['id'].'?tersimpan=1', 'location', 303);
                return;
            }
            catch (Exception $exception) { $error = $exception->getMessage(); }
        }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('staff/location', array('user' => $user, 'store' => $store,
            'error' => $error, 'saved' => (bool)$this->input->get('tersimpan', TRUE)));
    }

    public function people()
    {
        $user=$this->require_user(); if (!$user) return;
        $scope=$this->registry_scope($user);
        if ($scope===FALSE) { show_error('Akses tidak diizinkan.',403); return; }
        $districts=$this->registry_districts($scope);
        $district_id=(int)$this->input->get('district',TRUE);
        if ($district_id && !in_array($district_id,array_map('intval',array_column($districts,'id')),TRUE))
        { show_404(); return; }
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        $from=' FROM umk_people p LEFT JOIN umk_business_owners o ON o.person_id=p.id AND o.is_primary=1
            LEFT JOIN umk_businesses b ON b.id=o.business_id AND b.is_demo=0 AND b.canonical_business_id IS NULL
            LEFT JOIN ref_districts d ON d.id=b.district_id';
        $where=' WHERE 1=1';$params=array();
        if ($scope!==NULL)
        {
            if (!$scope) $where.=' AND 1=0';
            else
            {
                $marks=implode(',',array_fill(0,count($scope),'?'));
                $where.=' AND (b.district_id IN ('.$marks.') OR (b.id IS NULL AND p.registration_district_id IN ('.$marks.')))';
                foreach($scope as $id) $params[]=$id;
                foreach($scope as $id) $params[]=$id;
            }
        }
        if ($district_id) { $where.=' AND (b.district_id=? OR (b.id IS NULL AND p.registration_district_id=?))';$params[]=$district_id;$params[]=$district_id; }
        if ($search!=='')
        { $where.=' AND (p.full_name LIKE ? OR b.name LIKE ?)';
          $params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        $total=(int)$this->db->query('SELECT COUNT(DISTINCT p.id) n'.$from.$where,$params)->row('n');
        $pages=max(1,(int)ceil($total/$per_page));
        $page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $rows=$this->db->query('SELECT p.id,p.full_name,COUNT(DISTINCT b.id) activity_count,
            COUNT(DISTINCT s.id) store_count,GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR ", ") districts'.
            $from.' LEFT JOIN tko_stores s ON s.business_id=b.id'.$where.
            ' GROUP BY p.id,p.full_name ORDER BY p.full_name,p.id LIMIT '.$per_page.
            ' OFFSET '.(($page-1)*$per_page),$params)->result_array();
        $this->load->view('staff/people',compact('user','rows','districts','district_id',
            'search','per_page','total','page','pages'));
    }

    public function person($person_id=0)
    {
        $user=$this->require_user(); if (!$user) return;
        $scope=$this->registry_scope($user);
        if ($scope===FALSE) { show_error('Akses tidak diizinkan.',403); return; }
        $where=' WHERE p.id=?';
        $params=array((int)$person_id);
        if ($scope!==NULL)
        {
            if (!$scope) $where.=' AND 1=0';
            else
            {
                $marks=implode(',',array_fill(0,count($scope),'?'));
                $where.=' AND (b.district_id IN ('.$marks.') OR (b.id IS NULL AND p.registration_district_id IN ('.$marks.')))';
                foreach($scope as $id) $params[]=$id;
                foreach($scope as $id) $params[]=$id;
            }
        }
        $rows=$this->db->query('SELECT p.full_name,b.id,b.name,b.registry_status,
            d.name district_name,v.name village_name,s.id store_id,s.slug store_slug,s.status store_status
            FROM umk_people p LEFT JOIN umk_business_owners o ON o.person_id=p.id AND o.is_primary=1
            LEFT JOIN umk_businesses b ON b.id=o.business_id AND b.is_demo=0 AND b.canonical_business_id IS NULL
            LEFT JOIN ref_districts d ON d.id=b.district_id
            LEFT JOIN ref_villages v ON v.id=b.village_id
            LEFT JOIN tko_stores s ON s.business_id=b.id'.$where.' ORDER BY b.id',$params)->result_array();
        if (!$rows) { show_404(); return; }
        $person_name=$rows[0]['full_name'];
        $rows=array_values(array_filter($rows,function($row){ return $row['id']!==NULL; }));
        $can_edit_person=staff_scope_person($this->db,$user,$person_id,TRUE);
        $this->load->view('staff/person',compact('user','rows','person_name','person_id','can_edit_person'));
    }

    public function registry()
    {
        $user = $this->require_user();
        if (!$user) return;
        $scope = $this->registry_scope($user);
        if ($scope === FALSE) { show_error('Akses tidak diizinkan.', 403); return; }
        $district_id = (int)$this->input->get('district', TRUE);
        $status = (string)$this->input->get('status', TRUE);
        $search = mb_substr(trim((string)$this->input->get('q', TRUE)),0,80,'UTF-8');
        $shop=(string)$this->input->get('shop',TRUE);
        if (!in_array($shop,array('','yes','no'),TRUE)) $shop='';
        $owner_filter=(string)$this->input->get('owner',TRUE);
        if (!in_array($owner_filter,array('','linked','missing'),TRUE)) $owner_filter='';
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        if ($status !== '' && !in_array($status,array('imported_unverified','reviewed','draft'),TRUE))
        { show_404(); return; }
        if ($district_id && $scope !== NULL && !in_array($district_id,$scope,TRUE))
        { show_error('Wilayah di luar tugas Anda.',403); return; }
        $districts = $this->registry_districts($scope);
        if ($district_id && !in_array($district_id,array_map('intval',array_column($districts,'id')),TRUE))
        { show_404(); return; }
        $where = ' WHERE b.is_demo = 0 AND b.canonical_business_id IS NULL';
        $params = array();
        if ($scope !== NULL)
        {
            if (!$scope) $where .= ' AND 1 = 0';
            else
            {
                $where .= ' AND b.district_id IN ('.implode(',',array_fill(0,count($scope),'?')).')';
                foreach ($scope as $id) $params[] = $id;
            }
        }
        if ($district_id) { $where .= ' AND b.district_id = ?'; $params[] = $district_id; }
        if ($status !== '') { $where .= ' AND b.registry_status = ?'; $params[] = $status; }
        if ($shop==='yes') $where.=' AND EXISTS (SELECT 1 FROM tko_stores sx WHERE sx.business_id=b.id)';
        if ($shop==='no') $where.=' AND NOT EXISTS (SELECT 1 FROM tko_stores sx WHERE sx.business_id=b.id)';
        if ($owner_filter==='linked') $where.=' AND EXISTS (SELECT 1 FROM umk_business_owners ox WHERE ox.business_id=b.id AND ox.is_primary=1)';
        if ($owner_filter==='missing') $where.=' AND NOT EXISTS (SELECT 1 FROM umk_business_owners ox WHERE ox.business_id=b.id AND ox.is_primary=1)';
        if ($search !== '')
        {
            $where .= ' AND (b.name LIKE ? OR b.legal_name LIKE ? OR EXISTS
                (SELECT 1 FROM umk_business_owners so JOIN umk_people sp ON sp.id=so.person_id
                 WHERE so.business_id=b.id AND sp.full_name LIKE ?))';
            $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%'; $params[]='%'.$search.'%';
        }
        $total = (int)$this->db->query('SELECT COUNT(*) AS n FROM umk_businesses AS b'.$where,$params)->row('n');
        $actor_count=(int)$this->db->query('SELECT COUNT(DISTINCT o.person_id) n
            FROM umk_businesses b JOIN umk_business_owners o ON o.business_id=b.id AND o.is_primary=1'.$where,$params)->row('n');
        $shop_count=(int)$this->db->query('SELECT COUNT(s.id) n FROM umk_businesses b
            JOIN tko_stores s ON s.business_id=b.id'.$where,$params)->row('n');
        $page = max(1,(int)$this->input->get('page',TRUE));
        $pages = max(1,(int)ceil($total/$per_page));
        $page=min($page,$pages);
        $rows = $this->db->query(
            'SELECT b.id,b.name,b.registry_status,b.created_at,d.name AS district_name,
                    v.name AS village_name,sc.name AS scale_name,s.id AS store_id,
                    (SELECT p.full_name FROM umk_business_owners o JOIN umk_people p ON p.id=o.person_id
                     WHERE o.business_id=b.id AND o.is_primary=1 LIMIT 1) owner_name
             FROM umk_businesses AS b
             LEFT JOIN ref_districts AS d ON d.id=b.district_id
             LEFT JOIN ref_villages AS v ON v.id=b.village_id
             LEFT JOIN ref_business_scales AS sc ON sc.id=b.scale_id
             LEFT JOIN tko_stores AS s ON s.business_id=b.id'.$where.
            ' ORDER BY b.id DESC LIMIT '.$per_page.' OFFSET '.(int)(($page-1)*$per_page),$params)->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/registry',array('user'=>$user,'rows'=>$rows,
            'districts'=>$districts,'selected_district'=>$district_id,'selected_status'=>$status,
            'search'=>$search,'shop'=>$shop,'owner_filter'=>$owner_filter,'actor_count'=>$actor_count,'shop_count'=>$shop_count,
            'page'=>$page,'pages'=>$pages,'total'=>$total,'per_page'=>$per_page));
    }

    public function business($business_id = 0)
    {
        $user = $this->require_user();
        if (!$user) return;
        $scope = $this->registry_scope($user);
        if ($scope === FALSE) { show_error('Akses tidak diizinkan.',403); return; }
        $business = $this->registry_business($business_id,$scope);
        if (!$business) { show_404(); return; }
        $business_id=(int)$business['id'];
        $tab=(string)$this->input->get('tab',TRUE);
        if (!in_array($tab,array('profil','pelaku','klasifikasi','perizinan','lokasi','peninjauan'),TRUE))
            $tab='profil';
        $can_edit=in_array($user['role_code'],array('superadmin','admin_dinas','admin_kecamatan'),TRUE);
        $this->load->helper('registry_pii');
        $address=registry_pii_decrypt($business['address_ciphertext']);
        $districts=$this->registry_districts($scope);
        $villages=$this->db->query('SELECT id,district_id,name FROM ref_villages WHERE is_active=1 ORDER BY name')->result_array();
        $scales=$this->db->query('SELECT id,name FROM ref_business_scales ORDER BY sort_order,id')->result_array();
        $kbli=$this->db->query('SELECT c.code,c.title,v.version_label,k.is_primary
            FROM umk_business_kbli k JOIN ref_kbli_codes c ON c.id=k.kbli_code_id
            JOIN ref_kbli_versions v ON v.id=c.version_id
            WHERE k.business_id=? ORDER BY k.is_primary DESC,c.code',array($business_id))->result_array();
        $commodities=$this->db->query('SELECT id,commodity_name,kbki_code,kbki_version,
            kbki_review_status,is_primary FROM umk_business_commodities
            WHERE business_id=? ORDER BY is_primary DESC,id',array($business_id))->result_array();
        $licenses = $this->db->query(
            'SELECT license_type,license_number,status,verified_at,evidence_basis,verification_note
             FROM umk_business_licenses WHERE business_id=? ORDER BY id DESC LIMIT 10',
            array($business_id))->result_array();
        $nib_documents=$this->db->query("SELECT id,mime_type,created_at
            FROM umk_documents WHERE business_id=? AND document_type='NIB'
            ORDER BY id DESC LIMIT 20",array($business_id))->result_array();
        $store = $this->db->query('SELECT id,slug,status FROM tko_stores WHERE business_id=? LIMIT 1',
                                  array($business_id))->row_array();
        $owner=$this->db->query('SELECT p.id,p.full_name,p.phone_e164,p.nik_ciphertext,
            IF(p.nik_fingerprint IS NULL,0,1) has_nik FROM umk_business_owners o
            JOIN umk_people p ON p.id=o.person_id WHERE o.business_id=? AND o.is_primary=1 LIMIT 1',
            array($business_id))->row_array();
        if ($owner)
        {
            $nik=registry_pii_decrypt($owner['nik_ciphertext']);
            $owner['nik_hint']=$nik?'•••• '.substr($nik,-4):NULL;
            unset($owner['nik_ciphertext']);
        }
        $can_edit_person=$can_edit && (!$owner || staff_scope_person($this->db,$user,$owner['id'],TRUE));
        $related_where=$scope===NULL?'':($scope?' AND b.district_id IN ('.implode(',',array_map('intval',$scope)).')':' AND 1=0');
        $related=$owner?$this->db->query('SELECT b.id,b.name,s.id store_id
            FROM umk_business_owners o JOIN umk_businesses b ON b.id=o.business_id
            LEFT JOIN tko_stores s ON s.business_id=b.id
            WHERE o.person_id=? AND o.is_primary=1 AND b.canonical_business_id IS NULL
              AND b.is_demo=0 AND b.id<>?'.$related_where.' ORDER BY b.id LIMIT 30',
            array($owner['id'],$business_id))->result_array():array();
        $match_candidates=array();$match_total=0;
        if (in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        {
            $match_total=(int)$this->db->query("SELECT COUNT(*) n FROM umk_person_match_candidates
                WHERE business_id=? AND status='pending'",array($business_id))->row('n');
            if ($match_total)
                $match_candidates=$this->db->query("SELECT c.id,c.match_basis,p.id person_id,p.full_name,
                    COUNT(DISTINCT b.id) activity_count,
                    GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR ', ') districts
                    FROM umk_person_match_candidates c JOIN umk_people p ON p.id=c.person_id
                    JOIN umk_business_owners o ON o.person_id=p.id AND o.is_primary=1
                    JOIN umk_businesses b ON b.id=o.business_id AND b.canonical_business_id IS NULL
                    LEFT JOIN ref_districts d ON d.id=b.district_id
                    WHERE c.business_id=? AND c.status='pending'
                    GROUP BY c.id,c.match_basis,p.id,p.full_name
                    ORDER BY FIELD(c.match_basis,'name_address','name_district','name_only'),c.id LIMIT 10",
                    array($business_id))->result_array();
        }
        $location = $this->db->query('SELECT latitude,longitude,visibility,checked_at,updated_at
            FROM gis_business_locations WHERE business_id=? LIMIT 1',array($business_id))->row_array();
        $location_candidates = $this->db->query("SELECT place_name,source_name,source_url,latitude,longitude,match_status
            FROM gis_location_candidates WHERE business_id=? AND match_status='candidate'
            ORDER BY detected_at DESC,id DESC LIMIT 5",array($business_id))->result_array();
        $imported_account = $this->db->query('SELECT u.email,u.status FROM iam_imported_business_accounts a
            JOIN iam_users u ON u.id=a.user_id WHERE a.business_id=? LIMIT 1',
            array($business_id))->row_array();
        $notes = $this->db->query(
            'SELECT n.note_text,n.created_at,u.full_name FROM umk_review_notes AS n
             JOIN iam_users AS u ON u.id=n.author_id WHERE n.business_id=?
             ORDER BY n.id DESC LIMIT 20',array($business_id))->result_array();
        $updates = $this->db->query('SELECT r.id,r.proposed_name,r.proposed_description,r.status,
            r.decision_note,r.created_at,r.reviewed_at,u.email AS account_email
            FROM umk_profile_update_requests r JOIN iam_users u ON u.id=r.user_id
            WHERE r.business_id=? ORDER BY r.id DESC LIMIT 20',array($business_id))->result_array();
        $this->output->set_content_type('text/html','utf-8');
        $this->load->view('staff/business',array('user'=>$user,'business'=>$business,
            'address'=>$address,'districts'=>$districts,'villages'=>$villages,'scales'=>$scales,
            'kbli'=>$kbli,'commodities'=>$commodities,'can_edit'=>$can_edit,'tab'=>$tab,
            'licenses'=>$licenses,'nib_documents'=>$nib_documents,'store'=>$store,
            'notes'=>$notes,'updates'=>$updates,
            'imported_account'=>$imported_account,'owner'=>$owner,'can_edit_person'=>$can_edit_person,'related'=>$related,
            'match_candidates'=>$match_candidates,'match_total'=>$match_total,
            'location'=>$location,'location_candidates'=>$location_candidates,
            'saved'=>(bool)$this->input->get('catatan',TRUE)));
    }

    public function business_location($business_id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->require_user(); if (!$user) return;
        $scope=$this->registry_scope($user);
        if ($scope===FALSE) { show_error('Akses tidak diizinkan.',403); return; }
        $business=$this->registry_business($business_id,$scope);
        if (!$business) { show_404(); return; }
        $business_id=(int)$business['id'];
        $latitude=trim((string)$this->input->post('latitude',TRUE));
        $longitude=trim((string)$this->input->post('longitude',TRUE));
        $source=trim((string)$this->input->post('source_note',TRUE));
        $checked=(int)$this->input->post('checked',TRUE)===1;
        if (!is_numeric($latitude) || !is_numeric($longitude) ||
            (float)$latitude < -7.3 || (float)$latitude > -6.3 ||
            (float)$longitude < 110.8 || (float)$longitude > 111.8 ||
            mb_strlen($source,'UTF-8')<10 || mb_strlen($source,'UTF-8')>500)
        { show_error('Periksa koordinat Rembang dan catatan sumber minimal 10 karakter.',400); return; }
        if ($checked && !in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        { show_error('Pemeriksaan koordinat hanya untuk admin Dinas.',403); return; }
        $current=$this->db->query('SELECT visibility FROM gis_business_locations WHERE business_id=? LIMIT 1',
            array($business_id))->row_array();
        if ($current && $current['visibility']==='public')
        { show_error('Lokasi publik harus diubah melalui pengaturan lokasi toko.',409); return; }
        $this->db->trans_begin();
        $this->db->query("INSERT INTO gis_business_locations
            (business_id,latitude,longitude,visibility,checked_at,updated_by)
            VALUES (?,?,?,'hidden',IF(?=1,NOW(),NULL),?)
            ON DUPLICATE KEY UPDATE latitude=VALUES(latitude),longitude=VALUES(longitude),
                visibility='hidden',checked_at=VALUES(checked_at),updated_by=VALUES(updated_by)",
            array($business_id,(float)$latitude,(float)$longitude,$checked?1:0,$user['id']));
        $this->Staff_model->audit($user['id'],'gis.registry_location_saved','gis_business_locations',
            $business_id,($checked?'Diperiksa; ':'Belum diperiksa; ').$source);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Koordinat gagal disimpan.',500); return; }
        $this->db->trans_commit();
        redirect('pengelola/registri/'.$business_id.'?koordinat=1','location',303);
    }

    public function person_match($business_id=0,$candidate_id=0)
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->require_user(); if (!$user) return;
        if (!in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE))
        { show_error('Keputusan kecocokan hanya untuk admin Dinas.',403); return; }
        $business=$this->registry_business($business_id,NULL);
        if (!$business) { show_404(); return; }
        $business_id=(int)$business['id'];
        $decision=(string)$this->input->post('decision',TRUE);
        $note=trim((string)$this->input->post('decision_note',TRUE));
        if (!in_array($decision,array('confirmed','rejected'),TRUE) ||
            mb_strlen($note,'UTF-8')<15 || mb_strlen($note,'UTF-8')>500)
        { show_error('Isi keputusan dan alasan pemeriksaan 15 sampai 500 karakter.',400); return; }
        $this->db->trans_begin();
        $candidate=$this->db->query('SELECT id,person_id,status FROM umk_person_match_candidates
            WHERE id=? AND business_id=? FOR UPDATE',array((int)$candidate_id,$business_id))->row_array();
        if (!$candidate || $candidate['status']!=='pending')
        { $this->db->trans_rollback(); show_error('Calon kecocokan tidak tersedia.',409); return; }
        if ($decision==='confirmed')
        {
            $this->db->query('DELETE FROM umk_business_owners
                WHERE business_id=? AND is_primary=1 AND person_id<>?',
                array($business_id,$candidate['person_id']));
            $this->db->query("INSERT INTO umk_business_owners
                (business_id,person_id,relationship,is_primary) VALUES (?,?,'owner',1)
                ON DUPLICATE KEY UPDATE is_primary=1",
                array($business_id,$candidate['person_id']));
            $this->Staff_model->sync_imported_account_name($business_id);
            $this->db->query("UPDATE umk_person_match_candidates SET status='closed',
                decided_at=NOW(),decided_by=?,decision_note='Calon lain ditutup setelah hubungan dikonfirmasi.'
                WHERE business_id=? AND status='pending' AND id<>?",
                array($user['id'],$business_id,$candidate['id']));
        }
        $this->db->query('UPDATE umk_person_match_candidates
            SET status=?,decision_note=?,decided_at=NOW(),decided_by=? WHERE id=?',
            array($decision,$note,$user['id'],$candidate['id']));
        $this->Staff_model->audit($user['id'],'umk.person_match_'.$decision,
            'umk_person_match_candidates',$candidate['id']);
        if ($this->db->trans_status()===FALSE)
        { $this->db->trans_rollback(); show_error('Keputusan gagal disimpan.',500); return; }
        $this->db->trans_commit();
        redirect('pengelola/registri/'.$business_id.'?kecocokan=1','location',303);
    }

    public function business_note($business_id = 0)
    {
        if ($this->input->method(TRUE) !== 'POST') { show_404(); return; }
        $user = $this->require_user();
        if (!$user) return;
        $scope = $this->registry_scope($user);
        if ($scope === FALSE) { show_error('Akses tidak diizinkan.',403); return; }
        $business = $this->registry_business($business_id,$scope);
        if (!$business) { show_404(); return; }
        $business_id=(int)$business['id'];
        $note = trim((string)$this->input->post('note',TRUE));
        if (mb_strlen($note,'UTF-8') < 10 || mb_strlen($note,'UTF-8') > 1000)
        { show_error('Catatan harus berisi 10 sampai 1.000 karakter.',400); return; }
        $this->db->trans_begin();
        $this->db->query('INSERT INTO umk_review_notes (business_id,author_id,note_text) VALUES (?,?,?)',
                         array($business_id,$user['id'],$note));
        $note_id = (int)$this->db->insert_id();
        $this->Staff_model->audit($user['id'],'umk.review_note','umk_review_notes',$note_id,
                                  'Catatan peninjauan usaha #'.$business_id);
        if ($this->db->trans_status() === FALSE)
        { $this->db->trans_rollback(); show_error('Catatan gagal disimpan.',500); return; }
        $this->db->trans_commit();
        redirect('pengelola/registri/'.$business_id.'?catatan=1','location',303);
    }

    public function logout()
    {
        if ($this->input->method(TRUE) !== 'POST')
        {
            show_404();
            return;
        }
        $user = $this->current_user();
        if ($user) $this->Staff_model->audit($user['id'], 'staff.logout', 'iam_users', $user['id']);
        $this->session->unset_userdata('staff_user_id');
        $this->session->unset_userdata('staff_auth_epoch');
        $this->session->sess_regenerate(TRUE);
        redirect('pengelola/masuk', 'location', 303);
    }

    private function current_user()
    {
        $id = (int) $this->session->userdata('staff_user_id');
        if ($id < 1) return NULL;
        $user = $this->Staff_model->by_id($id);
        return $user && $user['status'] === 'active' ? $user : NULL;
    }

    private function require_user()
    {
        $user = $this->current_user();
        if (!$user) redirect('pengelola/masuk', 'location', 302);
        return $user;
    }

    private function registry_scope($user)
    {
        return staff_scope_districts($this->db,$user);
    }

    private function registry_districts($scope)
    {
        if ($scope === NULL)
            return $this->db->query('SELECT id,name FROM ref_districts WHERE is_active=1 ORDER BY name')->result_array();
        if (!$scope) return array();
        return $this->db->query('SELECT id,name FROM ref_districts WHERE is_active=1 AND id IN ('.
            implode(',',array_fill(0,count($scope),'?')).') ORDER BY name',$scope)->result_array();
    }

    private function registry_business($business_id,$scope)
    {
        if ((int)$business_id < 1 || $scope === FALSE) return NULL;
        $alias=$this->db->query('SELECT COALESCE(canonical_business_id,id) id
            FROM umk_businesses WHERE id=? LIMIT 1',array((int)$business_id))->row_array();
        if (!$alias) return NULL;
        $where = ' WHERE b.id=? AND b.is_demo=0 AND b.canonical_business_id IS NULL';
        $params = array((int)$alias['id']);
        if ($scope !== NULL)
        {
            if (!$scope) return NULL;
            $where .= ' AND b.district_id IN ('.implode(',',array_fill(0,count($scope),'?')).')';
            foreach ($scope as $id) $params[] = $id;
        }
        return $this->db->query(
            'SELECT b.id,b.name,b.legal_name,b.legal_form,b.sector_label,b.description,b.district_id,b.village_id,
                    b.address_ciphertext,b.scale_id,b.started_year,b.employee_count,
                    b.annual_revenue_idr,b.contact_phone_e164,b.contact_source_text,
                    b.website_url,b.social_media_url,b.export_destinations,b.export_potential,
                    b.production_capacity,b.registry_status,b.data_source,
                    b.source_updated_at,b.reviewed_at,b.created_at,b.updated_at,
                    d.name AS district_name,v.name AS village_name,sc.name AS scale_name'.$this->registry_detail_from().$where.' LIMIT 1',
            $params)->row_array();
    }

    private function registry_detail_from()
    {
        return ' FROM umk_businesses AS b
                 LEFT JOIN ref_districts AS d ON d.id=b.district_id
                 LEFT JOIN ref_villages AS v ON v.id=b.village_id
                 LEFT JOIN ref_business_scales AS sc ON sc.id=b.scale_id';
    }

    private function import_area_file($user)
    {
        if (!isset($_FILES['geojson']) || $_FILES['geojson']['error'] !== UPLOAD_ERR_OK ||
            !is_uploaded_file($_FILES['geojson']['tmp_name']) || $_FILES['geojson']['size'] > 5 * 1024 * 1024)
        {
            throw new InvalidArgumentException('Pilih berkas GeoJSON maksimal 5 MB.');
        }
        $source = basename($_FILES['geojson']['name']);
        if (!preg_match('/\.(geojson|json)$/i', $source))
        {
            throw new InvalidArgumentException('Berkas harus berekstensi .geojson atau .json.');
        }
        $source = mb_substr($source, 0, 190, 'UTF-8');
        $data = json_decode(file_get_contents($_FILES['geojson']['tmp_name']), TRUE);
        $this->load->library('Geojson_area');
        $features = $this->geojson_area->validate($data);
        $this->db->trans_begin();
        foreach ($features as $feature)
        {
            $table = $feature['type'] === 'district' ? 'ref_districts' : 'ref_villages';
            $exists = $this->db->query('SELECT id FROM '.$table.' WHERE code = ? LIMIT 1', array($feature['code']))->row_array();
            if (!$exists)
            {
                $this->db->trans_rollback();
                throw new InvalidArgumentException('Kode wilayah belum ada pada master: '.$feature['code']);
            }
            $this->db->query(
                'INSERT INTO gis_area_boundaries (area_type, area_code, geometry_json, source_name, imported_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE geometry_json = VALUES(geometry_json), source_name = VALUES(source_name),
                                         imported_by = VALUES(imported_by), imported_at = NOW()',
                array($feature['type'], $feature['code'], json_encode($feature['geometry'], JSON_UNESCAPED_UNICODE),
                      $source, $user['id'])
            );
        }
        if ($this->db->trans_status() === FALSE)
        {
            $this->db->trans_rollback();
            throw new RuntimeException('Penyimpanan gagal.');
        }
        $this->Staff_model->audit($user['id'], 'gis.area_import', 'gis_area_boundaries', NULL,
                                  $source.' ('.count($features).' wilayah)');
        $this->db->trans_commit();
        return count($features);
    }
}
