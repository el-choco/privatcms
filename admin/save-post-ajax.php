<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { http_response_code(403); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$data = json_decode(file_get_contents('php://input'), true);
if ($data && isset($data['id'])) {
    $stmt = $pdo->prepare("UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$data['content'], $data['id']]);
    echo json_encode(['status' => 'ok']);
}