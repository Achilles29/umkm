<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master_data extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Staff_model');
    }

    private function actor($write=FALSE)
    {
        $id=(int)$this->session->userdata('staff_user_id');
        if (!$id) { redirect('pengelola/masuk','location',302); return NULL; }
        $user=$this->Staff_model->by_id($id);
        $roles=$write?array('superadmin','admin_dinas'):array('superadmin','admin_dinas','admin_kecamatan');
        if (!$user || $user['status']!=='active' || !in_array($user['role_code'],$roles,TRUE))
        { show_error('Akses tidak diizinkan.',403); return NULL; }
        return $user;
    }

    private function filters()
    {
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        return array($search,$per_page);
    }

    private function page($total,$per_page)
    {
        $pages=max(1,(int)ceil($total/$per_page));
        $page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        return array($page,$pages,(int)(($page-1)*$per_page));
    }

    public function index()
    {
        $user=$this->actor(); if (!$user) return;
        $counts=array();
        foreach(array('ref_categories','ref_business_scales','ref_kbli_codes','ref_districts','ref_villages','gis_area_boundaries') as $table)
            $counts[$table]=(int)$this->db->query('SELECT COUNT(*) n FROM '.$table)->row('n');
        $this->load->view('staff/master_home',compact('user','counts'));
    }

    public function categories()
    {
        $user=$this->actor(); if (!$user) return;
        list($search,$per_page)=$this->filters();
        $status=(string)$this->input->get('status',TRUE);
        if (!in_array($status,array('','active','inactive'),TRUE)) $status='';
        $where=" WHERE c.category_type='store'";$params=array();
        if ($search!=='') { $where.=' AND (c.name LIKE ? OR c.slug LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        if ($status!=='') { $where.=' AND c.is_active=?';$params[]=$status==='active'?1:0; }
        $from=' FROM ref_categories c LEFT JOIN ref_categories p ON p.id=c.parent_id';
        $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
        list($page,$pages,$offset)=$this->page($total,$per_page);
        $rows=$this->db->query('SELECT c.id,c.name,c.slug,c.description,c.parent_id,c.sort_order,c.is_active,p.name parent_name'.$from.$where.
            ' ORDER BY COALESCE(p.sort_order,c.sort_order),COALESCE(p.name,c.name),c.parent_id IS NOT NULL,c.sort_order,c.name LIMIT '.$per_page.' OFFSET '.$offset,$params)->result_array();
        $parents=$this->db->query("SELECT id,name FROM ref_categories WHERE category_type='store' AND parent_id IS NULL ORDER BY sort_order,name")->result_array();
        $edit_id=(int)$this->input->get('edit',TRUE);
        $edit=$edit_id?$this->db->query("SELECT id,name,slug,description,parent_id,sort_order,is_active FROM ref_categories WHERE id=? AND category_type='store'",array($edit_id))->row_array():NULL;
        $error=(string)$this->session->flashdata('master_error');
        $this->load->view('staff/master_categories',compact('user','rows','parents','edit','error','search','status','per_page','page','pages','total'));
    }

    public function save_category()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(TRUE); if (!$user) return;
        $id=(int)$this->input->post('id',TRUE);
        $name=trim((string)$this->input->post('name',TRUE));
        $slug=trim((string)$this->input->post('slug',TRUE));
        $description=trim((string)$this->input->post('description',TRUE));
        $parent_id=(int)$this->input->post('parent_id',TRUE);
        $sort_order=(int)$this->input->post('sort_order',TRUE);
        $active=(int)$this->input->post('is_active',TRUE);
        $back='pengelola/master/kategori'.($id?'?edit='.$id:'');
        $started=FALSE;
        try
        {
            if (mb_strlen($name,'UTF-8')<2 || mb_strlen($name,'UTF-8')>120 ||
                mb_strlen($description,'UTF-8')>255 || $sort_order<0 || $sort_order>9999 || !in_array($active,array(0,1),TRUE))
                throw new InvalidArgumentException('Periksa nama, deskripsi, urutan, dan status kategori.');
            $current=NULL;
            if ($id)
            {
                $current=$this->db->query("SELECT id,slug,parent_id FROM ref_categories WHERE id=? AND category_type='store'",array($id))->row_array();
                if (!$current) throw new InvalidArgumentException('Kategori tidak ditemukan.');
                $slug=$current['slug'];
            }
            elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug) || strlen($slug)>100)
                throw new InvalidArgumentException('Slug hanya boleh huruf kecil, angka, dan tanda hubung.');
            if (!$id && $this->db->query("SELECT 1 FROM ref_categories WHERE category_type='store' AND slug=? LIMIT 1",array($slug))->row())
                throw new InvalidArgumentException('Slug kategori sudah dipakai.');
            if ($parent_id)
            {
                $parent=$this->db->query("SELECT id FROM ref_categories WHERE id=? AND category_type='store' AND parent_id IS NULL AND is_active=1",array($parent_id))->row_array();
                if (!$parent || $parent_id===$id) throw new InvalidArgumentException('Induk kategori tidak valid.');
                if ($id && $this->db->query('SELECT 1 FROM ref_categories WHERE parent_id=? LIMIT 1',array($id))->row())
                    throw new InvalidArgumentException('Kategori yang memiliki anak tidak dapat dijadikan anak kategori lain.');
            }
            if (!$active && $id)
            {
                if ($this->db->query('SELECT 1 FROM ref_categories WHERE parent_id=? AND is_active=1 LIMIT 1',array($id))->row())
                    throw new InvalidArgumentException('Nonaktifkan anak kategori terlebih dahulu.');
                $used=$this->db->query('SELECT 1 FROM tko_store_categories WHERE category_id=? LIMIT 1',array($id))->row()
                    || $this->db->query('SELECT 1 FROM prd_product_categories WHERE category_id=? LIMIT 1',array($id))->row();
                if ($used) throw new InvalidArgumentException('Kategori yang dipakai toko atau produk tidak dapat dinonaktifkan.');
            }
            $this->db->trans_begin();
            $started=TRUE;
            if ($id)
                $this->db->query('UPDATE ref_categories SET name=?,description=?,parent_id=?,sort_order=?,is_active=? WHERE id=?',
                    array($name,$description?:NULL,$parent_id?:NULL,$sort_order,$active,$id));
            else
            {
                $this->db->query("INSERT INTO ref_categories (parent_id,category_type,slug,name,description,sort_order,is_active) VALUES (?,'store',?,?,?,?,?)",
                    array($parent_id?:NULL,$slug,$name,$description?:NULL,$sort_order,$active));
                $id=(int)$this->db->insert_id();
            }
            $this->Staff_model->audit($user['id'],'ref.category_saved','ref_categories',$id);
            if ($this->db->trans_status()===FALSE) throw new RuntimeException('Kategori gagal disimpan.');
            $this->db->trans_commit();
            $started=FALSE;
            redirect('pengelola/master/kategori?tersimpan=1','location',303);
        }
        catch(Exception $e)
        {
            if ($started) $this->db->trans_rollback();
            $this->session->set_flashdata('master_error',$e->getMessage());
            redirect($back,'location',303);
        }
    }

    public function scales()
    {
        $user=$this->actor(); if (!$user) return;
        $rows=$this->db->query('SELECT sc.id,sc.code,sc.name,sc.sort_order,COUNT(b.id) business_count
            FROM ref_business_scales sc LEFT JOIN umk_businesses b ON b.scale_id=sc.id AND b.is_demo=0 AND b.canonical_business_id IS NULL
            GROUP BY sc.id ORDER BY sc.sort_order,sc.id')->result_array();
        $this->load->view('staff/master_scales',compact('user','rows'));
    }

    public function kbli()
    {
        $user=$this->actor(); if (!$user) return;
        list($search,$per_page)=$this->filters();
        $versions=$this->db->query('SELECT v.id,v.version_label,v.legal_basis,v.effective_date,v.is_current,COUNT(c.id) code_count
            FROM ref_kbli_versions v LEFT JOIN ref_kbli_codes c ON c.version_id=v.id
            GROUP BY v.id ORDER BY v.is_current DESC,v.effective_date DESC,v.id DESC')->result_array();
        $version_id=(int)$this->input->get('version',TRUE);
        if (!$version_id && $versions) $version_id=(int)$versions[0]['id'];
        if ($version_id && !in_array($version_id,array_map('intval',array_column($versions,'id')),TRUE)) { show_404(); return; }
        $where=' WHERE 1=1';$params=array();
        if ($version_id) { $where.=' AND c.version_id=?';$params[]=$version_id; }
        if ($search!=='') { $where.=' AND (c.code LIKE ? OR c.title LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        $from=' FROM ref_kbli_codes c JOIN ref_kbli_versions v ON v.id=c.version_id';
        $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
        list($page,$pages,$offset)=$this->page($total,$per_page);
        $rows=$this->db->query('SELECT c.code,c.title,c.parent_code,v.version_label'.$from.$where.' ORDER BY c.code LIMIT '.$per_page.' OFFSET '.$offset,$params)->result_array();
        $error=(string)$this->session->flashdata('master_error');
        $this->load->view('staff/master_kbli',compact('user','rows','versions','version_id','error','search','per_page','page','pages','total'));
    }

    public function save_kbli_version()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(TRUE); if (!$user) return;
        $label=trim((string)$this->input->post('version_label',TRUE));
        $basis=trim((string)$this->input->post('legal_basis',TRUE));
        $date=trim((string)$this->input->post('effective_date',TRUE));
        if (!preg_match('/^KBLI [0-9]{4}$/',$label) || mb_strlen($basis,'UTF-8')>255 || ($date!=='' && !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$date)))
        { $this->session->set_flashdata('master_error','Periksa versi, dasar hukum, dan tanggal berlaku.');redirect('pengelola/master/kbli','location',303);return; }
        if ($this->db->query('SELECT 1 FROM ref_kbli_versions WHERE version_label=? LIMIT 1',array($label))->row())
        { $this->session->set_flashdata('master_error','Versi tersebut sudah ada.');redirect('pengelola/master/kbli','location',303);return; }
        $this->db->trans_begin();
        $this->db->query('INSERT INTO ref_kbli_versions (version_label,legal_basis,effective_date,is_current) VALUES (?,?,?,0)',
            array($label,$basis?:NULL,$date?:NULL));
        $id=(int)$this->db->insert_id();
        $this->Staff_model->audit($user['id'],'ref.kbli_version_added','ref_kbli_versions',$id);
        if ($this->db->trans_status()===FALSE) { $this->db->trans_rollback();show_error('Versi gagal disimpan.',500);return; }
        $this->db->trans_commit();
        redirect('pengelola/master/kbli?version='.$id,'location',303);
    }

    public function import_kbli()
    {
        if ($this->input->method(TRUE)!=='POST') { show_404(); return; }
        $user=$this->actor(TRUE); if (!$user) return;
        $version_id=(int)$this->input->post('version_id',TRUE);
        $back='pengelola/master/kbli?version='.$version_id;
        $started=FALSE;
        try
        {
            if (!$this->db->query('SELECT 1 FROM ref_kbli_versions WHERE id=? LIMIT 1',array($version_id))->row())
                throw new InvalidArgumentException('Versi KBLI tidak tersedia.');
            if (!isset($_FILES['kbli_csv']) || $_FILES['kbli_csv']['error']!==UPLOAD_ERR_OK ||
                !is_uploaded_file($_FILES['kbli_csv']['tmp_name']) || $_FILES['kbli_csv']['size']>2*1024*1024 ||
                !preg_match('/\.csv$/i',$_FILES['kbli_csv']['name']))
                throw new InvalidArgumentException('Pilih berkas CSV maksimal 2 MB.');
            $handle=fopen($_FILES['kbli_csv']['tmp_name'],'r');
            if (!$handle) throw new RuntimeException('CSV tidak dapat dibaca.');
            $header=fgetcsv($handle,0,',','"','');
            if (!$header) throw new InvalidArgumentException('CSV kosong.');
            $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
            if (array_slice($header,0,2)!==array('code','title') ||
                count($header)!==count(array_unique($header)) ||
                array_diff($header,array('code','title','parent_code','description')))
                throw new InvalidArgumentException('Kolom pertama CSV harus code,title. Kolom opsional: parent_code,description.');
            $rows=array();
            while (($data=fgetcsv($handle,0,',','"',''))!==FALSE)
            {
                if (count($data)===1 && trim($data[0])==='') continue;
                if (count($data)>count($header)) throw new InvalidArgumentException('Ada baris CSV dengan kolom berlebih.');
                $row=array_combine($header,array_pad($data,count($header),''));
                $code=trim($row['code']);$title=trim($row['title']);
                $parent=trim(isset($row['parent_code'])?$row['parent_code']:'');
                $description=trim(isset($row['description'])?$row['description']:'');
                if (!preg_match('/^[0-9]{5}$/',$code) || mb_strlen($title,'UTF-8')<2 || mb_strlen($title,'UTF-8')>255 ||
                    ($parent!=='' && !preg_match('/^[0-9]{4}$/',$parent)) || mb_strlen($description,'UTF-8')>10000)
                    throw new InvalidArgumentException('Kode atau judul KBLI tidak valid pada CSV.');
                if (isset($rows[$code])) throw new InvalidArgumentException('Kode KBLI berulang dalam CSV: '.$code);
                $rows[$code]=array($code,$title,$parent?:substr($code,0,4),$description?:NULL);
                if (count($rows)>2500) throw new InvalidArgumentException('CSV melebihi 2.500 kode.');
            }
            fclose($handle);
            if (!$rows) throw new InvalidArgumentException('CSV tidak berisi kode.');
            $this->db->trans_begin();$started=TRUE;
            foreach($rows as $row)
                $this->db->query('INSERT INTO ref_kbli_codes (version_id,code,title,parent_code,description)
                    VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),parent_code=VALUES(parent_code),description=VALUES(description)',
                    array($version_id,$row[0],$row[1],$row[2],$row[3]));
            $this->Staff_model->audit($user['id'],'ref.kbli_codes_imported','ref_kbli_versions',$version_id,
                count($rows).' kode; berkas '.mb_substr(basename($_FILES['kbli_csv']['name']),0,90,'UTF-8'));
            if ($this->db->trans_status()===FALSE) throw new RuntimeException('Impor kode gagal.');
            $this->db->trans_commit();$started=FALSE;
            redirect($back.'&diimpor='.count($rows),'location',303);
        }
        catch(Exception $e)
        {
            if ($started) $this->db->trans_rollback();
            $this->session->set_flashdata('master_error',$e->getMessage());
            redirect($back,'location',303);
        }
    }

    public function territory()
    {
        $user=$this->actor(); if (!$user) return;
        list($search,$per_page)=$this->filters();
        $level=(string)$this->input->get('level',TRUE);
        if (!in_array($level,array('province','regency','district','village'),TRUE)) $level='village';
        $district_id=(int)$this->input->get('district',TRUE);
        $districts=$this->db->query('SELECT id,code,name FROM ref_districts ORDER BY name')->result_array();
        if ($district_id && !in_array($district_id,array_map('intval',array_column($districts,'id')),TRUE)) { show_404(); return; }
        $config=array(
            'province'=>array('ref_provinces p','p.code','p.name',"''"),
            'regency'=>array('ref_regencies r','r.code','r.name','p.name'),
            'district'=>array('ref_districts d','d.code','d.name','r.name'),
            'village'=>array('ref_villages v','v.code','v.name','d.name')
        );
        $c=$config[$level];$from=' FROM '.$c[0];
        if ($level==='regency') $from.=' JOIN ref_provinces p ON p.id=r.province_id';
        if ($level==='district') $from.=' JOIN ref_regencies r ON r.id=d.regency_id';
        if ($level==='village') $from.=' JOIN ref_districts d ON d.id=v.district_id';
        $where=' WHERE 1=1';$params=array();
        if ($level==='village' && $district_id) { $where.=' AND v.district_id=?';$params[]=$district_id; }
        if ($search!=='') { $where.=' AND ('.$c[1].' LIKE ? OR '.$c[2].' LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        $total=(int)$this->db->query('SELECT COUNT(*) n'.$from.$where,$params)->row('n');
        list($page,$pages,$offset)=$this->page($total,$per_page);
        $kind=$level==='village'?',v.kind':'';
        $rows=$this->db->query('SELECT '.$c[1].' code,'.$c[2].' name,'.$c[3].' parent_name'.$kind.$from.$where.' ORDER BY '.$c[1].' LIMIT '.$per_page.' OFFSET '.$offset,$params)->result_array();
        $this->load->view('staff/master_territory',compact('user','rows','districts','district_id','level','search','per_page','page','pages','total'));
    }
}
