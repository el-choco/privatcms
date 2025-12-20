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
$langOverride = isset($_GET['lang']) ? (string)$_GET['lang'] : null;
$i18n = I18n::fromConfig($ini, $langOverride);

$dbCfg = $ini['database'] ?? [];
$pdo = (new Database($dbCfg))->pdo();

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$notice = '';
$error = '';

// --- Aktionen verarbeiten ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $error = $i18n->t('posts.error_csrf');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');

        if ($id > 0) {
            if ($action === 'approve') {
                $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?")->execute([$id]);
                $notice = "Kommentar freigegeben.";
            } elseif ($action === 'spam') {
                $pdo->prepare("UPDATE comments SET status = 'spam' WHERE id = ?")->execute([$id]);
                $notice = "Als Spam markiert.";
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
                $notice = "Kommentar gelöscht.";
            }
        }
    }
}

// Kommentare laden
$comments = $pdo->query("
    SELECT c.*, p.title as post_title 
    FROM comments c 
    JOIN posts p ON c.post_id = p.id 
    ORDER BY c.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($i18n->locale()) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($i18n->t('nav.comments')) ?> – PiperBlog Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/admin/assets/styles/admin.css" rel="stylesheet">
    <style>
        /* Zusätzliches Styling für die klare Abgrenzung (Bild 3 Optimierung) */
        .comment-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 8px;
        }
        .comment-body {
            background: #fcfcfc;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid #3498db;
            margin: 10px 0;
            color: #333;
            line-height: 1.5;
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-spam { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h2 class="brand">Admin</h2>
            <nav>
                <a href="/admin/dashboard.php"><?= htmlspecialchars($i18n->t('nav.dashboard')) ?></a>
                <a href="/admin/posts.php"><?= htmlspecialchars($i18n->t('nav.posts')) ?></a>
                <a href="/admin/comments.php" class="active"><?= htmlspecialchars($i18n->t('nav.comments')) ?></a>
                <a href="/admin/files.php"><?= htmlspecialchars($i18n->t('nav.files')) ?></a>
                <a href="/admin/categories.php"><?= htmlspecialchars($i18n->t('nav.categories')) ?></a>
                <a href="/admin/settings.php"><?= htmlspecialchars($i18n->t('nav.settings')) ?></a>
                <a href="/admin/logout.php"><?= htmlspecialchars($i18n->t('nav.logout')) ?></a>
            </nav>
        </aside>

        <main class="admin-content">
            <div class="topbar">
                <h1 style="margin:0"><?= htmlspecialchars($i18n->t('nav.comments')) ?></h1>
                <div class="lang-switch">
                    <a href="?lang=de" class="<?= $i18n->locale()==='de'?'active':'' ?>">DE</a>
                    <a href="?lang=en" class="<?= $i18n->locale()==='en'?'active':'' ?>">EN</a>
                </div>
            </div>

            <?php if ($notice): ?><div class="notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="content-wrapper" style="margin-top: 20px;">
                <?php if (empty($comments)): ?>
                    <p class="muted">Keine Kommentare vorhanden.</p>
                <?php else: ?>
                    <?php foreach ($comments as $c): ?>
                        <div class="comment-card">
                            <div class="comment-header">
                                <span>
                                    <strong><?= htmlspecialchars($c['author_name']) ?></strong> 
                                    <span class="muted">schrieb zu</span> 
                                    <span style="color: #2c3e50; font-weight: 500;"><?= htmlspecialchars($c['post_title']) ?></span>
                                </span>
                                <span class="badge-status status-<?= $c['status'] ?>">
                                    <?= htmlspecialchars($c['status']) ?>
                                </span>
                            </div>
                            
                            <div class="comment-body">
                                <?= nl2br(htmlspecialchars($c['content'])) ?>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                                <small class="muted">
                                    <?= date('d.m.Y H:i', strtotime($c['created_at'])) ?> | <?= htmlspecialchars($c['author_email'] ?: 'Keine E-Mail') ?>
                                </small>
                                
                                <div style="display: flex; gap: 8px;">
                                    <?php if ($c['status'] !== 'approved'): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-sm" type="submit">Freigeben</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($c['status'] !== 'spam'): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action" value="spam">
                                            <button class="btn btn-sm" style="background:#f39c12; border-color:#e67e22" type="submit">Spam</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" style="display:inline" onsubmit="return confirm('Möchtest du diesen Kommentar wirklich löschen?')">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button class="btn btn-sm danger" type="submit">Löschen</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>