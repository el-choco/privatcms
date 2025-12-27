<?php
declare(strict_types=1);
session_start();

if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en', 'fr', 'es'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? 'de';

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$settings = [];
try {
    foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { 
        $settings[$row['setting_key']] = $row['setting_value']; 
    }
} catch (Exception $e) {}

$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$fLang = $iniLang['forum'] ?? [];

// ---------------------------------------------------------------------------
// 1. RECHTE PRÜFEN (RBAC SYSTEM)
// ---------------------------------------------------------------------------
$userPerms = [];
if (!empty($_SESSION['user_id'])) {
    $stmtP = $pdo->prepare("
        SELECT p.slug FROM permissions p 
        JOIN permission_role pr ON p.id = pr.permission_id 
        JOIN users u ON pr.role_id = u.role_id 
        WHERE u.id = ?
    ");
    $stmtP->execute([$_SESSION['user_id']]);
    $userPerms = $stmtP->fetchAll(PDO::FETCH_COLUMN);
}
$canManageBoards = in_array('forum_manage_boards', $userPerms);
// ---------------------------------------------------------------------------

// NEU: Board erstellen (Wenn Recht vorhanden)
if ($canManageBoards && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_board'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['desc']);
    $isCat = isset($_POST['is_category']) ? 1 : 0;
    $parent = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Slug generieren
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    if (empty($slug)) $slug = 'board-' . time();

    // Sort Order automatisch ans Ende setzen
    $sort = (int)$pdo->query("SELECT MAX(sort_order) FROM forum_boards")->fetchColumn() + 10;

    $stmt = $pdo->prepare("INSERT INTO forum_boards (title, description, slug, is_category, parent_id, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $desc, $slug, $isCat, $parent, $sort]);
    
    header("Location: /forum.php"); exit;
}

try {
    $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn();
    $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn();
} catch (Exception $e) { $totalViews = 0; $todayViews = 0; }
$t = [
    'footer_total' => $iniLang['frontend']['footer_stats_total'] ?? 'Total Visits',
    'footer_today' => $iniLang['frontend']['footer_stats_today'] ?? 'Today',
    'admin'        => $iniLang['frontend']['login_link'] ?? 'Admin'
];

$allBoards = $pdo->query("SELECT b.*, 
    (SELECT COUNT(*) FROM forum_threads t WHERE t.board_id = b.id) as thread_count,
    (SELECT COUNT(*) FROM forum_posts p JOIN forum_threads t2 ON p.thread_id = t2.id WHERE t2.board_id = b.id) as post_count
    FROM forum_boards b 
    ORDER BY b.sort_order ASC, b.created_at ASC")->fetchAll();

$categories = [];
$forums = [];

foreach ($allBoards as $b) {
    if ($b['is_category'] == 1) {
        $categories[] = $b;
    } else {
        $forums[] = $b;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($fLang['title'] ?? 'Forum') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="/assets/styles/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
      
<style>
.forum-section { margin-bottom: 30px; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; background: var(--bg-card); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.forum-cat-title { background: linear-gradient(90deg, var(--primary), #2b6cb0); color: #fff; padding: 12px 20px; font-size: 1.1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; justify-content: space-between; }
.forum-list { display: flex; flex-direction: column; }
.forum-card { display: flex; align-items: center; padding: 18px 25px; text-decoration: none; color: inherit; border-bottom: 1px solid var(--border); transition: background 0.15s; }
.forum-card:last-child { border-bottom: none; }
.forum-card:hover { background: var(--bg-body); }
.forum-icon { font-size: 1.8rem; color: var(--primary); margin-right: 20px; width: 40px; text-align: center; opacity: 0.8; }
.forum-content { flex: 1; }
.forum-title { margin: 0 0 4px 0; font-size: 1.15rem; color: var(--text-main); font-weight: 600; }
.forum-desc { color: var(--text-muted); font-size: 0.9rem; line-height: 1.4; }
.forum-stats { text-align: right; min-width: 140px; font-size: 0.85rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 2px; }
.badge-count { background: #edf2f7; color: #4a5568; padding: 2px 8px; border-radius: 12px; font-weight: 600; font-size: 0.75rem; margin-right: 5px; }
[data-theme="dark"] .badge-count { background: #2d3748; color: #cbd5e0; }
:not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}

/* Modal für Board-Erstellung */
#createBoardModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
.modal-box { background: var(--bg-card); padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; border: 1px solid var(--border); }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
.form-control { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text-main); }
</style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; border-bottom: 3px solid var(--primary); padding-bottom: 10px; margin-bottom: 40px;">
        <h1 style="margin:0; color: var(--primary);">
            <?= htmlspecialchars($fLang['title'] ?? 'Forum') ?>
        </h1>
        <?php if($canManageBoards): ?>
            <button onclick="document.getElementById('createBoardModal').style.display='flex'" class="btn-primary" style="font-size:0.9rem;">
                <i class="fa-solid fa-plus"></i> Neues Board
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($allBoards)): ?>
        <div style="background:var(--bg-card); border:1px solid var(--border); padding:40px; border-radius:12px; text-align:center; color:var(--text-muted);">
            <?= htmlspecialchars($fLang['no_boards'] ?? 'No boards found.') ?>
        </div>
    <?php endif; ?>

    <?php 
    foreach ($categories as $cat): 
        $catChildren = array_filter($forums, fn($f) => $f['parent_id'] == $cat['id']);
    ?>
        <div class="forum-section">
            <div class="forum-cat-title">
                <span><i class="fa-regular fa-folder-open" style="margin-right:10px;"></i> <?= htmlspecialchars($cat['title']) ?></span>
                </div>
            <div class="forum-list">
                <?php if (empty($catChildren)): ?>
                    <div style="padding:20px; text-align:center; color:var(--text-muted); font-style:italic;">
                        <?= htmlspecialchars($fLang['no_threads'] ?? 'No forums here.') ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($catChildren as $child): ?>
                        <a href="/forum/<?= htmlspecialchars($child['slug']) ?>" class="forum-card">
                            <div class="forum-icon"><i class="fa-solid fa-folder"></i></div>
                            <div class="forum-content">
                                <h3 class="forum-title"><?= htmlspecialchars($child['title']) ?></h3>
                                <div class="forum-desc"><?= htmlspecialchars($child['description']) ?></div>
                            </div>
                            <div class="forum-stats">
                                <div><span class="badge-count"><?= number_format((int)$child['thread_count']) ?></span> <?= htmlspecialchars($fLang['thread_title'] ?? 'Topics') ?></div>
                                <div><span class="badge-count"><?= number_format((int)$child['post_count']) ?></span> <?= htmlspecialchars($fLang['posts'] ?? 'Posts') ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php 
    $orphans = array_filter($forums, fn($f) => empty($f['parent_id']));
    if (!empty($orphans)): 
    ?>
        <div class="forum-section">
            <div class="forum-cat-title">
                <span><i class="fa-solid fa-globe" style="margin-right:10px;"></i> <?= htmlspecialchars($fLang['general'] ?? 'General') ?></span>
            </div>
            <div class="forum-list">
                <?php foreach ($orphans as $orphan): ?>
                    <a href="/forum/<?= htmlspecialchars($orphan['slug']) ?>" class="forum-card">
                        <div class="forum-icon"><i class="fa-solid fa-folder"></i></div>
                        <div class="forum-content">
                            <h3 class="forum-title"><?= htmlspecialchars($orphan['title']) ?></h3>
                            <div class="forum-desc"><?= htmlspecialchars($orphan['description']) ?></div>
                        </div>
                        <div class="forum-stats">
                            <div><span class="badge-count"><?= number_format((int)$orphan['thread_count']) ?></span> <?= htmlspecialchars($fLang['thread_title'] ?? 'Topics') ?></div>
                            <div><span class="badge-count"><?= number_format((int)$orphan['post_count']) ?></span> <?= htmlspecialchars($fLang['posts'] ?? 'Posts') ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</main>

<div id="createBoardModal">
    <div class="modal-box">
        <h2 style="margin-top:0;">Neues Forum/Kategorie erstellen</h2>
        <form method="POST">
            <div class="form-group">
                <label>Titel</label>
                <input type="text" name="title" class="form-control" required placeholder="z.B. Ankündigungen">
            </div>
            <div class="form-group">
                <label>Beschreibung</label>
                <input type="text" name="desc" class="form-control" placeholder="Kurze Beschreibung...">
            </div>
            <div class="form-group">
                <label>Ist eine Haupt-Kategorie?</label>
                <select name="is_category" class="form-control">
                    <option value="0">Nein (Normales Forum)</option>
                    <option value="1">Ja (Überschrift)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Übergeordnete Kategorie (Nur wenn "Nein")</label>
                <select name="parent_id" class="form-control">
                    <option value="">-- Keine --</option>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="button" onclick="document.getElementById('createBoardModal').style.display='none'" class="btn" style="margin-right:10px;">Abbrechen</button>
                <button type="submit" name="create_board" class="btn-primary">Erstellen</button>
            </div>
        </form>
    </div>
</div>

<?php
    try {
        $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn();
        $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn();
    } catch (Exception $e) { 
        $totalViews = 0; 
        $todayViews = 0; 
    }

    $t['footer_total'] = $iniLang['frontend']['footer_stats_total'] ?? 'Total Visits';
    $t['footer_today'] = $iniLang['frontend']['footer_stats_today'] ?? 'Today';

    include 'footer.php'; 
?>

<script>
    function toggleLang() { document.getElementById('langMenu').classList.toggle('show'); }
    window.addEventListener('click', function(e) { if (!document.getElementById('langDropdown').contains(e.target)) { document.getElementById('langMenu').classList.remove('show'); } });
    
    const toggleBtn = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        });
    }
</script>
</body>
</html>