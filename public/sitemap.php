<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$baseUrl = rtrim($ini['app']['url'] ?? 'https://privatcms.pacos-office.sbs', '/');

header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <url>
        <loc><?= $baseUrl ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/forum.php</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/login.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <?php
    try {
        $stmt = $pdo->query("SELECT slug, updated_at, created_at FROM posts WHERE status = 'published' ORDER BY created_at DESC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $post) {
            $date = new DateTime($post['updated_at'] ?? $post['created_at']);
            echo "\t<url>\n";
            echo "\t\t<loc>" . htmlspecialchars($baseUrl . '/artikel/' . $post['slug']) . "</loc>\n";
            echo "\t\t<lastmod>" . $date->format('Y-m-d') . "</lastmod>\n";
            echo "\t\t<changefreq>weekly</changefreq>\n";
            echo "\t\t<priority>0.8</priority>\n";
            echo "\t</url>\n";
        }
    } catch (Exception $e) {}
    ?>

    <?php
    try {
        $stmt = $pdo->query("SELECT slug, updated_at, created_at FROM pages WHERE status = 'published'");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $page) {
            $date = new DateTime($page['updated_at'] ?? $page['created_at']);
            echo "\t<url>\n";
            echo "\t\t<loc>" . htmlspecialchars($baseUrl . '/p/' . $page['slug']) . "</loc>\n";
            echo "\t\t<lastmod>" . $date->format('Y-m-d') . "</lastmod>\n";
            echo "\t\t<changefreq>monthly</changefreq>\n";
            echo "\t\t<priority>0.6</priority>\n";
            echo "\t</url>\n";
        }
    } catch (Exception $e) {}
    ?>

    <?php
    try {
        $stmt = $pdo->query("SELECT slug, created_at FROM forum_boards");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $board) {
            $date = new DateTime($board['created_at']);
            echo "\t<url>\n";
            echo "\t\t<loc>" . htmlspecialchars($baseUrl . '/forum/' . $board['slug']) . "</loc>\n";
            echo "\t\t<lastmod>" . $date->format('Y-m-d') . "</lastmod>\n";
            echo "\t\t<changefreq>daily</changefreq>\n";
            echo "\t\t<priority>0.7</priority>\n";
            echo "\t</url>\n";
        }
    } catch (Exception $e) {}
    ?>

    <?php
    try {
        $stmt = $pdo->query("SELECT slug, updated_at, created_at FROM forum_threads");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $thread) {
            $date = new DateTime($thread['updated_at'] ?? $thread['created_at']);
            echo "\t<url>\n";
            echo "\t\t<loc>" . htmlspecialchars($baseUrl . '/forum/thread/' . $thread['slug']) . "</loc>\n";
            echo "\t\t<lastmod>" . $date->format('Y-m-d') . "</lastmod>\n";
            echo "\t\t<changefreq>hourly</changefreq>\n";
            echo "\t\t<priority>0.6</priority>\n";
            echo "\t</url>\n";
        }
    } catch (Exception $e) {}
    ?>

</urlset>