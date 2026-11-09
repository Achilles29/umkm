<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Qr extends CI_Controller
{
    public function visit($code = '')
    {
        if (!preg_match('/^[a-z0-9-]{1,40}$/', $code))
        {
            show_404();
            return;
        }

        $this->load->database();
        $placement = $this->db->query(
            'SELECT id FROM mkt_qr_placements WHERE code = ? AND is_active = 1 LIMIT 1',
            array($code)
        )->row_array();
        if (!$placement)
        {
            show_404();
            return;
        }

        $this->db->query(
            'INSERT INTO mkt_qr_daily_stats (placement_id, visit_date, scan_count)
             VALUES (?, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE scan_count = scan_count + 1',
            array($placement['id'])
        );

        setcookie('umkm_qr_ref', $code, array('expires' => time() + 604800, 'path' => '/',
                                             'secure' => TRUE, 'httponly' => TRUE, 'samesite' => 'Lax'));

        // Semua QR promosi meminta nomor HP sebelum etalase dibuka.
        $path = '/masuk?next='.rawurlencode('/jelajah');
        $this->output->set_header('Cache-Control: no-store, private');
        redirect($path, 'location', 302);
    }
}
