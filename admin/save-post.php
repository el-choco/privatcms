<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { exit; }

require_once __DIR__ . '/../src/App/Database.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentUser = $_SESSION['admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $content = $_POST['content'] ?? '';

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT author_id, title FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if (!$post) { exit; }

        $isAdmin = ($currentUser['role'] ?? 'viewer') === 'admin';
        $isOwner = (int)($post['author_id'] ?? 0) === (int)$currentUser['id'];

        if (!$isAdmin && !$isOwner) {
            exit; 
        }

        $stmt = $pdo->prepare("UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $id]);

        try {
            $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'update', ?, ?)");
            $logStmt->execute([
                $currentUser['id'],
                "Updated content of post ID {$id}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) { }
    }

    header("Location: /admin/posts.php?success=1");
    exit;
}