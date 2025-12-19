<?php
// Zentrale Header-Datei für alle Admin-Seiten
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - <?= htmlspecialchars($ini['app']['name'] ?? 'Blog') ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap">
    <style>
        body, html { margin: 0; padding: 0; height: 100vh; overflow: hidden; font-family: 'Inter', sans-serif; background: #f7fafc; }
        .admin-wrapper { display: flex; height: 100vh; width: 100vw; }

        /* Sidebar */
        .admin-sidebar { width: 260px; background: #1a202c; color: #fff; flex-shrink: 0; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 25px 20px; font-size: 1.5rem; font-weight: bold; border-bottom: 1px solid #2d3748; color: #63b3ed; }
        .admin-sidebar nav { flex: 1; padding: 15px 0; }
        .admin-sidebar nav a { display: block; padding: 12px 20px; color: #cbd5e0; text-decoration: none; transition: 0.2s; border-left: 3px solid transparent; }
        .admin-sidebar nav a:hover, .admin-sidebar nav a.active { background: #2d3748; color: #fff; border-left-color: #63b3ed; }
        .sidebar-footer { padding: 15px; border-top: 1px solid #2d3748; }

        /* Content Layout */
        .main-viewport { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .top-header { background: #fff; padding: 20px 30px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; min-height: 70px; box-sizing: border-box; }
        .top-header h1 { margin: 0; font-size: 1.4rem; color: #2d3748; }
        .content-area { flex: 1; padding: 30px; overflow-y: auto; }

        /* Cards & Tables */
        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid #e2e8f0; background: #fff; transition: 0.2s; color: #4a5568; }
        .btn-primary { background: #3182ce; color: #fff; border: none; }
        .btn-primary:hover { background: #2b6cb0; }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="sidebar-brand"><?= htmlspecialchars($ini['app']['name'] ?? 'Admin') ?></div>
        <nav>
            <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">📊 Dashboard</a>
            <a href="posts.php" class="<?= ($current_page=='posts.php' || $current_page=='post-edit.php')?'active':'' ?>">📝 Beiträge</a>
            <a href="files.php" class="<?= $current_page=='files.php'?'active':'' ?>">📁 Dateien</a>
            <a href="categories.php" class="<?= $current_page=='categories.php'?'active':'' ?>">🏷️ Kategorien</a>
            <a href="comments.php" class="<?= $current_page=='comments.php'?'active':'' ?>">💬 Kommentare</a>
            <a href="settings.php" class="<?= $current_page=='settings.php'?'active':'' ?>">⚙️ Einstellungen</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" style="color: #fc8181; text-decoration: none; font-size: 14px;">🚪 Abmelden</a>
        </div>
    </aside>
    <main class="main-viewport">