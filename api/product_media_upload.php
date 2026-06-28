<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !is_uploaded_file($file['tmp_name'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing upload file.']);
    exit;
}

$allowed = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
    'video/mp4', 'video/webm', 'video/quicktime',
    'application/pdf',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unsupported file type.']);
    exit;
}

$maxBytes = 25 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File exceeds 25 MB limit.']);
    exit;
}

$sessionKey = session_id() ?: bin2hex(random_bytes(8));
$stagingDir = dirname(__DIR__) . '/uploads/products/staging/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionKey);
if (!is_dir($stagingDir)) {
    mkdir($stagingDir, 0755, true);
}

$original = (string) ($file['name'] ?? 'media');
$extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
if ($extension === '') {
    $extension = match (true) {
        str_starts_with($mime, 'video/') => 'mp4',
        $mime === 'application/pdf' => 'pdf',
        default => 'jpg',
    };
}

$filename = uniqid('media_', true) . '.' . $extension;
$destination = $stagingDir . '/' . $filename;

$optimized = false;
if (str_starts_with($mime, 'image/') && $mime !== 'image/gif' && function_exists('imagecreatefromstring')) {
    $raw = file_get_contents($file['tmp_name']);
    $image = $raw !== false ? @imagecreatefromstring($raw) : false;
    if ($image !== false) {
        $width = imagesx($image);
        $height = imagesy($image);
        $maxEdge = 1920;
        if ($width > $maxEdge || $height > $maxEdge) {
            $scale = min($maxEdge / max($width, 1), $maxEdge / max($height, 1));
            $newW = (int) round($width * $scale);
            $newH = (int) round($height * $scale);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $optimized = true;
        }
        if ($mime === 'image/png') {
            imagepng($image, $destination, 6);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            imagewebp($image, $destination, 82);
            $optimized = true;
        } else {
            imagejpeg($image, $destination, 82);
            $optimized = true;
        }
        imagedestroy($image);
    }
}

if (!is_file($destination)) {
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Unable to store uploaded file.']);
        exit;
    }
}

$relative = 'uploads/products/staging/' . basename($stagingDir) . '/' . $filename;

echo json_encode([
    'ok' => true,
    'item' => [
        'path' => $relative,
        'url' => public_asset_url($relative),
        'name' => $original,
        'mime' => $mime,
        'size' => filesize($destination) ?: (int) ($file['size'] ?? 0),
        'optimized' => $optimized,
        'type' => str_starts_with($mime, 'video/') ? 'video' : ($mime === 'application/pdf' ? 'pdf' : 'image'),
    ],
]);
