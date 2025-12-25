<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

// Statistiken
$stats = [
    'posts' => (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
    'published' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn(),
    'comments' => (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()
];

$latestPosts = $pdo->query("SELECT title, created_at, status FROM posts ORDER BY created_at DESC LIMIT 5")->fetchAll();
$latestComments = $pdo->query("SELECT author_name, content, created_at FROM comments ORDER BY created_at DESC LIMIT 5")->fetchAll();

include 'header.php'; 
?>

<header class="top-header">
    <h1>📊 Dashboard Übersicht</h1>
</header>

<div class="content-area">
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #3182ce;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;">GESAMT BEITRÄGE</div>
            <div style="font-size: 2.2rem; font-weight: 800;"><?= $stats['posts'] ?></div>
        </div>
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #38a169;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;">ÖFFENTLICH</div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #38a169;"><?= $stats['published'] ?></div>
        </div>
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #805ad5;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;">KOMMENTARE</div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #3182ce;"><?= $stats['comments'] ?></div>
        </div>
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #e53e3e;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;">BENUTZER</div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #e53e3e;"><?= $stats['users'] ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
        <div class="card">
            <div style="padding: 15px 20px; border-bottom: 1px solid #edf2f7; font-weight: bold; display: flex; justify-content: space-between;">
                <span>📝 Neueste Beiträge</span>
                <a href="posts.php" style="font-size: 12px; color: #3182ce;">Alle</a>
            </div>
            <div style="padding: 10px;">
                <?php foreach($latestPosts as $p): ?>
                <div style="padding: 12px; border-bottom: 1px solid #f7fafc; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($p['title']) ?></div>
                        <div style="font-size: 11px; color: #a0aec0;"><?= date('d.m.Y', strtotime($p['created_at'])) ?></div>
                    </div>
                    <span style="font-size: 10px; padding: 2px 8px; border-radius: 10px; background: <?= $p['status']==='published'?'#c6f6d5':'#feebc8' ?>;">
                        <?= $p['status'] ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div style="padding: 15px 20px; border-bottom: 1px solid #edf2f7; font-weight: bold; display: flex; justify-content: space-between;">
                <span>💬 Neueste Kommentare</span>
                <a href="comments.php" style="font-size: 12px; color: #3182ce;">Alle</a>
            </div>
            <div style="padding: 10px;">
                <?php foreach($latestComments as $c): ?>
                <div style="padding: 12px; border-bottom: 1px solid #f7fafc;">
                    <div style="font-size: 13px;"><strong><?= htmlspecialchars($c['author_name']) ?></strong></div>
                    <div style="font-size: 13px; color: #4a5568; margin: 4px 0;"><?= htmlspecialchars(mb_strimwidth($c['content'], 0, 60, "...")) ?></div>
                    <div style="font-size: 11px; color: #a0aec0;"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div style="padding: 15px 20px; border-bottom: 1px solid #edf2f7; font-weight: bold;">🖥️ System & Server Information</div>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr style="border-bottom: 1px solid #f7fafc;">
                <td style="padding: 15px 20px; color: #718096; width: 30%;">Server Software</td>
                <td style="padding: 15px 20px; font-weight: 600;"><?= $_SERVER['SERVER_SOFTWARE'] ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #f7fafc;">
                <td style="padding: 15px 20px; color: #718096;">PHP Version</td>
                <td style="padding: 15px 20px; font-weight: 600;"><?= PHP_VERSION ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #f7fafc;">
                <td style="padding: 15px 20px; color: #718096;">Datenbank Treiber</td>
                <td style="padding: 15px 20px; font-weight: 600;"><?= $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?> (<?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?>)</td>
            </tr>
            <tr style="border-bottom: 1px solid #f7fafc;">
                <td style="padding: 15px 20px; color: #718096;">Protokoll</td>
                <td style="padding: 15px 20px; font-weight: 600;"><?= $_SERVER['SERVER_PROTOCOL'] ?></td>
            </tr>
            <tr>
                <td style="padding: 15px 20px; color: #718096;">Konfiguration</td>
                <td style="padding: 15px 20px;">
                    <?= is_writable(__DIR__ . '/../config/config.ini') 
                        ? '<span style="color: #38a169; font-weight: bold;">✅ config.ini ist schreibbar</span>' 
                        : '<span style="color: #e53e3e; font-weight: bold;">❌ config.ini schreibgeschützt</span>' ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>