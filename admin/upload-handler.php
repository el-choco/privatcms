<?php
declare(strict_types=1);
session_start();

error_reporting(0);
ini_set('display_errors', '0');

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$uhLang = $t_temp['upload_handler'] ?? [];

if (empty($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $uhLang['error_auth'] ?? 'Auth Error']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $uhLang['error_upload'] ?? 'Upload Error']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowedTypes)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $uhLang['error_type'] ?? 'Invalid Type']);
    exit;
}

$uploadDir = '/var/www/html/public/uploads/images/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$targetPath = $uploadDir . $filename;

header('Content-Type: application/json');
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode([
        'success' => true, 
        'filename' => $filename
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $uhLang['error_move'] ?? 'Move Error']);
}