<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp_template
{
    public function render($template, array $values)
    {
        $allowed = array('store_name', 'customer_name', 'product_name', 'items', 'reference_no');
        $message = preg_replace_callback('/{{([a-z_]+)}}/', function ($match) use ($values, $allowed) {
            if (!in_array($match[1], $allowed, TRUE) || !array_key_exists($match[1], $values))
            {
                throw new InvalidArgumentException('Placeholder template tidak dikenal: '.$match[1]);
            }
            $value = (string) $values[$match[1]];
            if ($match[1] !== 'items')
            {
                $value = $this->single_line($value, 190);
            }
            return $value;
        }, (string) $template);
        if ($message === NULL || trim($message) === '' || mb_strlen($message, 'UTF-8') > 3500)
        {
            throw new InvalidArgumentException('Pesan WhatsApp tidak valid atau terlalu panjang.');
        }
        return $message;
    }

    public function items(array $items)
    {
        if (!$items || count($items) > 25)
        {
            throw new InvalidArgumentException('Keranjang harus berisi 1 sampai 25 produk per toko.');
        }
        $lines = array();
        foreach ($items as $index => $item)
        {
            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
            if ($quantity < 1 || $quantity > 999)
            {
                throw new InvalidArgumentException('Jumlah produk tidak valid.');
            }
            $name = $this->single_line(isset($item['name']) ? $item['name'] : '', 190);
            if ($name === '')
            {
                throw new InvalidArgumentException('Nama produk tidak boleh kosong.');
            }
            $line = ($index + 1).'. '.$name.' x'.$quantity;
            if (!empty($item['price_note']))
            {
                $line .= ' — '.$this->single_line($item['price_note'], 120);
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    public function url($phone, $message)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (!preg_match('/^62[0-9]{8,13}$/', $digits))
        {
            throw new InvalidArgumentException('Nomor WhatsApp toko tidak valid.');
        }
        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    private function single_line($value, $limit)
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        return mb_substr($value, 0, $limit, 'UTF-8');
    }
}
