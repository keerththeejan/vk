<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Staff extends CI_Controller
{
    public function index()
    {
        $data['title'] = 'Our Team';
        $data['staff'] = [
            [
                'name' => 'Vijay Keerththeejan',
                'role' => 'Owner',
                'image' => base_url('assets/images/staff/owner.svg'),
                'description' => 'Expert in Networking and AI Systems',
                'skills' => ['Networking', 'AI Systems'],
            ],
            [
                'name' => 'John Silva',
                'role' => 'Technician',
                'image' => base_url('assets/images/staff/staff1.svg'),
                'description' => 'Hardware and repair specialist',
                'skills' => ['Hardware Repair', 'Printer Service'],
            ],
            [
                'name' => 'Nimal Perera',
                'role' => 'System Admin',
                'image' => base_url('assets/images/staff/staff2.svg'),
                'description' => 'Server and network management',
                'skills' => ['Servers', 'Network Management'],
            ],
        ];

        $this->load->view('staff_list', $data);
    }
}
