<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];

$stats = [
    'posts' => (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
    'published' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn(),
    'comments' => (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()
];

try {
    $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn();
    $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn();
} catch (Exception $e) {
    $totalViews = 0; $todayViews = 0;
}

$latestPosts = $pdo->query("SELECT title, created_at, status FROM posts ORDER BY created_at DESC LIMIT 5")->fetchAll();
$latestComments = $pdo->query("SELECT author_name, content, created_at FROM comments ORDER BY created_at DESC LIMIT 5")->fetchAll();

try {
    $topPosts = $pdo->query("SELECT title, views FROM posts WHERE status='published' ORDER BY views DESC LIMIT 5")->fetchAll();
} catch (Exception $e) { $topPosts = []; }

include 'header.php'; 
?>

<header class="top-header" style="margin: 0px 30px">
    <h1><?= htmlspecialchars($t['dashboard']['overview'] ?? 'Dashboard') ?></h1>
</header>

<div class="content-area" style="margin: 30px 30px 30px 30px;"> 
        
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #3182ce;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;"><?= htmlspecialchars($t['dashboard']['cards_posts_total'] ?? 'Posts') ?></div>
            <div style="font-size: 2.2rem; font-weight: 800;"><?= $stats['posts'] ?></div>
        </div>
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #38a169;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;"><?= htmlspecialchars($t['dashboard']['cards_published'] ?? 'Published') ?></div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #38a169;"><?= $stats['published'] ?></div>
        </div>
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #805ad5;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;"><?= htmlspecialchars($t['dashboard']['cards_comments'] ?? 'Comments') ?></div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #3182ce;"><?= $stats['comments'] ?></div>
        </div>
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #e53e3e;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;"><?= htmlspecialchars($t['dashboard']['cards_users'] ?? 'Users') ?></div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #e53e3e;"><?= $stats['users'] ?></div>
        </div>
        
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #d69e2e;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;"><?= htmlspecialchars($t['dashboard']['stat_visits_total'] ?? 'Visits') ?></div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #d69e2e;"><?= number_format($totalViews) ?></div>
        </div>
        <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid #d69e2e;">
            <div style="font-size: 12px; color: #718096; font-weight: bold;"><?= htmlspecialchars($t['dashboard']['stat_visits_today'] ?? 'Today') ?></div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #d69e2e;"><?= number_format($todayViews) ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
        <div class="card">
            <div style="padding: 15px 20px; border-bottom: 1px solid #edf2f7; font-weight: bold; display: flex; justify-content: space-between;">
                <span><?= htmlspecialchars($t['dashboard']['panel_latest_posts'] ?? 'Latest Posts') ?></span>
                <a href="posts.php" style="font-size: 12px; color: #3182ce;"><?= htmlspecialchars($t['dashboard']['link_view_all'] ?? 'All') ?></a>
            </div>
            <div style="padding: 10px;">
                <?php foreach($latestPosts as $p): ?>
                <div style="padding: 12px; border-bottom: 1px solid #f7fafc; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($p['title']) ?></div>
                        <div style="font-size: 11px; color: #a0aec0;"><?= date($t['common']['date_fmt'] ?? 'd.m.Y', strtotime($p['created_at'])) ?></div>
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
                <span><?= htmlspecialchars($t['dashboard']['panel_latest_comments'] ?? 'Latest Comments') ?></span>
                <a href="comments.php" style="font-size: 12px; color: #3182ce;"><?= htmlspecialchars($t['dashboard']['link_view_all'] ?? 'All') ?></a>
            </div>
            <div style="padding: 10px;">
                <?php foreach($latestComments as $c): ?>
                <div style="padding: 12px; border-bottom: 1px solid #f7fafc;">
                    <div style="font-size: 13px;"><strong><?= htmlspecialchars($c['author_name']) ?></strong></div>
                    <div style="font-size: 13px; color: #4a5568; margin: 4px 0;"><?= htmlspecialchars(mb_strimwidth($c['content'], 0, 60, "...")) ?></div>
                    <div style="font-size: 11px; color: #a0aec0;"><?= date($t['common']['date_fmt_full'] ?? 'd.m.Y H:i', strtotime($c['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 30px;">
        <div style="padding: 15px 20px; border-bottom: 1px solid #edf2f7; font-weight: bold; color: #2d3748;">
            <?= htmlspecialchars($t['dashboard']['top_posts_title'] ?? 'Top 5 Posts') ?>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="text-align: left; background: #f7fafc;">
                    <th style="padding: 12px 20px; color: #718096; width: 50px;">#</th>
                    <th style="padding: 12px 20px; color: #718096;"><?= htmlspecialchars($t['dashboard']['col_title'] ?? 'Title') ?></th>
                    <th style="padding: 12px 20px; color: #718096; text-align: right;"><?= htmlspecialchars($t['dashboard']['col_views'] ?? 'Views') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topPosts)): ?>
                    <tr><td colspan="3" style="padding: 20px; text-align: center; color: #a0aec0;"><?= htmlspecialchars($t['dashboard']['no_data'] ?? 'No data') ?></td></tr>
                <?php else: ?>
                    <?php foreach($topPosts as $i => $p): ?>
                    <tr style="border-bottom: 1px solid #f7fafc;">
                        <td style="padding: 12px 20px; font-weight: bold; color: #cbd5e0;"><?= $i + 1 ?></td>
                        <td style="padding: 12px 20px; font-weight: 500;"><?= htmlspecialchars($p['title']) ?></td>
                        <td style="padding: 12px 20px; text-align: right; font-weight: bold; color: #3182ce;"><?= number_format($p['views']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div style="padding: 15px 20px; border-bottom: 1px solid #edf2f7; font-weight: bold;"><?= htmlspecialchars($t['dashboard']['system_title'] ?? 'System Info') ?></div>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr style="border-bottom: 1px solid #f7fafc;">
                <td style="padding: 15px 20px; color: #718096; width: 30%;"><?= htmlspecialchars($t['dashboard']['system_server'] ?? 'Server') ?></td>
                <td style="padding: 15px 20px; font-weight: 600;"><?= $_SERVER['SERVER_SOFTWARE'] ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #f7fafc;">
                <td style="padding: 15px 20px; color: #718096;"><?= htmlspecialchars($t['dashboard']['system_php_ver'] ?? 'PHP') ?></td>
                <td style="padding: 15px 20px; font-weight: 600;"><?= PHP_VERSION ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #f7fafc;">
                <td style="padding: 15px 20px; color: #718096;"><?= htmlspecialchars($t['dashboard']['system_db_driver'] ?? 'DB Driver') ?></td>
                <td style="padding: 15px 20px; font-weight: 600;"><?= $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?> (<?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?>)</td>
            </tr>
            <tr style="border-bottom: 1px solid #f7fafc;">
                <td style="padding: 15px 20px; color: #718096;"><?= htmlspecialchars($t['dashboard']['system_protocol'] ?? 'Protocol') ?></td>
                <td style="padding: 15px 20px; font-weight: 600;"><?= $_SERVER['SERVER_PROTOCOL'] ?></td>
            </tr>
            <tr>
                <td style="padding: 15px 20px; color: #718096;"><?= htmlspecialchars($t['dashboard']['system_config'] ?? 'Config') ?></td>
                <td style="padding: 15px 20px;">
                    <?= is_writable(__DIR__ . '/../config/config.ini') 
                        ? '<span style="color: #38a169; font-weight: bold;">' . htmlspecialchars($t['dashboard']['status_config_ok'] ?? 'OK') . '</span>' 
                        : '<span style="color: #e53e3e; font-weight: bold;">' . htmlspecialchars($t['dashboard']['status_config_no'] ?? 'NO') . '</span>' ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>