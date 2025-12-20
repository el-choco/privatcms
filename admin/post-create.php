<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? 'Neuer Beitrag';
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    
    // Holen wir die ID des aktuell eingeloggten Admins
    // Falls deine Session die ID nicht speichert, nehmen wir die 1 (Standard-Admin)
    $userId = $_SESSION['admin']['id'] ?? 1; 

    // Wir fügen user_id in das INSERT Statement ein
    $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, status, user_id, created_at) VALUES (?, ?, '', 'draft', ?, NOW())");
    $stmt->execute([$title, $slug, $userId]);
    
    header("Location: /admin/post-edit.php?id=" . $pdo->lastInsertId());
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Neuer Beitrag</title>
    <link href="/admin/assets/styles/admin.css" rel="stylesheet">
</head>
<body class="admin-layout">
    <aside class="admin-sidebar">
        <h2 class="brand">Admin</h2>
        <nav><a href="/admin/posts.php">← Abbrechen</a></nav>
    </aside>
    <main class="admin-content">
        <div class="topbar"><h1>Neuen Beitrag erstellen</h1></div>
        <section class="panel">
            <form method="post" style="display: flex; flex-direction: column; gap: 15px; max-width: 500px;">
                <label>Titel des Beitrags</label>
                <input type="text" name="title" class="input" placeholder="z.B. Mein erster Sommertag" required autofocus>
                <button type="submit" class="btn" style="background: #3498db; color: white;">Beitrag anlegen & Editor öffnen</button>
            </form>
        </section>
    </main>
</body>
</html>