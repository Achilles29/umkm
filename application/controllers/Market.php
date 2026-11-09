<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Market extends CI_Controller
{
    private $cookie_name = 'umkm_marketplace';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Marketplace_model');
        $this->load->model('Commerce_model');
        $this->load->model('Notification_model');
    }

    public function signin()
    {
        $next = $this->safe_next($this->input->get_post('next', TRUE));
        if ($this->input->method(TRUE) === 'GET' && $this->current_customer())
        {
            redirect($next, 'location', 302);
            return;
        }
        if ($this->input->method(TRUE) === 'POST')
        {
            $phone = $this->normalize_phone($this->input->post('phone', TRUE));
            if ($phone === NULL)
            {
                return $this->render_signin('Masukkan nomor HP Indonesia yang valid.', '', $next);
            }
            $existing = $this->Marketplace_model->customer_by_phone($phone);
            if ($existing)
            {
                $this->issue_session((int) $existing['id']);
                redirect($next, 'location', 303);
                return;
            }
            return $this->render_signin('', $phone, $next);
        }
        $this->render_signin('', '', $next);
    }

    public function name()
    {
        if ($this->input->method(TRUE) !== 'POST')
        {
            show_404();
            return;
        }
        $phone = $this->normalize_phone($this->input->post('phone', TRUE));
        $name = trim((string) $this->input->post('name', TRUE));
        $next = $this->safe_next($this->input->post('next', TRUE));
        if ($phone === NULL || mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 160)
        {
            return $this->render_signin('Isi nama 2 sampai 160 karakter.', $phone ?: '', $next);
        }
        $this->db->trans_begin();
        $created = $this->Marketplace_model->create_customer($phone, $name);
        if ($created['created'])
        {
            $masked = substr($phone,0,5).'***'.substr($phone,-3);
            $this->Notification_model->publish('customer_registered','customer:'.$created['id'],
                'Pendaftaran pengunjung baru', $name.' mendaftar ke etalase ('.$masked.').',
                '/pengelola/anggota/'.$created['id'], $this->Notification_model->admin_ids());
        }
        if ($this->db->trans_status() === FALSE)
        {
            $this->db->trans_rollback();
            show_error('Pendaftaran gagal disimpan. Silakan coba lagi.',500);
            return;
        }
        $this->db->trans_commit();
        $this->issue_session($created['id']);
        redirect($next, 'location', 303);
    }

    public function index()
    {
        $customer = $this->require_customer();
        if (!$customer) return;
        $nearby=$this->nearby_coordinates();
        $slug = trim((string) $this->input->get('category', TRUE));
        $category = $slug === '' ? NULL : $this->Marketplace_model->category($slug);
        if ($slug !== '' && $category === NULL)
        {
            show_404();
            return;
        }
        $query = trim((string) $this->input->get('q', TRUE));
        $query = mb_substr($query, 0, 80, 'UTF-8');
        $page = (int) $this->input->get('page', TRUE);
        $page = $page < 1 ? 1 : $page;
        $per_page = 24;
        $total = $this->Marketplace_model->active_product_count($category ? (int) $category['id'] : NULL, $query);
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($page > $total_pages)
        {
            show_404();
            return;
        }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/index', array(
            'categories' => $this->Marketplace_model->categories(),
            'banner' => $this->Marketplace_model->banner(),
            'selected_category' => $category,
            'products' => $this->Marketplace_model->active_products($category ? (int) $category['id'] : NULL,
                                                                     $query, $per_page, ($page - 1) * $per_page,
                                                                     $nearby ? $nearby[0] : NULL, $nearby ? $nearby[1] : NULL),
            'featured_stores' => $total===0 && $query==='' ? $this->Marketplace_model->active_stores(
                $category ? (int)$category['id'] : NULL,12,0) : array(),
            'query' => $query,
            'total_products' => $total,
            'page' => $page,
            'total_pages' => $total_pages,
            'nearby'=>$nearby,
            'cart_count' => $this->Commerce_model->cart_count((int) $customer['id'])
        ));
    }

    public function stores()
    {
        $customer = $this->require_customer();
        if (!$customer) return;
        $nearby=$this->nearby_coordinates();
        $district_id = (int) $this->input->get('district', TRUE);
        $village_id = (int) $this->input->get('village', TRUE);
        $district = $district_id ? $this->Marketplace_model->district($district_id) : NULL;
        if ($district_id && !$district) { show_404(); return; }
        $village = $village_id && $district
            ? $this->Marketplace_model->village($village_id, $district_id) : NULL;
        if ($village_id && !$village) { show_404(); return; }
        $page = max(1, (int) $this->input->get('page', TRUE));
        $per_page = 12;
        $total = $this->Marketplace_model->active_store_count(NULL,
            $district ? $district_id : NULL, $village ? $village_id : NULL);
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($page > $total_pages) { show_404(); return; }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/stores', array(
            'stores' => $this->Marketplace_model->active_stores(NULL, $per_page, ($page - 1) * $per_page,
                $district ? $district_id : NULL, $village ? $village_id : NULL,
                $nearby ? $nearby[0] : NULL, $nearby ? $nearby[1] : NULL),
            'total_stores' => $total, 'page' => $page, 'total_pages' => $total_pages,
            'districts' => $this->Marketplace_model->districts(),
            'villages' => $district ? $this->Marketplace_model->villages($district_id) : array(),
            'selected_district' => $district, 'selected_village' => $village,
            'nearby'=>$nearby,
            'cart_count' => $this->Commerce_model->cart_count((int) $customer['id'])
        ));
    }

    public function map()
    {
        $customer = $this->require_customer();
        if (!$customer) return;
        $district_id = (int) $this->input->get('district', TRUE);
        $district = $district_id ? $this->Marketplace_model->district($district_id) : NULL;
        if ($district_id && !$district) { show_404(); return; }
        $stores = $this->Marketplace_model->active_stores(NULL, 500, 0,
            $district ? $district_id : NULL);
        $area_markers = array();
        $exact_markers = array();
        foreach ($stores as $store)
        {
            if ($store['map_latitude'] === NULL || $store['map_longitude'] === NULL) continue;
            if ((int) $store['exact_location'] === 1)
            {
                $exact_markers[] = array('lat' => (float) $store['map_latitude'],
                    'lng' => (float) $store['map_longitude'], 'name' => $store['display_name'],
                    'url' => site_url('toko/'.$store['slug']));
            }
            else
            {
                $key = (int) $store['district_id'];
                if (!isset($area_markers[$key]))
                {
                    $area_markers[$key] = array('lat' => (float) $store['map_latitude'],
                        'lng' => (float) $store['map_longitude'], 'name' => $store['district_name'],
                        'count' => 0, 'url' => site_url('daftar-toko').'?district='.$key);
                }
                $area_markers[$key]['count']++;
            }
        }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/map', array('area_markers' => array_values($area_markers),
            'exact_markers' => $exact_markers, 'districts' => $this->Marketplace_model->districts(),
            'selected_district' => $district, 'store_count' => count($stores),
            'cart_count' => $this->Commerce_model->cart_count((int) $customer['id'])));
    }

    public function store($slug = '')
    {
        $customer = $this->require_customer();
        if (!$customer) return;
        if (!preg_match('/^[a-z0-9-]{1,120}$/', $slug))
        {
            show_404();
            return;
        }
        $store = $this->Marketplace_model->active_store_by_slug($slug);
        if (!$store)
        {
            show_404();
            return;
        }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/store', array(
            'store' => $store,
            'products' => $this->Marketplace_model->active_store_products((int) $store['id']),
            'categories' => $this->Marketplace_model->active_store_categories((int) $store['id']),
            'hours' => $this->Marketplace_model->store_hours((int) $store['id']),
            'cart_count' => $this->Commerce_model->cart_count((int) $customer['id'])
        ));
    }

    private function nearby_coordinates()
    {
        $lat=$this->input->get('lat',TRUE);
        $lng=$this->input->get('lng',TRUE);
        if (!is_scalar($lat) || !is_scalar($lng) || !is_numeric($lat) || !is_numeric($lng)) return NULL;
        $lat=(float)$lat;$lng=(float)$lng;
        if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return NULL;
        return array(round($lat,2),round($lng,2));
    }

    public function product($id = 0)
    {
        $customer = $this->require_customer();
        if (!$customer) return;
        $product = $this->Marketplace_model->active_product_by_id((int) $id);
        if (!$product) { show_404(); return; }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/product', array('product' => $product,
            'variants' => $this->Marketplace_model->product_variants((int)$id),
            'media' => $this->Marketplace_model->product_media((int)$id),
            'specs' => $this->Marketplace_model->product_specs((int)$id),
            'cart_count' => $this->Commerce_model->cart_count((int) $customer['id'])));
    }

    public function cart()
    {
        $customer = $this->require_customer();
        if (!$customer) return;
        $rows = $this->Commerce_model->cart_items((int) $customer['id']);
        $groups = array();
        foreach ($rows as $row)
        {
            $store_id = (int) $row['store_id'];
            if (!isset($groups[$store_id]))
            {
                $groups[$store_id] = array('store_id' => $store_id, 'store_slug' => $row['store_slug'],
                                           'store_name' => $row['store_name'], 'items' => array(), 'subtotal' => 0);
            }
            $groups[$store_id]['items'][] = $row;
            $groups[$store_id]['subtotal'] += (float) $row['price_min'] * (int) $row['quantity'];
        }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/cart', array('groups' => $groups,
            'cart_count' => $this->Commerce_model->cart_count((int) $customer['id'])));
    }

    public function add_to_cart()
    {
        if ($this->input->method(TRUE) !== 'POST') { show_404(); return; }
        $customer = $this->require_customer();
        if (!$customer) return;
        $product_id = (int) $this->input->post('product_id', TRUE);
        $quantity = (int) $this->input->post('quantity', TRUE);
        $product = $this->Marketplace_model->active_product_by_id($product_id);
        if (!$product || $quantity < 1 || $quantity > 99) { show_error('Produk atau jumlah tidak valid.', 400); return; }
        $variant = $this->selected_variant($product_id, $quantity);
        if ($variant === FALSE) return;
        $this->Commerce_model->add_item((int) $customer['id'], $product_id, $quantity, $variant ? (int)$variant['id'] : NULL);
        redirect('keranjang', 'location', 303);
    }

    public function change_cart()
    {
        if ($this->input->method(TRUE) !== 'POST') { show_404(); return; }
        $customer = $this->require_customer();
        if (!$customer) return;
        $line_id = (int) $this->input->post('line_id', TRUE);
        $quantity = (int) $this->input->post('quantity', TRUE);
        if ($line_id < 1 || $quantity < 0 || $quantity > 99) { show_error('Jumlah tidak valid.', 400); return; }
        $rows = $this->Commerce_model->cart_items((int)$customer['id']);
        foreach ($rows as $row) if ((int)$row['line_id'] === $line_id && $quantity > 0 && $row['stock_qty'] !== NULL && $quantity > (int)$row['stock_qty']) { show_error('Jumlah melebihi stok varian.', 409); return; }
        $this->Commerce_model->change_item((int) $customer['id'], $line_id, $quantity);
        redirect('keranjang', 'location', 303);
    }

    public function contact_product()
    {
        if ($this->input->method(TRUE) !== 'POST') { show_404(); return; }
        $customer = $this->require_customer();
        if (!$customer) return;
        $product = $this->Marketplace_model->active_product_by_id((int) $this->input->post('product_id', TRUE));
        if (!$product) { show_404(); return; }
        $quantity = max(1,(int)$this->input->post('quantity', TRUE));
        if ($quantity > 99) { show_error('Jumlah tidak valid.',400); return; }
        $variant = $this->selected_variant((int)$product['id'], $quantity);
        if ($variant === FALSE) return;
        $label = $variant ? $variant['label'] : NULL;
        $item = array('product_id' => (int) $product['id'], 'product_name' => $product['name'], 'name' => $product['name'].($label ? ' ('.$label.')' : ''),
                      'variant_label' => $label, 'quantity' => $quantity,
                      'price_note' => $this->price_note($product, $variant));
        $reference = $this->prepare_contact($customer, $product, array($item), 'direct_product', NULL);
        $this->finish_contact($reference, $customer);
    }

    public function contact_store()
    {
        if ($this->input->method(TRUE) !== 'POST') { show_404(); return; }
        $customer = $this->require_customer();
        if (!$customer) return;
        $slug = (string) $this->input->post('store_slug', TRUE);
        if (!preg_match('/^[a-z0-9-]{1,120}$/', $slug)) { show_404(); return; }
        $store = $this->Marketplace_model->active_store_by_slug($slug);
        if (!$store) { show_404(); return; }
        $item = array('product_id' => NULL, 'name' => 'pilihan produk di toko Anda',
                      'quantity' => 1, 'price_note' => NULL);
        $reference = $this->prepare_contact($customer,
            array('store_id' => $store['id'], 'store_name' => $store['display_name']),
            array($item), 'direct_product', NULL);
        $this->finish_contact($reference, $customer);
    }

    public function contact_cart($store_id = 0)
    {
        if ($this->input->method(TRUE) !== 'POST') { show_404(); return; }
        $customer = $this->require_customer();
        if (!$customer) return;
        $rows = $this->Commerce_model->cart_items((int) $customer['id']);
        $items = array();
        $store = NULL;
        $cart_id = NULL;
        foreach ($rows as $row)
        {
            if ((int) $row['store_id'] !== (int) $store_id) continue;
            $product = $this->Marketplace_model->active_product_by_id((int) $row['product_id']);
            if (!$product || (int) $product['store_id'] !== (int) $store_id)
            {
                show_error('Salah satu produk tidak tersedia.', 409);
                return;
            }
            if (($row['variant_count'] > 0 && !$row['variant_id']) || ($row['variant_id'] && !$row['variant_label']) ||
                ($row['stock_qty'] !== NULL && (int)$row['quantity'] > (int)$row['stock_qty']))
            { show_error('Pilihan varian atau stok berubah. Perbarui keranjang.',409); return; }
            $variant = $row['variant_id'] ? array('price' => $row['price_min']) : NULL;
            $items[] = array('product_id' => (int) $row['product_id'], 'product_name' => $row['product_name'],
                             'name' => $row['product_name'].($row['variant_label'] ? ' ('.$row['variant_label'].')' : ''),
                             'variant_label' => $row['variant_label'],
                             'quantity' => (int) $row['quantity'], 'price_note' => $this->price_note($product,$variant));
            $store = $product;
            $cart_id = (int) $row['cart_id'];
        }
        if (!$items || count($items) > 25) { show_error('Keranjang toko tidak valid.', 400); return; }
        $reference = $this->prepare_contact($customer, $store, $items, 'cart_per_store', $cart_id);
        $this->finish_contact($reference, $customer);
    }

    public function contact($reference = '')
    {
        $customer = $this->require_customer();
        if (!$customer) return;
        if (!preg_match('/^UMKM-[A-F0-9]{12}$/', $reference)) { show_404(); return; }
        $intent = $this->Commerce_model->intent($reference, (int) $customer['id']);
        if (!$intent) { show_404(); return; }
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/contact', array('intent' => $intent,
            'test_destination_available' => $intent['is_demo'] && $this->Commerce_model->test_whatsapp_number() !== NULL,
            'cart_count' => $this->Commerce_model->cart_count((int) $customer['id'])));
    }

    public function open_contact($reference = '')
    {
        if ($this->input->method(TRUE) !== 'POST') { show_404(); return; }
        $customer = $this->require_customer();
        if (!$customer) return;
        $this->finish_contact($reference, $customer, TRUE);
    }

    public function account()
    {
        $customer = $this->require_customer();
        if (!$customer) return;
        $registrations=$this->db->query('SELECT r.reference_no,r.status,r.submitted_at,e.title,e.slug,e.status AS event_status,e.starts_at FROM evt_registrations r JOIN evt_events e ON e.id=r.event_id WHERE r.customer_id=? ORDER BY r.submitted_at DESC LIMIT 30',array((int)$customer['id']))->result_array();
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/account', array(
            'cart_count' => $this->Commerce_model->cart_count((int) $customer['id']),
            'registrations' => $registrations));
    }

    public function logout()
    {
        if ($this->input->method(TRUE) !== 'POST')
        {
            show_404();
            return;
        }
        $token = (string) $this->input->cookie($this->cookie_name, TRUE);
        if (preg_match('/^[a-f0-9]{64}$/', $token))
        {
            $this->Marketplace_model->delete_marketplace_session(hash('sha256', $token, TRUE));
        }
        setcookie($this->cookie_name, '', array('expires' => time() - 3600, 'path' => '/', 'secure' => TRUE,
                                               'httponly' => TRUE, 'samesite' => 'Lax'));
        redirect('/', 'location', 303);
    }

    private function require_customer()
    {
        $customer = $this->current_customer();
        if (!$customer) redirect('masuk', 'location', 302);
        return $customer;
    }

    private function selected_variant($product_id, $quantity)
    {
        $variants = $this->Marketplace_model->product_variants($product_id);
        $id = (int)$this->input->post('variant_id', TRUE);
        if (!$variants && !$id) return NULL;
        $variant = $id ? $this->Marketplace_model->product_variant($product_id,$id) : NULL;
        if (!$variant) { show_error('Pilih varian produk terlebih dahulu.',400); return FALSE; }
        if ($variant['stock_qty'] !== NULL && $quantity > (int)$variant['stock_qty'])
        { show_error('Jumlah melebihi stok varian.',409); return FALSE; }
        return $variant;
    }

    private function price_note($product, $variant = NULL)
    {
        $price = $variant && $variant['price'] !== NULL ? $variant['price'] : $product['price_min'];
        if ($price === NULL) return NULL;
        $note = 'Rp '.number_format((float) $price, 0, ',', '.');
        if (!empty($product['unit_label'])) $note .= ' / '.$product['unit_label'];
        return $note;
    }

    private function finish_contact($reference, $customer, $strict = FALSE)
    {
        $intent = $this->Commerce_model->intent($reference, (int) $customer['id']);
        if (!$intent) { show_404(); return; }
        $phone = $intent['is_demo'] ? $this->Commerce_model->test_whatsapp_number() : $intent['whatsapp_e164'];
        if (!$phone)
        {
            if ($strict) { show_error('Kontak toko belum tersedia.', 409); return; }
            redirect('kontak/'.$reference, 'location', 303);
            return;
        }
        $this->load->library('Whatsapp_template');
        try { $url = $this->whatsapp_template->url($phone, $intent['message_text']); }
        catch (InvalidArgumentException $error) { show_error('Nomor WhatsApp toko belum tersedia.', 409); return; }
        $this->Commerce_model->mark_opened((int) $intent['id'], (int) $customer['id']);
        redirect($url, 'location', 303);
    }

    private function prepare_contact($customer, $store, array $items, $mode, $cart_id)
    {
        $template = $this->Commerce_model->active_template($mode);
        if (!$template) { show_error('Template pesan belum tersedia.', 503); exit; }
        $reference = 'UMKM-'.strtoupper(bin2hex(random_bytes(6)));
        $this->load->library('Whatsapp_template');
        $values = array('store_name' => $store['store_name'],
                        'customer_name' => $customer['display_name'] ?: 'Pelanggan',
                        'reference_no' => $reference);
        if ($mode === 'direct_product')
        {
            $values['product_name'] = $items[0]['name'].((int)$items[0]['quantity'] > 1 ? ' x'.(int)$items[0]['quantity'] : '');
        }
        else
        {
            $values['items'] = $this->whatsapp_template->items($items);
        }
        $message = $this->whatsapp_template->render($template['body_template'], $values);
        $qr_id = $this->Commerce_model->qr_placement_id($this->input->cookie('umkm_qr_ref', TRUE));
        $this->Commerce_model->create_intent($reference, (int) $customer['id'], (int) $store['store_id'],
            $cart_id, $qr_id, (int) $template['id'], $mode, $message, $items);
        return $reference;
    }

    private function render_signin($error, $phone, $next)
    {
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('market/signin', array('error' => $error, 'phone' => $phone, 'next' => $next));
    }

    private function normalize_phone($input)
    {
        $digits = preg_replace('/\D+/', '', (string) $input);
        if (strpos($digits, '0') === 0)
        {
            $digits = '62'.substr($digits, 1);
        }
        if (strpos($digits, '62') !== 0 || !preg_match('/^62[0-9]{8,13}$/', $digits))
        {
            return NULL;
        }
        return '+'.$digits;
    }

    private function safe_next($next)
    {
        if (!is_string($next) || !preg_match('/^\/(?:jelajah(?:\?category=[a-z0-9-]{1,100})?|peta|daftar-toko|kegiatan\/[a-z0-9-]{1,150})$/', $next))
        {
            return '/jelajah';
        }
        return $next;
    }

    private function issue_session($customer_id)
    {
        $token = bin2hex(random_bytes(32));
        $this->Marketplace_model->create_marketplace_session($customer_id, hash('sha256', $token, TRUE));
        setcookie($this->cookie_name, $token, array('expires' => time() + 604800, 'path' => '/',
                                                  'secure' => TRUE, 'httponly' => TRUE, 'samesite' => 'Lax'));
    }

    private function current_customer()
    {
        $token = (string) $this->input->cookie($this->cookie_name, TRUE);
        if (!preg_match('/^[a-f0-9]{64}$/', $token))
        {
            return NULL;
        }
        return $this->Marketplace_model->current_customer(hash('sha256', $token, TRUE));
    }
}
