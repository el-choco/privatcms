<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { exit; }

require_once __DIR__ . '/../src/App/Database.php';
use App\Database;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new Database($ini['database'] ?? []))->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $content = $_POST['content'] ?? '';
    // Optional: Falls du den Titel im Editor auch änderbar machst:
    // $title = $_POST['title'] ?? '';

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $id]);
    }

    // Zurück zur Übersicht oder zum Editor
    header("Location: /admin/posts.php?success=1");
    exit;
}