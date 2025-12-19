<?php
declare(strict_types=1);
session_start();

// Fehlerberichterstattung für AJAX unterdrücken, damit kein HTML die JSON-Antwort stört
error_reporting(0);
ini_set('display_errors', '0');

if (empty($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Upload-Fehler']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowedTypes)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ungültiger Dateityp']);
    exit;
}

// ABSOLUTER PFAD im Docker-Container
// Gemäß deiner docker-compose: ./uploads -> /var/www/html/public/uploads
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
    echo json_encode(['success' => false, 'message' => 'Fehler beim Verschieben. Rechte prüfen!']);
}