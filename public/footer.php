<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($settings)) $settings = [];
if (!isset($t)) $t = [];
if (!isset($iniLang)) $iniLang = [];

$txtTotal = $t['footer_total'] ?? ($iniLang['frontend']['footer_stats_total'] ?? 'Total Visits');
$txtToday = $t['footer_today'] ?? ($iniLang['frontend']['footer_stats_today'] ?? 'Today');
$blogTitle = $settings['blog_title'] ?? 'PiperBlog';

$totalViews = 0;
$todayViews = 0;

if (isset($pdo)) {
    try {
        $footerPages = $pdo->query("SELECT title, slug FROM pages WHERE status='published' AND show_in_footer=1 ORDER BY title ASC")->fetchAll();
        $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn();
        $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn();
    } catch (Exception $e) { $footerPages = []; }
} else {
    $footerPages = [];
}
?>
<footer style="text-align: center; padding: 40px 20px; color: var(--text-muted); font-size: 0.9rem; margin-top: 50px; border-top: 1px solid var(--border);">
    
    <?php if (!empty($footerPages)): ?>
        <div style="margin-bottom: 15px;">
            <?php foreach($footerPages as $fp): ?>
                <a href="/p/<?= htmlspecialchars($fp['slug']) ?>" style="margin: 0 10px; text-decoration: none; color: var(--text-muted); font-weight: 500;">
                    <?= htmlspecialchars($fp['title']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 10px;">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($blogTitle) ?>
    </div>
    <div>
        📊 <?= htmlspecialchars($txtTotal) ?>: <strong><?= number_format($totalViews) ?></strong>
        &bull; 
        📅 <?= htmlspecialchars($txtToday) ?>: <strong><?= number_format($todayViews) ?></strong>
    </div>
</footer>
<?php
$lbClose = $t['lightbox_close'] ?? ($iniLang['lightbox']['close'] ?? 'Close');
?>
<link href="/assets/styles/lightbox.css" rel="stylesheet">
<script>
    document.body.dataset.lbClose = "<?= htmlspecialchars($lbClose) ?>";
</script>
<script src="/assets/js/lightbox.js"></script>
<?php
$cText   = $t['cookie_text']   ?? ($iniLang['cookie']['text']   ?? 'We use cookies.');
$cAccept = $t['cookie_accept'] ?? ($iniLang['cookie']['accept'] ?? 'OK');
$cMore   = $t['cookie_more']   ?? ($iniLang['cookie']['more']   ?? 'Info');
$cLink   = $t['cookie_link']   ?? ($iniLang['cookie']['link']   ?? '#');
?>
<link href="/assets/styles/cookie.css" rel="stylesheet">

<div id="cookieBanner" class="cookie-banner">
    <p><?= htmlspecialchars($cText) ?> <a href="<?= htmlspecialchars($cLink) ?>" style="color:var(--primary);"><?= htmlspecialchars($cMore) ?></a></p>
    <button id="acceptCookies" class="btn-cookie"><?= htmlspecialchars($cAccept) ?></button>
</div>
<script src="/assets/js/cookie.js"></script>