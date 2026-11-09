<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Staff_model extends CI_Model
{
    public function sync_imported_account_name($business_id)
    {
        $this->db->query('UPDATE iam_users u
            JOIN iam_imported_business_accounts a ON a.user_id=u.id
            JOIN umk_businesses source ON source.id=a.business_id
            JOIN umk_business_owners o
              ON o.business_id=COALESCE(source.canonical_business_id,source.id)
                AND o.is_primary=1
            JOIN umk_people p ON p.id=o.person_id
            SET u.full_name=p.full_name
            WHERE COALESCE(source.canonical_business_id,source.id)=?',
            array((int)$business_id));
    }

    public function by_email($email)
    {
        return $this->db->query(
            "SELECT u.id, u.full_name, u.email, u.password_hash, u.status, u.auth_epoch, r.code AS role_code, r.name AS role_name
             FROM iam_users AS u
             JOIN iam_user_roles AS ur ON ur.user_id = u.id
             JOIN iam_roles AS r ON r.id = ur.role_id
             WHERE u.email = ? AND r.code IN ('superadmin','admin_dinas','admin_kecamatan','umkm')
             ORDER BY FIELD(r.code,'superadmin','admin_dinas','admin_kecamatan','umkm') LIMIT 1",
            array($email)
        )->row_array();
    }

    public function by_id($id)
    {
        $user=$this->db->query(
            "SELECT u.id, u.full_name, u.email, u.status, u.auth_epoch, r.code AS role_code, r.name AS role_name
             FROM iam_users AS u
             JOIN iam_user_roles AS ur ON ur.user_id = u.id
             JOIN iam_roles AS r ON r.id = ur.role_id
             WHERE u.id = ? AND r.code IN ('superadmin','admin_dinas','admin_kecamatan','umkm')
             ORDER BY FIELD(r.code,'superadmin','admin_dinas','admin_kecamatan','umkm') LIMIT 1",
            array($id)
        )->row_array();
        if ($user && (int)$user['auth_epoch']>0 &&
            (int)$this->session->userdata('staff_user_id')===(int)$user['id'] &&
            (int)$this->session->userdata('staff_auth_epoch')!==(int)$user['auth_epoch'])
            return NULL;
        return $user;
    }

    public function too_many_attempts($email, $ip)
    {
        $email_hash = hash('sha256', strtolower($email), TRUE);
        $row = $this->db->query(
            'SELECT COUNT(*) AS n FROM iam_login_attempts
             WHERE failed_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
               AND email_hash = ?',
            array($email_hash)
        )->row_array();
        return (int) $row['n'] >= 8;
    }

    public function failed_attempt($email, $ip)
    {
        $this->db->query('INSERT INTO iam_login_attempts (email_hash, ip_hash) VALUES (?, ?)', array(
            hash('sha256', strtolower($email), TRUE), hash('sha256', $ip, TRUE)
        ));
    }

    public function clear_attempts($email, $ip)
    {
        $this->db->query('DELETE FROM iam_login_attempts WHERE email_hash = ? AND ip_hash = ?', array(
            hash('sha256', strtolower($email), TRUE), hash('sha256', $ip, TRUE)
        ));
    }

    public function audit($actor_id, $action, $entity_type, $entity_id = NULL, $reason = NULL)
    {
        $this->db->query(
            'INSERT INTO ops_audit_logs (actor_id, action_code, entity_type, entity_id, reason)
             VALUES (?, ?, ?, ?, ?)',
            array($actor_id, $action, $entity_type, $entity_id, $reason)
        );
    }
}
