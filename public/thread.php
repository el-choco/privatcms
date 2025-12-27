<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/Parsedown.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$parsedown = new Parsedown();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$fLang = $iniLang['forum'] ?? [];
$peLang = $iniLang['editor'] ?? []; 

$settings = []; try { foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; } } catch (Exception $e) {}
try { $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn(); $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn(); } catch (Exception $e) { $totalViews=0; $todayViews=0; }
$t = ['footer_total' => $iniLang['frontend']['footer_stats_total'] ?? 'Total','footer_today' => $iniLang['frontend']['footer_stats_today'] ?? 'Today','admin' => $iniLang['frontend']['login_link'] ?? 'Admin'];

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT t.*, b.slug as board_slug, b.title as board_title FROM forum_threads t JOIN forum_boards b ON t.board_id = b.id WHERE t.slug = ?");
$stmt->execute([$slug]);
$thread = $stmt->fetch();
if (!$thread) { http_response_code(404); echo "Not found"; exit; }

$userPerms = [];
if (!empty($_SESSION['user_id'])) {
    $stmtP = $pdo->prepare("SELECT p.slug FROM permissions p JOIN permission_role pr ON p.id = pr.permission_id JOIN users u ON pr.role_id = u.role_id WHERE u.id = ?");
    $stmtP->execute([$_SESSION['user_id']]);
    $userPerms = $stmtP->fetchAll(PDO::FETCH_COLUMN);
}
$canModerate = in_array('forum_moderate', $userPerms);
$canPost = in_array('forum_access', $userPerms);

if ($canModerate && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_lock'])) {
        $newStatus = $thread['is_locked'] ? 0 : 1;
        $pdo->prepare("UPDATE forum_threads SET is_locked = ? WHERE id = ?")->execute([$newStatus, $thread['id']]);
        header("Location: /forum/thread/" . $thread['slug']);
        exit;
    }

    if (isset($_POST['toggle_sticky'])) {
        $newSticky = $thread['is_sticky'] ? 0 : 1;
        $pdo->prepare("UPDATE forum_threads SET is_sticky = ? WHERE id = ?")->execute([$newSticky, $thread['id']]);
        header("Location: /forum/thread/" . $thread['slug']);
        exit;
    }

    if (isset($_POST['delete_thread_id'])) {
        $delTId = (int)$_POST['delete_thread_id'];
        $pdo->prepare("DELETE FROM forum_posts WHERE thread_id = ?")->execute([$delTId]);
        $pdo->prepare("DELETE FROM forum_threads WHERE id = ?")->execute([$delTId]);
        header("Location: /forum/" . $thread['board_slug']);
        exit;
    }

    if (isset($_POST['edit_thread_title'])) {
        $newTitle = trim($_POST['edit_thread_title']);
        $pdo->prepare("UPDATE forum_threads SET title = ? WHERE id = ?")->execute([$newTitle, $thread['id']]);
        header("Location: /forum/thread/" . $thread['slug']);
        exit;
    }

    if (isset($_POST['delete_post_id'])) {
        $delId = (int)$_POST['delete_post_id'];
        $pdo->prepare("DELETE FROM forum_posts WHERE id = ?")->execute([$delId]);
        header("Location: /forum/thread/" . $thread['slug']);
        exit;
    }
    if (isset($_POST['edit_save_id'], $_POST['edit_content'])) {
        $editId = (int)$_POST['edit_save_id'];
        $newContent = trim($_POST['edit_content']);
        $pdo->prepare("UPDATE forum_posts SET content = ?, updated_at = NOW() WHERE id = ?")->execute([$newContent, $editId]);
        header("Location: /forum/thread/" . $thread['slug'] . "#post-" . $editId);
        exit;
    }
}

$pdo->prepare("UPDATE forum_threads SET views = views + 1 WHERE id = ?")->execute([$thread['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['user_id']) && isset($_POST['content'])) {
    if (!$canPost) {
        exit;
    }
    $content = trim($_POST['content'] ?? '');
    if ($content && !$thread['is_locked']) {
        $stmt = $pdo->prepare("INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$thread['id'], $_SESSION['user_id'], $content]);
        $pdo->prepare("UPDATE forum_threads SET updated_at = NOW() WHERE id = ?")->execute([$thread['id']]);
        header("Location: /forum/thread/" . $thread['slug']);
        exit;
    }
}

$posts = $pdo->prepare("SELECT p.*, u.username, r.label as role_label, u.avatar FROM forum_posts p JOIN users u ON p.user_id = u.id LEFT JOIN roles r ON u.role_id = r.id WHERE p.thread_id = ? ORDER BY p.created_at ASC");
$posts->execute([$thread['id']]);
$posts = $posts->fetchAll();

$uploadDir = __DIR__ . '/uploads/';
$allFiles = is_dir($uploadDir) ? array_diff(scandir($uploadDir), ['.', '..']) : [];
usort($allFiles, function($a, $b) use ($uploadDir) { return filemtime($uploadDir . $b) - filemtime($uploadDir . $a); });

$editModeId = isset($_GET['edit_mode']) && $canModerate ? (int)$_GET['edit_mode'] : 0;

function getAvatar($user) {
    $name = $user['username'] ?? 'U';
    
    if (!empty($user['avatar'])) {
        return '<img src="/uploads/'.htmlspecialchars($user['avatar']).'" alt="'.$name.'" class="thread-user-avatar">';
    }

    $colors = ['#f56565', '#ed8936', '#ecc94b', '#48bb78', '#38b2ac', '#4299e1', '#667eea', '#9f7aea', '#ed64a6', '#a0aec0'];
    $val = 0;
    for ($i = 0; $i < strlen($name); $i++) { $val += ord($name[$i]); }
    $color = $colors[$val % count($colors)];
    $initial = strtoupper(substr($name, 0, 1));
    return '<div class="thread-user-placeholder" style="background:'.$color.';">'.$initial.'</div>';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($thread['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/styles/main.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        .breadcrumb { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 12px 20px; margin-bottom: 25px; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; color: var(--text-muted); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .breadcrumb a { text-decoration: none; color: var(--primary); font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .breadcrumb a:hover { opacity: 0.8; }
        .breadcrumb span { opacity: 0.5; }

        .btn-reply { background: var(--primary); color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; margin-top: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-reply:hover { opacity: 0.9; transform: translateY(-1px); }

        .form-input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-size: 1rem; background: var(--bg-body); color: var(--text-main); }

        .editor-container { background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        [data-theme="dark"] .editor-container { background: #2d3748; border-color: #4a5568; }

        .post-card { display: flex; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: var(--bg-card); margin-bottom: 20px; }
        .post-sidebar { width: 160px; background: var(--bg-body); padding: 20px; text-align: center; border-right: 1px solid var(--border); flex-shrink: 0; }
        .post-role { display: inline-block; font-size: 0.75rem; background: #e2e8f0; padding: 2px 8px; border-radius: 10px; margin-top: 5px; color: #555; }
        .post-main { flex: 1; padding: 20px; }
        .post-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 15px; color: var(--text-muted); font-size: 0.85rem; }
        
        .admin-actions { display: flex; gap: 8px; }
        .btn-act { border: none; background: transparent; cursor: pointer; font-size: 0.9rem; padding: 4px 8px; border-radius: 4px; transition: 0.2s; }
        .btn-act.edit { color: var(--primary); }
        .btn-act.edit:hover { background: #ebf8ff; }
        .btn-act.del { color: #e53e3e; }
        .btn-act.del:hover { background: #fff5f5; }

        .thread-user-avatar { width:64px; height:64px; object-fit:cover; border-radius:50%; margin:0 auto 12px auto; box-shadow:0 3px 6px rgba(0,0,0,0.1); border:2px solid var(--bg-card); display:block; }
        .thread-user-placeholder { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:26px; font-weight:700; margin:0 auto 12px auto; box-shadow:0 3px 6px rgba(0,0,0,0.1); border:2px solid var(--bg-card); color:#fff; }

        [data-theme="dark"] .post-role { background: #4a5568; color: #e2e8f0; }
        [data-theme="dark"] .btn-act.edit:hover { background: #2d3748; }
        [data-theme="dark"] .btn-act.del:hover { background: #742a2a; }
        :not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}

        .post-content table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        .post-content th, .post-content td { border: 1px solid var(--border); padding: 8px; }
        .post-content th { background-color: var(--bg-body); font-weight: bold; text-align: left; }
        .post-content tr:nth-child(even) { background-color: rgba(0,0,0,0.02); }
        .post-content :not(pre) > code { background-color: #23241f; color: #f8f8f2; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; border: 1px solid #3e3d32; }
        .post-content blockquote { border-left: 4px solid #1877f2; background: rgba(0,0,0,0.02); margin: 1em 0; padding: 10px 20px; color: var(--text-muted); font-style: italic; border-radius: 0 4px 4px 0; }

        [data-theme="dark"] .post-content th { background-color: #4a5568; border-color: #4a5568; color: #fff; }
        [data-theme="dark"] .post-content td { border-color: #4a5568; color: #e2e8f0; }
        [data-theme="dark"] .post-content tr:nth-child(even) { background-color: #2d3748; }
        [data-theme="dark"] .post-content :not(pre) > code { background-color: #4a5568; border-color: #4a5568; }
        [data-theme="dark"] .post-content blockquote { background: #1a202c; border-left-color: #4299e1; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div class="breadcrumb">
        <a href="/forum.php"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars($fLang['title'] ?? 'Forum') ?></a> 
        <span>/</span>
        <a href="/forum/<?= htmlspecialchars($thread['board_slug']) ?>"><?= htmlspecialchars($thread['board_title']) ?></a>
    </div>

    <div style="margin-bottom: 30px; border-bottom: 2px solid var(--primary); padding-bottom: 15px;">
        <?php if ($canModerate && isset($_GET['edit_thread'])): ?>
            <form method="POST" style="display:flex; align-items:center; gap:10px;">
                <input type="text" name="edit_thread_title" class="form-input" value="<?= htmlspecialchars($thread['title']) ?>" style="font-size:1.5rem; font-weight:bold; margin:0;">
                <button type="submit" class="btn-reply" style="margin:0; padding:8px 15px;"><i class="fa-solid fa-check"></i></button>
                <a href="?slug=<?= $thread['slug'] ?>" class="ed-btn" style="text-decoration:none; padding:8px 15px;"><i class="fa-solid fa-xmark"></i></a>
            </form>
        <?php else: ?>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1 style="margin: 0; color: var(--primary);">
                    <?php if($thread['is_locked']): ?><i class="fa-solid fa-lock" title="<?= htmlspecialchars($fLang['locked'] ?? 'Locked') ?>"></i><?php endif; ?>
                    <?php if(!empty($thread['is_sticky'])): ?><i class="fa-solid fa-thumbtack" title="<?= htmlspecialchars($fLang['sticky'] ?? 'Sticky') ?>" style="margin-right:5px; font-size:0.8em; opacity:0.8;"></i><?php endif; ?>
                    <?= htmlspecialchars($thread['title']) ?>
                </h1>
                <?php if ($canModerate): ?>
                    <div class="admin-actions" style="font-size:1.2rem; display:flex; gap:10px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="toggle_sticky" value="1">
                            <button type="submit" class="btn-act" title="<?= htmlspecialchars(!empty($thread['is_sticky']) ? ($fLang['unstick'] ?? 'Unstick') : ($fLang['stick'] ?? 'Stick')) ?>">
                                <i class="fa-solid fa-thumbtack" style="<?= !empty($thread['is_sticky']) ? 'color:var(--primary)' : '' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="toggle_lock" value="1">
                            <button type="submit" class="btn-act" title="<?= htmlspecialchars($thread['is_locked'] ? ($fLang['unlock'] ?? 'Unlock') : ($fLang['lock'] ?? 'Lock')) ?>">
                                <i class="fa-solid fa-<?= $thread['is_locked'] ? 'lock-open' : 'lock' ?>"></i>
                            </button>
                        </form>
                        <a href="?slug=<?= $thread['slug'] ?>&edit_thread=1" class="btn-act edit" title="<?= htmlspecialchars($peLang['btn_edit'] ?? 'Edit') ?>">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <form method="POST" onsubmit="return confirm('<?= htmlspecialchars($fLang['delete_confirm'] ?? 'Delete?') ?>')" style="display:inline;">
                            <input type="hidden" name="delete_thread_id" value="<?= $thread['id'] ?>">
                            <button type="submit" class="btn-act del" title="<?= htmlspecialchars($fLang['btn_delete'] ?? 'Delete') ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="thread-list">
        <?php foreach ($posts as $index => $p): ?>
            <div class="post-card" id="post-<?= $p['id'] ?>">
                <div class="post-sidebar">
                    <?= getAvatar($p) ?>
                    <div style="font-weight: bold; margin-bottom: 5px;"><?= htmlspecialchars($p['username']) ?></div>
                    <span class="post-role"><?= htmlspecialchars($p['role_label'] ?? $p['role']) ?></span>
                </div>
                <div class="post-main">
                    <div class="post-header">
                        <div>
                            <span><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></span>
                            <span style="margin-left:8px;">#<?= $index + 1 ?></span>
                        </div>
                        <?php if ($canModerate): ?>
                            <div class="admin-actions">
                                <a href="?edit_mode=<?= $p['id'] ?>#post-<?= $p['id'] ?>" class="btn-act edit" title="<?= htmlspecialchars($peLang['btn_edit'] ?? 'Edit') ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="POST" onsubmit="return confirm('<?= htmlspecialchars($fLang['delete_confirm'] ?? 'Delete?') ?>')" style="display:inline;">
                                    <input type="hidden" name="delete_post_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-act del" title="<?= htmlspecialchars($fLang['btn_delete'] ?? 'Delete') ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($editModeId === (int)$p['id']): ?>
                        <form method="POST">
                            <input type="hidden" name="edit_save_id" value="<?= $p['id'] ?>">
                            
                            <?php 
                                $editorContent = $p['content'];
                                $editorName = 'edit_content'; // Important: Different name for POST
                                $editorUploadUrl = '/upload-handler.php';
                                include 'templates/editor.php';
                            ?>

                            <div style="text-align:right; margin-top:10px;">
                                <a href="?slug=<?= $thread['slug'] ?>" class="ed-btn" style="text-decoration:none; padding:8px 15px; margin-right:5px; background:#f0f2f5;"><?= htmlspecialchars($peLang['btn_close'] ?? 'Cancel') ?></a>
                                <button type="submit" class="btn-reply" style="margin:0; font-size:0.9rem; padding:8px 15px;">
                                    <i class="fa-solid fa-check"></i> <?= htmlspecialchars($peLang['btn_save'] ?? 'Save') ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="post-content" style="line-height: 1.6;">
                            <?= $parsedown->text($p['content']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
        <?php if (!$thread['is_locked'] && $canPost && $editModeId === 0): ?>
            <h3 style="margin-top: 40px; color: var(--text-main);"><?= htmlspecialchars($fLang['reply'] ?? 'Reply') ?></h3>
            <form method="POST">
                <?php 
                    $editorContent = '';
                    $editorName = 'content'; // Default name for new post
                    $editorUploadUrl = '/upload-handler.php';
                    include 'templates/editor.php';
                ?>
                
                <button type="submit" class="btn-reply">
                    <i class="fa-solid fa-paper-plane"></i> <?= htmlspecialchars($fLang['reply'] ?? 'Post Reply') ?>
                </button>
            </form>
        <?php elseif ($thread['is_locked']): ?>
            <div style="margin-top: 30px; background: #fed7d7; color: #822727; padding: 15px; border-radius: 8px; text-align: center;">
                <i class="fa-solid fa-lock"></i> <?= htmlspecialchars($fLang['locked'] ?? 'This topic is locked.') ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div style="margin-top: 40px; text-align: center; padding: 30px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px;">
            <?= htmlspecialchars($fLang['login_required'] ?? 'Please login to reply.') ?> 
            <a href="/login.php" style="margin-left: 10px; color:var(--primary); font-weight:bold;">Login</a>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        if (window.hljs) {
            hljs.highlightAll();
        }
    });

    const toggleBtn = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    if (toggleBtn) { toggleBtn.addEventListener('click', () => { const current = html.getAttribute('data-theme'); const next = current === 'dark' ? 'light' : 'dark'; html.setAttribute('data-theme', next); localStorage.setItem('theme', next); }); }
    function toggleLang() { document.getElementById('langMenu').classList.toggle('show'); }
    window.addEventListener('click', function(e) { 
        if (!document.getElementById('langDropdown').contains(e.target)) document.getElementById('langMenu').classList.remove('show'); 
    });
</script>
</body>
</html>