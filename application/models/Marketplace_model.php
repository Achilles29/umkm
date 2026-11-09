<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Marketplace_model extends CI_Model
{
    public function categories()
    {
        return $this->db->query(
            "SELECT id, slug, name FROM ref_categories
             WHERE category_type = 'store' AND parent_id IS NULL AND is_active = 1
             ORDER BY sort_order, name"
        )->result_array();
    }

    public function category($slug)
    {
        return $this->db->query(
            "SELECT id, slug, name FROM ref_categories
             WHERE category_type = 'store' AND parent_id IS NULL AND slug = ? AND is_active = 1 LIMIT 1",
            array($slug)
        )->row_array();
    }

    public function districts()
    {
        return $this->db->query('SELECT id, name FROM ref_districts WHERE is_active = 1 ORDER BY name')->result_array();
    }

    public function district($id)
    {
        return $this->db->query('SELECT id, name FROM ref_districts WHERE id = ? AND is_active = 1 LIMIT 1',
                                array($id))->row_array();
    }

    public function villages($district_id)
    {
        return $this->db->query('SELECT id, name, kind FROM ref_villages WHERE district_id = ? AND is_active = 1 ORDER BY name',
                                array($district_id))->result_array();
    }

    public function village($id, $district_id)
    {
        return $this->db->query('SELECT id, name, kind FROM ref_villages WHERE id = ? AND district_id = ? AND is_active = 1 LIMIT 1',
                                array($id, $district_id))->row_array();
    }

    public function banner()
    {
        return $this->db->query("SELECT eyebrow, title, subtitle, cta_label, cta_target, image_path
                                 FROM cms_banners WHERE slot = 'marketplace' AND is_active = 1 LIMIT 1")->row_array();
    }

    private function active_store_filter($category_id = NULL, $district_id = NULL, $village_id = NULL)
    {
        $sql = " FROM tko_stores AS s
                JOIN umk_businesses AS b ON b.id = s.business_id
                LEFT JOIN ref_districts AS d ON d.id = b.district_id
                LEFT JOIN ref_villages AS village ON village.id = b.village_id
                LEFT JOIN gis_business_locations AS gl ON gl.business_id = b.id
                LEFT JOIN gis_district_points AS dp ON dp.district_id = b.district_id
                WHERE s.status = 'active'
                  AND EXISTS (SELECT 1 FROM umk_business_licenses AS l
                              WHERE l.business_id = b.id AND l.license_type = 'NIB'
                                AND ((l.status = 'verified' AND l.verified_at IS NOT NULL AND l.verified_by IS NOT NULL)
                                  OR (l.status = 'assumed' AND l.evidence_basis = 'profile_workbook'
                                      AND l.license_number LIKE 'DUMMY-NIB-%')))
                  AND EXISTS (SELECT 1 FROM tko_verifications AS v
                              WHERE v.store_id = s.id
                                AND v.id = (SELECT MAX(v2.id) FROM tko_verifications AS v2
                                            WHERE v2.store_id = s.id)
                                AND v.decision = 'approved' AND v.decided_at IS NOT NULL
                                AND ((v.evidence_basis = 'field_visit' AND v.visit_date IS NOT NULL AND v.decided_by IS NOT NULL)
                                  OR v.evidence_basis = 'profile_workbook'))";
        $params = array();
        if ($category_id !== NULL)
        {
            $sql .= " AND EXISTS (SELECT 1 FROM tko_store_categories AS sc
                                  JOIN ref_categories AS c ON c.id = sc.category_id
                                  WHERE sc.store_id = s.id AND sc.status = 'approved'
                                    AND (c.id = ? OR c.parent_id = ?))";
            $params[] = (int) $category_id;
            $params[] = (int) $category_id;
        }
        if ($district_id !== NULL)
        {
            $sql .= ' AND b.district_id = ?';
            $params[] = (int) $district_id;
        }
        if ($village_id !== NULL)
        {
            $sql .= ' AND b.village_id = ?';
            $params[] = (int) $village_id;
        }
        return array($sql, $params);
    }

    public function active_store_count($category_id = NULL, $district_id = NULL, $village_id = NULL)
    {
        list($sql, $params) = $this->active_store_filter($category_id, $district_id, $village_id);
        return (int) $this->db->query('SELECT COUNT(*) AS n'.$sql, $params)->row('n');
    }

    public function active_stores($category_id = NULL, $limit = 12, $offset = 0,
                                  $district_id = NULL, $village_id = NULL, $near_lat = NULL, $near_lng = NULL)
    {
        list($sql, $params) = $this->active_store_filter($category_id, $district_id, $village_id);
        $sql = "SELECT s.id, s.slug, s.display_name, s.description, s.published_at,
                       b.is_demo, d.id AS district_id, d.name AS district_name,
                       village.name AS village_name,
                       CASE WHEN gl.visibility = 'public' AND gl.consent_at IS NOT NULL
                                  AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL
                                  AND gl.public_longitude IS NOT NULL
                            THEN gl.public_latitude ELSE dp.latitude END AS map_latitude,
                       CASE WHEN gl.visibility = 'public' AND gl.consent_at IS NOT NULL
                                  AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL
                                  AND gl.public_longitude IS NOT NULL
                            THEN gl.public_longitude ELSE dp.longitude END AS map_longitude,
                       CASE WHEN gl.visibility = 'public' AND gl.consent_at IS NOT NULL
                                  AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL
                                  AND gl.public_longitude IS NOT NULL THEN 1 ELSE 0 END AS exact_location,
                       (SELECT COUNT(*) FROM prd_products AS p
                        WHERE p.store_id = s.id AND p.status = 'active') AS product_count,
                       (SELECT COALESCE(parent.slug,c.slug) FROM tko_store_categories AS sc
                        JOIN ref_categories AS c ON c.id = sc.category_id
                        LEFT JOIN ref_categories AS parent ON parent.id=c.parent_id
                        WHERE sc.store_id = s.id AND sc.status = 'approved'
                        ORDER BY c.id LIMIT 1) AS category_slug".$sql;
        $order='s.published_at DESC, s.id DESC';
        if ($near_lat !== NULL && $near_lng !== NULL)
        {
            $lat='(CASE WHEN gl.visibility = "public" AND gl.consent_at IS NOT NULL AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL AND gl.public_longitude IS NOT NULL THEN gl.public_latitude ELSE dp.latitude END)';
            $lng='(CASE WHEN gl.visibility = "public" AND gl.consent_at IS NOT NULL AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL AND gl.public_longitude IS NOT NULL THEN gl.public_longitude ELSE dp.longitude END)';
            $distance='POW('.$lat.'-'.(float)$near_lat.',2)+POW(('.$lng.'-'.(float)$near_lng.')*COS(RADIANS('.(float)$near_lat.')),2)';
            $order='('.$lat.' IS NULL) ASC, '.$distance.' ASC, '.$order;
        }
        $sql .= ' ORDER BY '.$order.' LIMIT '.(int) $limit.' OFFSET '.(int) $offset;
        return $this->db->query($sql, $params)->result_array();
    }

    public function active_store_by_slug($slug)
    {
        list($sql, $params) = $this->active_store_filter();
        $sql = "SELECT s.id, s.slug, s.display_name, s.description, s.whatsapp_e164,
                       b.is_demo, d.id AS district_id, d.name AS district_name,
                       village.name AS village_name,
                       CASE WHEN gl.visibility = 'public' AND gl.consent_at IS NOT NULL
                                  AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL
                                  AND gl.public_longitude IS NOT NULL
                            THEN gl.public_latitude ELSE dp.latitude END AS map_latitude,
                       CASE WHEN gl.visibility = 'public' AND gl.consent_at IS NOT NULL
                                  AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL
                                  AND gl.public_longitude IS NOT NULL
                            THEN gl.public_longitude ELSE dp.longitude END AS map_longitude,
                       CASE WHEN gl.visibility = 'public' AND gl.consent_at IS NOT NULL
                                  AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL
                                  AND gl.public_longitude IS NOT NULL THEN 1 ELSE 0 END AS exact_location".$sql.' AND s.slug = ? LIMIT 1';
        $params[] = $slug;
        return $this->db->query($sql, $params)->row_array();
    }

    public function active_store_products($store_id)
    {
        return $this->db->query(
            "SELECT p.id, p.slug, p.name, p.description, p.price_min, p.price_max,
                    p.unit_label, p.availability_note,
                    (SELECT storage_key FROM prd_media WHERE product_id=p.id AND media_type='image' ORDER BY is_cover DESC,sort_order,id LIMIT 1) AS cover_path,
                    (SELECT COUNT(*) FROM prd_variants WHERE product_id=p.id AND is_active=1) AS variant_count,
                    (SELECT COALESCE(parent.slug, c.slug) FROM prd_product_categories AS pc
                     JOIN ref_categories AS c ON c.id = pc.category_id
                     LEFT JOIN ref_categories AS parent ON parent.id = c.parent_id
                     WHERE pc.product_id = p.id ORDER BY c.id LIMIT 1) AS category_slug
             FROM prd_products AS p WHERE p.store_id = ? AND p.status = 'active'
               AND p.published_at IS NOT NULL ORDER BY p.id ASC LIMIT 100", array($store_id)
        )->result_array();
    }

    public function active_store_categories($store_id)
    {
        return $this->db->query(
            "SELECT c.slug, c.name FROM tko_store_categories AS sc
             JOIN ref_categories AS c ON c.id = sc.category_id
             WHERE sc.store_id = ? AND sc.status = 'approved' AND c.is_active = 1
             ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name", array($store_id)
        )->result_array();
    }

    public function store_hours($store_id)
    {
        return $this->db->query('SELECT day_of_week,opens_at,closes_at,is_closed FROM tko_store_hours WHERE store_id=? ORDER BY day_of_week',array($store_id))->result_array();
    }

    private function active_product_filter($category_id = NULL, $query = '')
    {
        $sql = " FROM prd_products AS p
                 JOIN tko_stores AS s ON s.id = p.store_id
                 JOIN umk_businesses AS b ON b.id = s.business_id
                 LEFT JOIN ref_districts AS d ON d.id = b.district_id
                 LEFT JOIN gis_business_locations AS gl ON gl.business_id = b.id
                 LEFT JOIN gis_district_points AS dp ON dp.district_id = b.district_id
                 WHERE p.status = 'active' AND p.published_at IS NOT NULL
                   AND s.status = 'active'
                   AND EXISTS (SELECT 1 FROM umk_business_licenses AS l
                               WHERE l.business_id = b.id AND l.license_type = 'NIB'
                                 AND ((l.status = 'verified' AND l.verified_at IS NOT NULL AND l.verified_by IS NOT NULL)
                                   OR (l.status = 'assumed' AND l.evidence_basis = 'profile_workbook'
                                       AND l.license_number LIKE 'DUMMY-NIB-%')))
                   AND EXISTS (SELECT 1 FROM tko_verifications AS v
                               WHERE v.store_id = s.id
                                 AND v.id = (SELECT MAX(v2.id) FROM tko_verifications AS v2
                                             WHERE v2.store_id = s.id)
                                 AND v.decision = 'approved' AND v.decided_at IS NOT NULL
                                 AND ((v.evidence_basis = 'field_visit' AND v.visit_date IS NOT NULL AND v.decided_by IS NOT NULL)
                                   OR v.evidence_basis = 'profile_workbook'))";
        $params = array();
        if ($category_id !== NULL)
        {
            $sql .= " AND EXISTS (SELECT 1 FROM prd_product_categories AS pc
                                   JOIN ref_categories AS c ON c.id = pc.category_id
                                   WHERE pc.product_id = p.id AND c.is_active = 1
                                     AND (c.id = ? OR c.parent_id = ?))";
            $params[] = (int) $category_id;
            $params[] = (int) $category_id;
        }
        if ($query !== '')
        {
            $sql .= ' AND (p.name LIKE ? OR s.display_name LIKE ?)';
            $params[] = '%'.$query.'%';
            $params[] = '%'.$query.'%';
        }
        return array($sql, $params);
    }

    public function active_product_count($category_id = NULL, $query = '')
    {
        list($sql, $params) = $this->active_product_filter($category_id, $query);
        return (int) $this->db->query('SELECT COUNT(*) AS n'.$sql, $params)->row('n');
    }

    public function active_products($category_id = NULL, $query = '', $limit = 20, $offset = 0,
                                    $near_lat = NULL, $near_lng = NULL)
    {
        list($sql, $params) = $this->active_product_filter($category_id, $query);
        $sql = "SELECT p.id, p.slug, p.name, p.price_min, p.price_max, p.unit_label,
                       (SELECT storage_key FROM prd_media WHERE product_id=p.id AND media_type='image' ORDER BY is_cover DESC,sort_order,id LIMIT 1) AS cover_path,
                       (SELECT COUNT(*) FROM prd_variants WHERE product_id=p.id AND is_active=1) AS variant_count,
                       s.id AS store_id, s.slug AS store_slug, s.display_name AS store_name,
                       d.name AS district_name,
                       (SELECT COALESCE(parent.slug, c2.slug) FROM prd_product_categories AS pc2
                        JOIN ref_categories AS c2 ON c2.id = pc2.category_id
                        LEFT JOIN ref_categories AS parent ON parent.id = c2.parent_id
                        WHERE pc2.product_id = p.id ORDER BY c2.id LIMIT 1) AS category_slug".$sql;
        $order='p.published_at DESC, p.id DESC';
        if ($near_lat !== NULL && $near_lng !== NULL)
        {
            $lat='(CASE WHEN gl.visibility = "public" AND gl.consent_at IS NOT NULL AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL AND gl.public_longitude IS NOT NULL THEN gl.public_latitude ELSE dp.latitude END)';
            $lng='(CASE WHEN gl.visibility = "public" AND gl.consent_at IS NOT NULL AND gl.checked_at IS NOT NULL AND gl.public_latitude IS NOT NULL AND gl.public_longitude IS NOT NULL THEN gl.public_longitude ELSE dp.longitude END)';
            $distance='POW('.$lat.'-'.(float)$near_lat.',2)+POW(('.$lng.'-'.(float)$near_lng.')*COS(RADIANS('.(float)$near_lat.')),2)';
            $order='('.$lat.' IS NULL) ASC, '.$distance.' ASC, '.$order;
        }
        $sql .= ' ORDER BY '.$order.' LIMIT '.(int) $limit.' OFFSET '.(int) $offset;
        return $this->db->query($sql, $params)->result_array();
    }

    public function active_product_by_id($id)
    {
        list($sql, $params) = $this->active_product_filter();
        $sql = "SELECT p.id, p.slug, p.name, p.description, p.price_min, p.price_max,
                       p.unit_label, p.availability_note, s.id AS store_id,
                       s.slug AS store_slug, s.display_name AS store_name,
                       s.whatsapp_e164, b.is_demo, d.name AS district_name,
                       (SELECT COALESCE(parent.slug, c2.slug) FROM prd_product_categories AS pc2
                        JOIN ref_categories AS c2 ON c2.id = pc2.category_id
                        LEFT JOIN ref_categories AS parent ON parent.id = c2.parent_id
                        WHERE pc2.product_id = p.id ORDER BY c2.id LIMIT 1) AS category_slug".$sql;
        $params[] = (int) $id;
        return $this->db->query($sql.' AND p.id = ? LIMIT 1', $params)->row_array();
    }

    public function product_variants($product_id)
    {
        return $this->db->query('SELECT id,label,sku,price,stock_qty FROM prd_variants WHERE product_id=? AND is_active=1 ORDER BY sort_order,id', array($product_id))->result_array();
    }

    public function product_variant($product_id, $variant_id)
    {
        return $this->db->query('SELECT id,label,price,stock_qty FROM prd_variants WHERE id=? AND product_id=? AND is_active=1 LIMIT 1', array($variant_id,$product_id))->row_array();
    }

    public function product_media($product_id)
    {
        return $this->db->query('SELECT id,media_type,storage_key,alt_text FROM prd_media WHERE product_id=? ORDER BY media_type="image" DESC,is_cover DESC,sort_order,id', array($product_id))->result_array();
    }

    public function product_specs($product_id)
    {
        return $this->db->query('SELECT spec_name,spec_value FROM prd_product_specs WHERE product_id=? ORDER BY sort_order,spec_name', array($product_id))->result_array();
    }

    public function customer_by_phone($phone)
    {
        return $this->db->query('SELECT id FROM crm_customers WHERE phone_e164 = ? LIMIT 1', array($phone))->row_array();
    }

    public function create_customer($phone, $name)
    {
        $this->db->query(
            "INSERT INTO crm_customers (phone_e164, display_name)
             VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), last_seen_at = NOW()",
            array($phone, $name)
        );
        return array('id' => (int)$this->db->insert_id(),
                     'created' => $this->db->affected_rows() === 1);
    }

    public function create_marketplace_session($customer_id, $token_hash)
    {
        $this->db->query(
            "INSERT INTO crm_marketplace_sessions (customer_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))",
            array($customer_id, $token_hash)
        );
    }

    public function current_customer($token_hash)
    {
        return $this->db->query(
            "SELECT c.id, c.display_name FROM crm_marketplace_sessions AS s
             JOIN crm_customers AS c ON c.id = s.customer_id
             WHERE s.token_hash = ? AND s.expires_at > NOW() LIMIT 1",
            array($token_hash)
        )->row_array();
    }

    public function delete_marketplace_session($token_hash)
    {
        $this->db->query('DELETE FROM crm_marketplace_sessions WHERE token_hash = ?', array($token_hash));
    }
}
