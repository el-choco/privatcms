<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { http_response_code(403); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($data['id'])) {
    try {
        $sql = "UPDATE posts SET 
                title = ?, 
                content = ?, 
                hero_image = ?, 
                download_file = ?, 
                category_id = ?, 
                status = ?, 
                is_sticky = ?,
                updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['hero_image'] ?: null,
            $data['download_file'] ?: null, // NEU
            $data['category_id'] ?: null,
            $data['status'],
            (int)$data['is_sticky'],
            (int)$data['id']
        ]);

        echo json_encode(['status' => 'ok']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
}