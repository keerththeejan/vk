<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/init.php';
vk_bootstrap_module('vehicle_booking');
unset($_SESSION['vk_vehicle_customer_id']);
flash_set('success', 'Logged out.');
redirect('/vehicle/login.php');
