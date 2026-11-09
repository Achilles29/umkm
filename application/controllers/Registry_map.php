<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registry_map extends CI_Controller
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
        if (!$user || $user['status']!=='active' || !in_array($user['role_code'],array('superadmin','admin_dinas','admin_kecamatan'),TRUE))
        { show_error('Akses tidak diizinkan.',403); return NULL; }
        return $user;
    }

    public function index()
    {
        $user=$this->actor(); if (!$user) return;
        $scope=NULL;
        if ($user['role_code']==='admin_kecamatan')
        {
            $assigned=$this->db->query("SELECT DISTINCT dr.district_id FROM iam_district_roles dr
                JOIN iam_roles r ON r.id=dr.role_id AND r.code='admin_kecamatan' WHERE dr.user_id=?",array($user['id']))->result_array();
            $scope=array_map('intval',array_column($assigned,'district_id'));
        }
        $district_sql='SELECT d.id,d.code,d.name FROM ref_districts d WHERE d.is_active=1';
        $district_params=array();
        if ($scope!==NULL)
        {
            $district_sql.=$scope?' AND d.id IN ('.implode(',',array_fill(0,count($scope),'?')).')':' AND 1=0';
            $district_params=$scope?:array();
        }
        $districts=$this->db->query($district_sql.' ORDER BY d.name',$district_params)->result_array();
        $allowed=array_map('intval',array_column($districts,'id'));
        $district_id=(int)$this->input->get('district',TRUE);
        $village_id=(int)$this->input->get('village',TRUE);
        $scale_id=(int)$this->input->get('scale',TRUE);
        $status=(string)$this->input->get('status',TRUE);
        $search=mb_substr(trim((string)$this->input->get('q',TRUE)),0,80,'UTF-8');
        $per_page=(int)$this->input->get('per_page',TRUE);
        if (!in_array($per_page,array(10,20,50,100),TRUE)) $per_page=20;
        if ($district_id && !in_array($district_id,$allowed,TRUE)) { show_error('Wilayah di luar tugas Anda.',403); return; }
        $villages=$district_id?$this->db->query('SELECT id,name FROM ref_villages WHERE district_id=? AND is_active=1 ORDER BY name',array($district_id))->result_array():array();
        if ($village_id && (!$district_id || !in_array($village_id,array_map('intval',array_column($villages,'id')),TRUE)))
        { show_404(); return; }
        $scales=$this->db->query('SELECT id,name FROM ref_business_scales ORDER BY sort_order,id')->result_array();
        if ($scale_id && !in_array($scale_id,array_map('intval',array_column($scales,'id')),TRUE)) { show_404(); return; }
        if (!in_array($status,array('','imported_unverified','reviewed','draft'),TRUE)) { show_404(); return; }

        $where=' WHERE b.is_demo=0 AND b.canonical_business_id IS NULL';$params=array();
        if ($scope!==NULL)
        {
            $where.=$scope?' AND b.district_id IN ('.implode(',',array_fill(0,count($scope),'?')).')':' AND 1=0';
            foreach($scope?:array() as $id) $params[]=$id;
        }
        if ($district_id) { $where.=' AND b.district_id=?';$params[]=$district_id; }
        if ($village_id) { $where.=' AND b.village_id=?';$params[]=$village_id; }
        if ($scale_id) { $where.=' AND b.scale_id=?';$params[]=$scale_id; }
        if ($status!=='') { $where.=' AND b.registry_status=?';$params[]=$status; }
        if ($search!=='') { $where.=' AND (b.name LIKE ? OR b.legal_name LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }

        $summary=$this->db->query('SELECT COUNT(*) total,COUNT(DISTINCT b.village_id) village_count,
            SUM(b.village_id IS NULL) without_village,SUM(b.scale_id IS NULL) without_scale
            FROM umk_businesses b'.$where,$params)->row_array();
        $total=(int)$summary['total'];
        $areas=$this->db->query('SELECT d.id,d.code,d.name,COUNT(b.id) business_count
            FROM umk_businesses b JOIN ref_districts d ON d.id=b.district_id'.$where.
            ' GROUP BY d.id,d.code,d.name ORDER BY business_count DESC,d.name',$params)->result_array();
        $scale_counts=$this->db->query('SELECT COALESCE(sc.name,\'Belum dicatat\') label,COUNT(*) business_count
            FROM umk_businesses b LEFT JOIN ref_business_scales sc ON sc.id=b.scale_id'.$where.
            ' GROUP BY sc.id,sc.name ORDER BY business_count DESC',$params)->result_array();
        $pages=max(1,(int)ceil($total/$per_page));
        $page=max(1,min($pages,(int)$this->input->get('page',TRUE)));
        $rows=$this->db->query('SELECT b.id,b.name,b.registry_status,d.name district_name,v.name village_name,sc.name scale_name
            FROM umk_businesses b JOIN ref_districts d ON d.id=b.district_id
            LEFT JOIN ref_villages v ON v.id=b.village_id
            LEFT JOIN ref_business_scales sc ON sc.id=b.scale_id'.$where.
            ' ORDER BY b.id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page),$params)->result_array();

        // Titik peta berasal dari koordinat usaha; tidak memakai pusat kecamatan.
        // Kandidat lokasi hanya diperlihatkan pada panel internal dengan label jelas.
        $locations=$this->db->query('SELECT b.id,b.name,d.name district_name,v.name village_name,
                gl.latitude,gl.longitude,gl.checked_at
            FROM umk_businesses b JOIN gis_business_locations gl ON gl.business_id=b.id
            JOIN ref_districts d ON d.id=b.district_id
            LEFT JOIN ref_villages v ON v.id=b.village_id'.$where.
            ' AND gl.latitude BETWEEN -7.3 AND -6.3 AND gl.longitude BETWEEN 110.8 AND 111.8
            ORDER BY b.id',$params)->result_array();
        $candidates=$this->db->query('SELECT b.id,b.name,d.name district_name,v.name village_name,
                c.latitude,c.longitude,c.source_name
            FROM umk_businesses b
            JOIN (SELECT business_id,MIN(id) id FROM gis_location_candidates
                  WHERE match_status IN (\'candidate\',\'matched\',\'approved\') GROUP BY business_id) picked ON picked.business_id=b.id
            JOIN gis_location_candidates c ON c.id=picked.id
            LEFT JOIN gis_business_locations gl ON gl.business_id=b.id
            JOIN ref_districts d ON d.id=b.district_id
            LEFT JOIN ref_villages v ON v.id=b.village_id'.$where.
            ' AND (gl.latitude IS NULL OR gl.longitude IS NULL)
              AND c.latitude BETWEEN -7.3 AND -6.3 AND c.longitude BETWEEN 110.8 AND 111.8
            ORDER BY b.id',$params)->result_array();
        $map_points=array();$checked_count=0;
        foreach($locations as $location)
        {
            $checked=$location['checked_at']!==NULL;
            if ($checked) $checked_count++;
            $map_points[]=array('id'=>(int)$location['id'],'name'=>$location['name'],
                'district'=>$location['district_name'],'village'=>$location['village_name'],
                'lat'=>(float)$location['latitude'],'lng'=>(float)$location['longitude'],
                'kind'=>$checked?'checked':'internal');
        }
        foreach($candidates as $candidate)
            $map_points[]=array('id'=>(int)$candidate['id'],'name'=>$candidate['name'],
                'district'=>$candidate['district_name'],'village'=>$candidate['village_name'],
                'lat'=>(float)$candidate['latitude'],'lng'=>(float)$candidate['longitude'],
                'kind'=>'candidate','source'=>$candidate['source_name']);
        $point_count=count($map_points);
        $candidate_count=count($candidates);
        $filters=array('district'=>$district_id,'village'=>$village_id,'scale'=>$scale_id,
            'status'=>$status,'q'=>$search,'per_page'=>$per_page);
        $this->load->view('staff/registry_map',compact('user','districts','villages','scales','district_id',
            'village_id','scale_id','status','search','per_page','summary','total','areas','scale_counts',
            'rows','page','pages','map_points','point_count','checked_count','candidate_count','filters'));
    }
}
