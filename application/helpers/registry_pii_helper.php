<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('registry_pii_key')) {
    function registry_pii_key()
    {
        $key=@file_get_contents(dirname(APPPATH).'/.secrets/pii_aes256_key');
        return strlen((string)$key)===32 ? $key : NULL;
    }
}

if (!function_exists('registry_pii_decrypt')) {
    function registry_pii_decrypt($blob)
    {
        if (!$blob || strlen($blob)<30 || $blob[0]!=="\x01") return '';
        $key=registry_pii_key();
        if ($key===NULL) return '';
        $nonce=substr($blob,1,12);
        $cipher=substr($blob,13,-16);
        $tag=substr($blob,-16);
        $plain=openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag);
        return $plain===FALSE ? '' : $plain;
    }
}

if (!function_exists('registry_pii_encrypt')) {
    function registry_pii_encrypt($plain)
    {
        if ($plain==='') return NULL;
        $key=registry_pii_key();
        if ($key===NULL) throw new RuntimeException('Kunci data pribadi tidak tersedia.');
        $nonce=random_bytes(12);
        $tag='';
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag);
        if ($cipher===FALSE) throw new RuntimeException('Alamat gagal dienkripsi.');
        return "\x01".$nonce.$cipher.$tag;
    }
}
