<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$fLang = $iniLang['forum'] ?? [];
$peLang = $iniLang['editor'] ?? [];
$aLang = $iniLang['auth'] ?? [];

$settings = [];
try { foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; } } catch (Exception $e) {}
try { $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn(); $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn(); } catch (Exception $e) { $totalViews=0; $todayViews=0; }
$t = ['footer_total' => $iniLang['frontend']['footer_stats_total'] ?? 'Total','footer_today' => $iniLang['frontend']['footer_stats_today'] ?? 'Today','admin' => $iniLang['frontend']['login_link'] ?? 'Admin'];

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM forum_boards WHERE slug = ?");
$stmt->execute([$slug]);
$board = $stmt->fetch();
if (!$board) { http_response_code(404); echo "Board not found"; exit; }

$subBoards = $pdo->prepare("SELECT b.*, 
    (SELECT COUNT(*) FROM forum_threads t WHERE t.board_id = b.id) as thread_count,
    (SELECT COUNT(*) FROM forum_posts p JOIN forum_threads t2 ON p.thread_id = t2.id WHERE t2.board_id = b.id) as post_count
    FROM forum_boards b WHERE parent_id = ? ORDER BY sort_order ASC");
$subBoards->execute([$board['id']]);
$subBoards = $subBoards->fetchAll();

$labels = $pdo->query("SELECT * FROM forum_labels ORDER BY title ASC")->fetchAll();

$userPerms = [];
if (!empty($_SESSION['user_id'])) {
    $stmtP = $pdo->prepare("SELECT p.slug FROM permissions p JOIN permission_role pr ON p.id = pr.permission_id JOIN users u ON pr.role_id = u.role_id WHERE u.id = ?");
    $stmtP->execute([$_SESSION['user_id']]);
    $userPerms = $stmtP->fetchAll(PDO::FETCH_COLUMN);
}
$canPost = in_array('forum_access', $userPerms);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['user_id'])) {
    if (!$canPost) {
        $error = $fLang['no_permission'] ?? 'No permission';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $labelId = !empty($_POST['label_id']) ? (int)$_POST['label_id'] : null;

        if ($title && $content) {
            $tSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . time();
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO forum_threads (board_id, user_id, title, slug, label_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$board['id'], $_SESSION['user_id'], $title, $tSlug, $labelId]);
                $threadId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$threadId, $_SESSION['user_id'], $content]);
                $pdo->commit();
                header("Location: /forum/thread/" . $tSlug);
                exit;
            } catch (Exception $e) { $pdo->rollBack(); $error = 'Error'; }
        }
    }
}

$threads = $pdo->prepare("SELECT t.*, u.username, 
    l.title as label_title, l.css_class as label_color,
    (SELECT COUNT(*) FROM forum_posts p WHERE p.thread_id = t.id) - 1 as replies, 
    (SELECT created_at FROM forum_posts p WHERE p.thread_id = t.id ORDER BY created_at DESC LIMIT 1) as last_activity 
    FROM forum_threads t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN forum_labels l ON t.label_id = l.id
    WHERE t.board_id = ? 
    ORDER BY t.is_sticky DESC, t.sort_order ASC, t.created_at DESC");
    
$threads->execute([$board['id']]);
$threads = $threads->fetchAll();

$uploadDir = __DIR__ . '/uploads/';
$allFiles = is_dir($uploadDir) ? array_diff(scandir($uploadDir), ['.', '..']) : [];
usort($allFiles, function($a, $b) use ($uploadDir) { return filemtime($uploadDir . $b) - filemtime($uploadDir . $a); });
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($board['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="/assets/styles/main.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
    .breadcrumb { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 12px 20px; margin-bottom: 25px; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; color: var(--text-muted); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .breadcrumb a { text-decoration: none; color: var(--primary); font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
    .breadcrumb a:hover { opacity: 0.8; }
    .breadcrumb span { opacity: 0.5; }

    .thread-card { display: flex; align-items: center; padding: 18px 25px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: inherit; margin-bottom: 10px; transition:0.2s; }
    .thread-card:hover { border-color: var(--primary); transform: translateX(3px); }
    .thread-meta { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }
    
    .btn-primary { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
    
    .form-input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-size: 1rem; background: var(--bg-body); color: var(--text-main); margin-bottom: 15px; }

    .badge { display: inline-block; padding: 3px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #fff; margin-right: 6px; vertical-align: middle; line-height:1; }
    .badge-red { background-color: #e53e3e; } .badge-orange { background-color: #dd6b20; }
    .badge-green { background-color: #38a169; } .badge-blue { background-color: #3182ce; }
    .badge-purple { background-color: #805ad5; } .badge-gray { background-color: #718096; }
    .badge-dark { background-color: #2d3748; }

    .label-select-container { margin-bottom:15px; display:flex; gap:10px; flex-wrap:wrap; }
    .label-radio { display:none; }
    .label-opt { cursor:pointer; opacity:0.6; border:2px solid transparent; padding:2px; border-radius:6px; transition:0.2s; }
    .label-radio:checked + .label-opt { opacity:1; border-color:var(--text-main); transform:scale(1.1); }

    /* Editor Specific Styles are now handled by templates/editor.php (mostly) but some container styles can remain if needed by layout */
    .editor-container { background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-top: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
    [data-theme="dark"] .editor-container { background: #2d3748; border-color: #4a5568; }

    .section-header { background: linear-gradient(90deg, var(--primary), #2b6cb0); color: #fff; padding: 10px 20px; font-size: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; }
    .subforum-list { display: flex; flex-direction: column; }
    .subforum-card { display: flex; align-items: center; padding: 15px 25px; text-decoration: none; color: inherit; border-bottom: 1px solid var(--border); transition: background 0.15s; }
    .subforum-card:last-child { border-bottom: none; }
    .subforum-card:hover { background: var(--bg-body); }
    .subforum-icon { font-size: 1.5rem; color: var(--primary); margin-right: 15px; opacity: 0.8; width: 30px; text-align: center; }
    .subforum-container { margin-bottom: 30px; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; background: var(--bg-card); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .subforum-stats { font-size: 0.8rem; color: var(--text-muted); margin-left: auto; text-align: right; }
    
    .badge-count { background: #edf2f7; color: #4a5568; padding: 2px 8px; border-radius: 12px; font-weight: 600; font-size: 0.75rem; }
    [data-theme="dark"] .badge-count { background: #2d3748; color: #cbd5e0; }
    :not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div class="breadcrumb">
        <a href="/forum.php"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars($fLang['title'] ?? 'Forum') ?></a> 
        <span>/</span>
        <span><?= htmlspecialchars($board['title']) ?></span>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; border-bottom: 2px solid var(--primary); padding-bottom: 15px;">
        <div>
            <h1 style="margin: 0; color: var(--primary);"><?= htmlspecialchars($board['title']) ?></h1>
            <div style="color: var(--text-muted); margin-top: 5px;"><?= htmlspecialchars($board['description']) ?></div>
        </div>
        
        <?php if (!empty($_SESSION['user_id']) && $board['is_category'] == 0 && $canPost): ?>
            <button onclick="document.getElementById('newThreadBox').style.display='block'" class="btn-primary">
                <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($fLang['create_thread'] ?? 'New Topic') ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($subBoards)): ?>
        <div class="subforum-container">
            <div class="section-header">
                <span><i class="fa-solid fa-sitemap" style="margin-right:10px;"></i> <?= htmlspecialchars($fLang['subforums'] ?? 'Subforums') ?></span>
            </div>
            <div class="subforum-list">
                <?php foreach ($subBoards as $sb): ?>
                    <a href="/forum/<?= htmlspecialchars($sb['slug']) ?>" class="subforum-card">
                        <div class="subforum-icon"><i class="fa-solid fa-comments"></i></div>
                        <div style="flex:1;">
                            <div style="font-weight:600; font-size:1rem; color:var(--text-main);"><?= htmlspecialchars($sb['title']) ?></div>
                            <div style="font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($sb['description']) ?></div>
                        </div>
                        <div class="subforum-stats">
                            <span class="badge-count"><?= number_format((int)$sb['thread_count']) ?></span> Topics
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($board['is_category'] == 0): ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <?php if ($canPost): ?>
                <div id="newThreadBox" style="display: none; padding: 30px; margin-bottom: 30px; background:var(--bg-card); border: 2px solid var(--primary); border-radius:12px;">
                    <h3 style="margin-top: 0;"><?= htmlspecialchars($fLang['create_thread'] ?? 'New Topic') ?></h3>
                    
                    <form method="POST">
                        <input type="text" name="title" class="form-input" placeholder="<?= htmlspecialchars($fLang['thread_title'] ?? 'Title') ?>" required>
                        
                        <?php if(!empty($labels)): ?>
                        <div class="label-select-container">
                            <span style="font-size:0.9rem; font-weight:bold; align-self:center; margin-right:5px;">Label:</span>
                            <label class="color-option">
                                <input type="radio" name="label_id" value="" checked>
                                <span style="background:#eee; color:#555; padding:3px 8px; border-radius:4px; font-size:0.75rem; border:1px solid #ccc;">Kein</span>
                            </label>
                            <?php foreach($labels as $lbl): ?>
                                <label class="color-option">
                                    <input type="radio" name="label_id" value="<?= $lbl['id'] ?>">
                                    <span class="badge <?= htmlspecialchars($lbl['css_class']) ?>"><?= htmlspecialchars($lbl['title']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php 
                            $editorContent = '';
                            $editorUploadUrl = '/upload-handler.php';
                            include 'templates/editor.php'; 
                        ?>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-paper-plane"></i> <?= htmlspecialchars($fLang['save_board'] ?? 'Post') ?>
                            </button>
                            <button type="button" onclick="document.getElementById('newThreadBox').style.display='none'" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-weight:600;">
                                <?= htmlspecialchars($peLang['btn_close'] ?? 'Cancel') ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="thread-list">
            <?php if (empty($threads)): ?>
                <div style="padding: 40px; text-align: center; color: var(--text-muted); background: var(--bg-card); border-radius: 8px; border:1px solid var(--border);">
                    <?= htmlspecialchars($fLang['no_threads'] ?? 'No topics yet.') ?>
                </div>
            <?php else: ?>
                <?php foreach ($threads as $t): ?>
                    <a href="/forum/thread/<?= htmlspecialchars($t['slug']) ?>" class="thread-card">
                        <div style="width: 40px; font-size: 1.5rem; color: <?= $t['is_sticky'] ? '#e53e3e' : 'var(--text-muted)' ?>;">
                            <i class="fa-solid <?= $t['is_sticky'] ? 'fa-thumbtack' : ($t['is_locked'] ? 'fa-lock' : 'fa-comments') ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 1.1rem; color: var(--primary);">
                                <?php if(!empty($t['label_title'])): ?>
                                    <span class="badge <?= htmlspecialchars($t['label_color']) ?>">
                                        <?= htmlspecialchars($t['label_title']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if($t['is_locked']): ?>
                                    <i class="fa-solid fa-lock" title="<?= htmlspecialchars($fLang['locked'] ?? 'Locked') ?>" style="color:var(--text-muted); font-size:0.9rem; margin-right:5px;"></i>
                                <?php endif; ?>

                                <?= htmlspecialchars($t['title']) ?>
                            </div>
                            <div class="thread-meta">
                                <?= htmlspecialchars($fLang['posted_by'] ?? 'by') ?> <strong><?= htmlspecialchars($t['username']) ?></strong> &bull; <?= date('d.m.Y H:i', strtotime($t['created_at'])) ?>
                            </div>
                        </div>
                        <div style="font-weight: bold; color: var(--text-main);"><?= $t['replies'] ?> <i class="fa-regular fa-comment"></i></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
<script>
    document.addEventListener('DOMContentLoaded', (event) => { if (window.hljs) hljs.highlightAll(); });
    const toggleBtn = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    if (toggleBtn) { toggleBtn.addEventListener('click', () => { const current = html.getAttribute('data-theme'); const next = current === 'dark' ? 'light' : 'dark'; html.setAttribute('data-theme', next); localStorage.setItem('theme', next); }); }
    function toggleLang() { document.getElementById('langMenu').classList.toggle('show'); }
    window.addEventListener('click', function(e) { if (!document.getElementById('langDropdown').contains(e.target)) document.getElementById('langMenu').classList.remove('show'); });
</script>
</body>
</html>