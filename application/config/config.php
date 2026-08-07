<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
$scheme = $https ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = trim($scriptDir, '/');
$firstSegment = $basePath !== '' ? strtok($basePath, '/') : '';
$config['base_url'] = $scheme . '://' . $host . ($firstSegment ? '/' . $firstSegment : '') . '/';
$config['index_page'] = 'index.php';
