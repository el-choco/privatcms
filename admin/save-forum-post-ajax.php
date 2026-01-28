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
        $id = (int)$data['id'];
        
        $sqlThread = "UPDATE forum_threads SET 
                      title = ?, 
                      slug = ?,
                      board_id = ?,
                      label_id = ?,
                      is_sticky = ?, 
                      is_locked = ?,
                      updated_at = NOW() 
                      WHERE id = ?";
        
        $pdo->prepare($sqlThread)->execute([
            trim($data['title']),
            trim($data['slug']),
            (int)$data['board_id'],
            (int)$data['label_id'] ?: null,
            (int)$data['is_sticky'],
            (int)$data['is_locked'],
            $id
        ]);

        $sqlPost = "UPDATE forum_posts 
                    SET content = ?, updated_at = NOW() 
                    WHERE thread_id = ? 
                    ORDER BY created_at ASC LIMIT 1";
                    
        $pdo->prepare($sqlPost)->execute([
            $data['content'],
            $id
        ]);
        
        echo json_encode(['status' => 'ok']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
}