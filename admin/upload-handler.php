<?php
declare(strict_types=1);
session_start();

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../src/App/Database.php';

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$uhLang = $t_temp['upload_handler'] ?? [];

if (empty($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'error' => $uhLang['error_auth'] ?? 'Auth Error']);
    exit;
}

$file = $_FILES['file'] ?? $_FILES['image'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'error' => $uhLang['error_upload'] ?? 'Upload Error']);
    exit;
}

$uploadDir = __DIR__ . '/../public/uploads/';
if (isset($_FILES['image'])) {
    $uploadDir = __DIR__ . '/../public/uploads/images/';
}

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = basename($file['name']);
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

if (file_exists($uploadDir . $filename)) {
    $info = pathinfo($filename);
    $filename = $info['filename'] . '_' . time() . '.' . ($info['extension'] ?? '');
}

$targetPath = $uploadDir . $filename;

header('Content-Type: application/json');
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode([
        'status' => 'ok', 
        'success' => true,
        'filename' => $filename,
        'url' => '/uploads/' . (isset($_FILES['image']) ? 'images/' : '') . $filename
    ]);
} else {
    echo json_encode(['status' => 'error', 'error' => $uhLang['error_move'] ?? 'Move Error']);
}