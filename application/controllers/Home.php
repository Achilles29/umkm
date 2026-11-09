<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller
{
    public function index()
    {
        $this->load->database();
        $this->load->model('Marketplace_model');
        $this->output->set_content_type('text/html', 'utf-8');
        $this->load->view('home', array('categories' => $this->Marketplace_model->categories()));
    }
}
