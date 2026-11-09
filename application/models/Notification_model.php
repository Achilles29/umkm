<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notification_model extends CI_Model
{
    public function admin_ids()
    {
        $rows=$this->db->query("SELECT DISTINCT u.id FROM iam_users u
            JOIN iam_user_roles ur ON ur.user_id=u.id
            JOIN iam_roles r ON r.id=ur.role_id
            WHERE u.status='active' AND r.code IN ('superadmin','admin_dinas')")->result_array();
        return array_map('intval',array_column($rows,'id'));
    }

    public function store_manager_ids($store_id)
    {
        $rows=$this->db->query("SELECT DISTINCT u.id FROM tko_store_managers m
            JOIN iam_users u ON u.id=m.user_id AND u.status='active'
            WHERE m.store_id=? AND m.status='active'",array((int)$store_id))->result_array();
        return array_map('intval',array_column($rows,'id'));
    }

    public function district_admin_ids($store_id)
    {
        $rows=$this->db->query("SELECT DISTINCT u.id FROM tko_stores s
            JOIN umk_businesses b ON b.id=s.business_id
            JOIN iam_district_roles dr ON dr.district_id=b.district_id
            JOIN iam_roles r ON r.id=dr.role_id AND r.code='admin_kecamatan'
            JOIN iam_users u ON u.id=dr.user_id AND u.status='active'
            WHERE s.id=?",array((int)$store_id))->result_array();
        return array_map('intval',array_column($rows,'id'));
    }

    public function publish($type,$key,$title,$body,$path,array $recipients)
    {
        if (!preg_match('/^\/pengelola(?:\/[a-z0-9_-]+)*$/',(string)$path))
            throw new InvalidArgumentException('Tujuan notifikasi tidak valid.');
        $recipients=array_values(array_unique(array_filter(array_map('intval',$recipients))));
        if (!$recipients) return NULL;
        $this->db->query('INSERT INTO ntf_events (event_type,dedupe_key,title,body,target_path)
            VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
            array(mb_substr($type,0,40,'UTF-8'),mb_substr($key,0,120,'UTF-8'),
                  mb_substr($title,0,160,'UTF-8'),mb_substr($body,0,1000,'UTF-8'),$path));
        $event_id=(int)$this->db->insert_id();
        foreach($recipients as $user_id)
            $this->db->query('INSERT IGNORE INTO ntf_inbox_items (event_id,user_id) VALUES (?,?)',array($event_id,$user_id));
        return $event_id;
    }

    public function unread_count($user_id)
    {
        return (int)$this->db->query('SELECT COUNT(*) n FROM ntf_inbox_items WHERE user_id=? AND read_at IS NULL',array((int)$user_id))->row('n');
    }

    public function count_items($user_id,$unread_only=FALSE,$search='')
    {
        $where=$unread_only?' AND i.read_at IS NULL':'';
        $params=array((int)$user_id);
        if ($search!=='') { $where.=' AND (e.title LIKE ? OR e.body LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        return (int)$this->db->query('SELECT COUNT(*) n FROM ntf_inbox_items i JOIN ntf_events e ON e.id=i.event_id WHERE i.user_id=?'.$where,$params)->row('n');
    }

    public function items($user_id,$unread_only,$limit,$offset,$search='')
    {
        $where=$unread_only?' AND i.read_at IS NULL':'';
        $params=array((int)$user_id);
        if ($search!=='') { $where.=' AND (e.title LIKE ? OR e.body LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%'; }
        return $this->db->query('SELECT e.id,e.event_type,e.title,e.body,e.target_path,e.occurred_at,i.read_at
            FROM ntf_inbox_items i JOIN ntf_events e ON e.id=i.event_id
            WHERE i.user_id=?'.$where.' ORDER BY e.occurred_at DESC,e.id DESC LIMIT '.(int)$limit.' OFFSET '.(int)$offset,
            $params)->result_array();
    }

    public function read_item($user_id,$event_id)
    {
        $row=$this->db->query('SELECT e.target_path FROM ntf_inbox_items i JOIN ntf_events e ON e.id=i.event_id WHERE i.user_id=? AND i.event_id=? LIMIT 1',array((int)$user_id,(int)$event_id))->row_array();
        if (!$row) return NULL;
        $this->db->query('UPDATE ntf_inbox_items SET read_at=COALESCE(read_at,NOW()) WHERE user_id=? AND event_id=?',array((int)$user_id,(int)$event_id));
        return $row['target_path'];
    }

    public function read_all($user_id)
    {
        $this->db->query('UPDATE ntf_inbox_items SET read_at=NOW() WHERE user_id=? AND read_at IS NULL',array((int)$user_id));
    }
}
