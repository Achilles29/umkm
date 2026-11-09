<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('category_art_url'))
{
    function category_art_url($slug)
    {
        $slug=(string)$slug;
        if (!preg_match('/^[a-z0-9-]{1,100}$/',$slug) || !is_file(FCPATH.'assets/categories/'.$slug.'.svg'))
            $slug='ritel';
        return base_url('assets/categories/'.$slug.'.svg');
    }
}
