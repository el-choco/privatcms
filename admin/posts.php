<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$i18n = I18n::fromConfig($ini, $_GET['lang'] ?? null);
$db = new Database($ini['database'] ?? []);
$pdo = $db->pdo();

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// --- AKTIONEN (Löschen, Status ändern) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'publish') {
            $pdo->prepare("UPDATE posts SET status = 'published' WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'unpublish') {
            $pdo->prepare("UPDATE posts SET status = 'draft' WHERE id = ?")->execute([$id]);
        }
    }
    header('Location: posts.php');
    exit;
}

// --- DATEN LADEN ---
$search = $_GET['q'] ?? '';
$sql = "SELECT * FROM posts";
$params = [];
if ($search) {
    $sql .= " WHERE title LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
    <meta charset="utf-8">
    <title>Beiträge - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/admin/assets/styles/admin.css" rel="stylesheet">
    <style>
        /* Zusätzlicher Schutz, damit alle Buttons wirklich gleich aussehen */
        .item-actions { display: flex; gap: 8px; align-items: center; }
        .btn { cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; font-weight: bold; }
        .list li { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body class="admin-layout">

    <aside class="admin-sidebar">
        <h2 class="brand">Admin</h2>
        <nav>
            <a href="/admin/dashboard.php">Dashboard</a>
            <a href="/admin/posts.php" class="active">Beiträge</a>
            <a href="/admin/comments.php">Kommentare</a>
            <a href="/admin/files.php">Dateien</a>
            <a href="/admin/categories.php">Kategorien</a>
            <a href="/admin/settings.php">Einstellungen</a>
            <a href="/admin/logout.php">Abmelden</a>
        </nav>
    </aside>

    <main class="admin-content">
        <div class="topbar">
            <h1>Beiträge verwalten</h1>
            <a href="/admin/post-create.php" class="btn" style="background:#27ae60; color:white; border:none;">
                + Neuer Beitrag
            </a>
        </div>

        <section class="panel">
            <ul class="list">
                <?php if (empty($items)): ?>
                    <li class="muted">Keine Beiträge gefunden.</li>
                <?php else: ?>
                    <?php foreach ($items as $p): ?>
                        <li>
                            <div class="item-info">
                                <strong style="font-size:1.1rem;"><?= htmlspecialchars((string)$p['title']) ?></strong>
                                <div class="muted" style="font-size:0.85rem; margin-top:4px;">
                                    <?= date('d.m.Y', strtotime((string)$p['created_at'])) ?> | 
                                    <span class="badge"><?= htmlspecialchars((string)$p['status']) ?></span>
                                </div>
                            </div>
                            
                            <div class="item-actions">
                                <a href="/admin/post-edit.php?id=<?= $p['id'] ?>" class="btn">
                                    Bearbeiten
                                </a>

                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="action" value="<?= $p['status'] === 'published' ? 'unpublish' : 'publish' ?>">
                                    <button type="submit" class="btn">
                                        <?= $p['status'] === 'published' ? 'Entwurf' : 'Veröffentlichen' ?>
                                    </button>
                                </form>

                                <form method="post" onsubmit="return confirm('Wirklich löschen?')" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn">
                                        Löschen
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
    </main>

</body>
</html>