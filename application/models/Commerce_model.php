<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Commerce_model extends CI_Model
{
    public function cart_id($customer_id, $create = FALSE)
    {
        $row = $this->db->query('SELECT id, expires_at FROM krj_carts WHERE customer_id = ? LIMIT 1',
                                array($customer_id))->row_array();
        if ($row)
        {
            if (strtotime($row['expires_at']) <= time())
            {
                $this->db->query('DELETE FROM krj_cart_items WHERE cart_id = ?', array($row['id']));
                $this->db->query('UPDATE krj_carts SET expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?',
                                 array($row['id']));
            }
            return (int) $row['id'];
        }
        if (!$create) return NULL;
        $token = bin2hex(random_bytes(16));
        $uuid = substr($token, 0, 8).'-'.substr($token, 8, 4).'-4'.substr($token, 13, 3).'-a'.substr($token, 17, 3).'-'.substr($token, 20, 12);
        $this->db->query(
            'INSERT INTO krj_carts (cart_token, customer_id, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
            array($uuid, $customer_id)
        );
        return (int) $this->db->insert_id();
    }

    public function cart_count($customer_id)
    {
        $row = $this->db->query(
            'SELECT COALESCE(SUM(i.quantity), 0) AS n FROM krj_carts AS c
             JOIN krj_cart_items AS i ON i.cart_id = c.id
             WHERE c.customer_id = ? AND c.expires_at > NOW()', array($customer_id)
        )->row_array();
        return (int) $row['n'];
    }

    public function add_item($customer_id, $product_id, $quantity, $variant_id = NULL)
    {
        $cart_id = $this->cart_id($customer_id, TRUE);
        $this->db->query(
            'INSERT INTO krj_cart_items (cart_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = LEAST(99, quantity + VALUES(quantity))',
            array($cart_id, $product_id, $variant_id, $quantity)
        );
    }

    public function change_item($customer_id, $line_id, $quantity)
    {
        $cart_id = $this->cart_id($customer_id);
        if (!$cart_id) return;
        if ($quantity === 0)
        {
            $this->db->query('DELETE FROM krj_cart_items WHERE cart_id = ? AND id = ?',
                             array($cart_id, $line_id));
        }
        else
        {
            $this->db->query('UPDATE krj_cart_items SET quantity = ? WHERE cart_id = ? AND id = ?',
                             array($quantity, $cart_id, $line_id));
        }
    }

    public function cart_items($customer_id)
    {
        return $this->db->query(
            "SELECT c.id AS cart_id, i.id AS line_id, i.product_id, i.variant_id, i.quantity, p.name AS product_name,
                    COALESCE(v.price,p.price_min) AS price_min, v.label AS variant_label, v.stock_qty,
                    p.unit_label, s.id AS store_id, s.slug AS store_slug,
                    s.display_name AS store_name, s.whatsapp_e164, b.is_demo,
                    (SELECT storage_key FROM prd_media WHERE product_id=p.id AND media_type='image' ORDER BY is_cover DESC,sort_order,id LIMIT 1) AS cover_path,
                    (SELECT COUNT(*) FROM prd_variants WHERE product_id=p.id AND is_active=1) AS variant_count,
                    (SELECT COALESCE(parent.slug, cat.slug) FROM prd_product_categories AS pc
                     JOIN ref_categories AS cat ON cat.id = pc.category_id
                     LEFT JOIN ref_categories AS parent ON parent.id = cat.parent_id
                     WHERE pc.product_id = p.id ORDER BY cat.id LIMIT 1) AS category_slug
             FROM krj_carts AS c
             JOIN krj_cart_items AS i ON i.cart_id = c.id
             JOIN prd_products AS p ON p.id = i.product_id AND p.status = 'active'
             LEFT JOIN prd_variants AS v ON v.id=i.variant_id AND v.is_active=1
             JOIN tko_stores AS s ON s.id = p.store_id AND s.status = 'active'
             JOIN umk_businesses AS b ON b.id = s.business_id
             WHERE c.customer_id = ? AND c.expires_at > NOW()
             ORDER BY s.display_name, i.added_at, i.id", array($customer_id)
        )->result_array();
    }

    public function active_template($key)
    {
        return $this->db->query(
            'SELECT id, body_template FROM mkt_whatsapp_templates
             WHERE template_key = ? AND is_active = 1 ORDER BY version_no DESC LIMIT 1',
            array($key)
        )->row_array();
    }

    public function test_whatsapp_number()
    {
        $row = $this->db->query('SELECT test_whatsapp_e164 FROM mkt_contact_settings WHERE id = 1 LIMIT 1')->row_array();
        return $row && $row['test_whatsapp_e164'] ? $row['test_whatsapp_e164'] : NULL;
    }

    public function qr_placement_id($code)
    {
        if (!preg_match('/^[a-z0-9-]{1,40}$/', (string) $code)) return NULL;
        $row = $this->db->query('SELECT id FROM mkt_qr_placements WHERE code = ? AND is_active = 1 LIMIT 1',
                                array($code))->row_array();
        return $row ? (int) $row['id'] : NULL;
    }

    public function create_intent($reference, $customer_id, $store_id, $cart_id, $qr_id,
                                  $template_id, $mode, $message, array $items)
    {
        $this->db->trans_begin();
        $this->db->query(
            "INSERT INTO mkt_contact_intents
             (reference_no, customer_id, store_id, cart_id, qr_placement_id, template_id,
              contact_mode, message_text, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'prepared')",
            array($reference, $customer_id, $store_id, $cart_id, $qr_id, $template_id, $mode, $message)
        );
        $intent_id = (int) $this->db->insert_id();
        foreach ($items as $index => $item)
        {
            $this->db->query(
                'INSERT INTO mkt_contact_items
                 (intent_id, product_id, line_no, product_name, variant_label, quantity, price_note)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                array($intent_id, $item['product_id'], $index + 1, isset($item['product_name']) ? $item['product_name'] : $item['name'],
                      isset($item['variant_label']) ? $item['variant_label'] : NULL,
                      $item['quantity'], $item['price_note'])
            );
        }
        if ($this->db->trans_status() === FALSE)
        {
            $this->db->trans_rollback();
            throw new RuntimeException('Catatan kontak gagal disimpan.');
        }
        $this->db->trans_commit();
        return $intent_id;
    }

    public function intent($reference, $customer_id)
    {
        return $this->db->query(
            'SELECT i.id, i.reference_no, i.message_text, i.status, i.contact_mode,
                    s.id AS store_id, s.slug AS store_slug, s.display_name AS store_name,
                    s.whatsapp_e164, b.is_demo
             FROM mkt_contact_intents AS i
             JOIN tko_stores AS s ON s.id = i.store_id
             JOIN umk_businesses AS b ON b.id = s.business_id
             WHERE i.reference_no = ? AND i.customer_id = ? LIMIT 1',
            array($reference, $customer_id)
        )->row_array();
    }

    public function mark_opened($intent_id, $customer_id)
    {
        $this->db->trans_begin();
        $this->db->query(
            "UPDATE mkt_contact_intents SET status = 'opened', opened_at = COALESCE(opened_at, NOW())
             WHERE id = ? AND customer_id = ? AND status <> 'opened'", array($intent_id, $customer_id)
        );
        if ($this->db->affected_rows() > 0)
        {
            $intent=$this->db->query('SELECT i.reference_no,i.store_id,s.display_name FROM mkt_contact_intents i JOIN tko_stores s ON s.id=i.store_id WHERE i.id=?',array($intent_id))->row_array();
            $this->load->model('Notification_model');
            $recipients=array_merge($this->Notification_model->admin_ids(),$this->Notification_model->store_manager_ids($intent['store_id']),
                                    $this->Notification_model->district_admin_ids($intent['store_id']));
            $this->Notification_model->publish('contact_opened','contact:'.$intent_id,
                'Minat WhatsApp baru', 'Pengunjung membuka kontak untuk '.$intent['display_name'].' ('.$intent['reference_no'].').',
                '/pengelola/minat/'.$intent_id,$recipients);
        }
        if ($this->db->trans_status() === FALSE)
        {
            $this->db->trans_rollback();
            throw new RuntimeException('Status kontak gagal dicatat.');
        }
        $this->db->trans_commit();
    }
}
