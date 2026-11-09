<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('workflow_store_evidence'))
{
    function workflow_store_evidence($field,$folder)
    {
        if (!in_array($folder,array('claims','recovery'),TRUE) ||
            empty($_FILES[$field]) || $_FILES[$field]['error']!==UPLOAD_ERR_OK ||
            !is_uploaded_file($_FILES[$field]['tmp_name']) ||
            (int)$_FILES[$field]['size']<1 || (int)$_FILES[$field]['size']>5*1048576)
            throw new InvalidArgumentException('Unggah bukti PDF, JPG, atau PNG maksimal 5 MB.');
        $tmp=$_FILES[$field]['tmp_name'];
        $signature=file_get_contents($tmp,FALSE,NULL,0,16);
        if (strncmp($signature,'%PDF-',5)===0) { $mime='application/pdf';$extension='pdf'; }
        elseif (strncmp($signature,"\xff\xd8\xff",3)===0) { $mime='image/jpeg';$extension='jpg'; }
        elseif (strncmp($signature,"\x89PNG\r\n\x1a\n",8)===0) { $mime='image/png';$extension='png'; }
        else throw new InvalidArgumentException('Format bukti harus PDF, JPG, atau PNG.');
        if ($mime!=='application/pdf')
        {
            $image=@getimagesize($tmp);
            if (!$image || $image['mime']!==$mime) throw new InvalidArgumentException('Gambar bukti tidak valid.');
        }
        $key=bin2hex(random_bytes(16)).'.'.$extension;
        $dir=dirname(APPPATH).'/var/documents/'.$folder.'/';
        if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Penyimpanan bukti belum siap.');
        if (!move_uploaded_file($tmp,$dir.$key)) throw new RuntimeException('Bukti gagal disimpan.');
        chmod($dir.$key,0600);
        return array('key'=>$key,'mime'=>$mime,'sha256'=>hash_file('sha256',$dir.$key,TRUE),'path'=>$dir.$key);
    }
}

if (!function_exists('workflow_download_evidence'))
{
    function workflow_download_evidence($folder,$key,$mime,$sha256,$label)
    {
        if (!in_array($folder,array('claims','recovery'),TRUE) ||
            !preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/',$key)) show_404();
        $path=dirname(APPPATH).'/var/documents/'.$folder.'/'.$key;
        if (!is_file($path) || !hash_equals($sha256,hash_file('sha256',$path,TRUE)))
            show_error('Berkas bukti tidak tersedia atau tidak sesuai catatan.',500);
        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        header('Content-Disposition: attachment; filename="'.$label.'.'.pathinfo($key,PATHINFO_EXTENSION).'"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit;
    }
}
