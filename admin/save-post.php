<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { exit; }

require_once __DIR__ . '/../src/App/Database.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $content = $_POST['content'] ?? '';

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $id]);
    }

    header("Location: /admin/posts.php?success=1");
    exit;
}