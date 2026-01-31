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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'viewer') {
        $error = 'Viewers cannot create topics.';
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

    .editor-container { background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-top: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
    .editor-toolbar { background: #f8fafc; border-bottom: 1px solid #cbd5e0; padding: 10px; font-family: sans-serif; }
    .editor-row { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; }
    .editor-row:last-child { margin-bottom: 0; }
    .editor-label { font-weight: 800; color: #1877f2; width: 90px; font-size: 11px; text-transform: uppercase; flex-shrink: 0; }
    .editor-label.green { color: #42b72a; }
    .ed-btn { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 5px 10px; font-size: 13px; cursor: pointer; color: #444; display: inline-flex; align-items: center; justify-content: center; min-width: 30px; transition: all 0.2s; font-weight: 500; }
    .ed-btn:hover { background: #f0f2f5; border-color: #bbb; color: #000; }
    .ed-sep { width: 1px; height: 20px; background: #eee; margin: 0 5px; }
    
    .ico-img::before { content: "🖼️"; font-size:12px; }
    .ico-link::before { content: "🔗"; font-size:12px; }
    
    .editor-split { display: flex; min-height: 200px; }
    .editor-area { flex: 1; border-right: 1px solid #ddd; position: relative; }
    .editor-area textarea { width: 100%; height: 100%; border: none; padding: 15px; resize: vertical; outline: none; font-family: monospace; font-size: 14px; box-sizing: border-box; display: block; min-height: 200px; }
    
    .preview-area { flex: 1; padding: 15px; background: #fff; overflow-y: auto; max-height: 500px; }
    .preview-area pre { background: #23241f; color: #f8f8f2; padding: 1em; border-radius: 6px; overflow-x: auto; }
    .preview-area code { font-family: 'Fira Code', Consolas, monospace; font-size: 14px; }
    
    #mediaModal, #iconModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); align-items: center; justify-content: center; }
    .modal-content { background: white; padding: 20px; border-radius: 12px; width: 80%; max-width: 900px; max-height: 80vh; overflow-y: auto; display: flex; flex-direction: column; }
    
    .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px; margin-top: 15px; }
    .media-item { cursor: pointer; border: 2px solid transparent; border-radius: 6px; overflow: hidden; transition: 0.2s; text-align: center; background: #f8fafc; padding: 5px; }
    .media-item:hover { border-color: #3182ce; transform: scale(1.05); }
    .media-item img { width: 100%; height: 100px; object-fit: cover; border-radius: 4px; }
    
    .file-icon { font-size: 3rem; line-height: 100px; height: 100px; }
    
    .smiley-popover { display: none; position: absolute; background: #fff; border: 1px solid #ccc; border-radius: 8px; padding: 10px; width: 300px; height: 250px; overflow-y: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 1000; }
    .smiley-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 5px; }
    .smiley-item { cursor: pointer; font-size: 20px; text-align: center; padding: 5px; border-radius: 4px; }
    .smiley-item:hover { background: #f0f2f5; }
    
    .icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 10px; max-height: 60vh; overflow-y: auto; }
    .icon-item { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px; border: 1px solid #eee; border-radius: 6px; cursor: pointer; transition: all 0.2s; }
    .icon-item:hover { background: #e7f3ff; border-color: #1877f2; color: #1877f2; }
    .icon-item i { font-size: 24px; margin-bottom: 5px; }
    .icon-name { font-size: 10px; color: #666; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; width: 100%; }
    
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
    [data-theme="dark"] .editor-container { background: #2d3748; border-color: #4a5568; }
    [data-theme="dark"] .editor-toolbar { background: #1a202c; border-bottom-color: #4a5568; }
    [data-theme="dark"] .ed-btn { background: #2d3748; border-color: #4a5568; color: #1877f2; }
    [data-theme="dark"] .ed-btn:hover { background: #4a5568; color: #fff; }
    [data-theme="dark"] .ed-sep { background: #4a5568; }
    [data-theme="dark"] .editor-area textarea { background: #2d3748; color: #e2e8f0; }
    [data-theme="dark"] .editor-area { border-right-color: #4a5568; }
    [data-theme="dark"] .preview-area { background: #2d3748; color: #e2e8f0; }
    [data-theme="dark"] .smiley-popover { background: #1a202c; border-color: #4a5568; }
    [data-theme="dark"] .smiley-item:hover { background: #2d3748; }
    [data-theme="dark"] .modal-content { background: #2d3748; color: #fff; }
    [data-theme="dark"] .media-item { background: #1a202c; }
    [data-theme="dark"] .media-item .file-icon { color: #e2e8f0; }
    [data-theme="dark"] .icon-item { border-color: #4a5568; }
    [data-theme="dark"] .icon-item i { color: #e2e8f0; }
    [data-theme="dark"] .icon-name { color: #a0aec0; }
    [data-theme="dark"] .icon-item:hover { background: #1a202c; }
    [data-theme="dark"] .form-input { background: #2d3748; color: #fff; border-color: #4a5568; }

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
        
        <?php if (!empty($_SESSION['user_id']) && $board['is_category'] == 0 && ($_SESSION['role'] ?? '') !== 'viewer'): ?>
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
            <?php if (($_SESSION['role'] ?? '') !== 'viewer'): ?>
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

                        <div class="editor-container">
                            <div class="editor-toolbar">
                                <div class="editor-row">
                                    <span class="editor-label">MARKDOWN:</span>
                                    <button type="button" class="ed-btn" onclick="insertTag('**', '**')" title="<?= htmlspecialchars($peLang['tooltip_bold'] ?? 'Bold') ?>"><b>B</b></button>
                                    <button type="button" class="ed-btn" onclick="insertTag('*', '*')" title="<?= htmlspecialchars($peLang['tooltip_italic'] ?? 'Italic') ?>"><i>I</i></button>
                                    <button type="button" class="ed-btn" onclick="insertTag('~~', '~~')" title="<?= htmlspecialchars($peLang['tooltip_strike'] ?? 'Strike') ?>"><s>S</s></button>
                                    <div class="ed-sep"></div>
                                    <button type="button" class="ed-btn" onclick="insertTag('# ', '')">H1</button>
                                    <button type="button" class="ed-btn" onclick="insertTag('## ', '')">H2</button>
                                    <button type="button" class="ed-btn" onclick="insertTag('### ', '')">H3</button>
                                    <div class="ed-sep"></div>
                                    <button type="button" class="ed-btn ico-link" onclick="insertLink()" title="<?= htmlspecialchars($peLang['tooltip_link'] ?? 'Link') ?>"></button>
                                    <button type="button" class="ed-btn ico-img" onclick="openMediaModal()" title="<?= htmlspecialchars($peLang['tooltip_image'] ?? 'Image') ?>"></button>
                                    <div class="ed-sep"></div>
                                    <button type="button" class="ed-btn" onclick="insertTag('`', '`')" style="font-family:monospace;">`code`</button>
                                    <button type="button" class="ed-btn" onclick="insertTag('```\n', '\n```')" title="<?= htmlspecialchars($peLang['tooltip_code_block'] ?? 'Code') ?>">...</button>
                                    
                                    <div class="ed-sep"></div>
                                    <div style="position:relative;">
                                        <button type="button" class="ed-btn" onclick="toggleSmileyPicker(this)" title="<?= htmlspecialchars($peLang['tooltip_emojis'] ?? 'Emojis') ?>">😀</button>
                                        <div id="smileyPopover" class="smiley-popover">
                                            <div id="smileyGrid" class="smiley-grid"></div>
                                        </div>
                                    </div>
                                    <button type="button" class="ed-btn" onclick="openIconModal()" title="<?= htmlspecialchars($peLang['modal_icons'] ?? 'Icons') ?>"><?= htmlspecialchars($peLang['btn_fa_icons'] ?? 'FA Icons') ?></button>

                                    <div class="ed-sep"></div>
                                    <button type="button" class="ed-btn" onclick="insertTag('* ', '')"><?= htmlspecialchars($peLang['btn_list_bullet'] ?? '• List') ?></button>
                                    <button type="button" class="ed-btn" onclick="insertTag('1. ', '')"><?= htmlspecialchars($peLang['btn_list_number'] ?? '1. List') ?></button>
                                    <button type="button" class="ed-btn" onclick="insertTag('> ', '')">💬</button>
                                    <button type="button" class="ed-btn" onclick="insertTag('\n---\n', '')">---</button>
                                </div>
                                <div class="editor-row">
                                    <span class="editor-label green">HTML:</span>
                                    <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:center\'>', '</div>')"><?= htmlspecialchars($peLang['btn_center'] ?? 'Center') ?></button>
                                    <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:right\'>', '</div>')"><?= htmlspecialchars($peLang['btn_right'] ?? 'Right') ?></button>
                                    <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:left\'>', '</div>')"><?= htmlspecialchars($peLang['btn_left'] ?? 'Left') ?></button>
                                    <div class="ed-sep"></div>
                                    
                                    <button type="button" class="ed-btn" onclick="document.getElementById('html-color-picker').click()">
                                        <?= htmlspecialchars($peLang['btn_color'] ?? 'Color') ?>
                                    </button>
                                    <input type="color" id="html-color-picker" style="display:none">

                                    <button type="button" class="ed-btn" onclick="insertTag('<mark>', '</mark>')"><?= htmlspecialchars($peLang['btn_mark'] ?? 'Mark') ?></button>
                                    <div class="ed-sep"></div>
                                    <button type="button" class="ed-btn" onclick="insertTag('<small>', '</small>')">Small</button>
                                    <button type="button" class="ed-btn" onclick="insertTag('<big>', '</big>')">Large</button>
                                    <div class="ed-sep"></div>
                                    <button type="button" class="ed-btn" onclick="insertTag('<u>', '</u>')"><u>U</u></button>
                                    <button type="button" class="ed-btn" onclick="insertTag('<sup>', '</sup>')">x²</button>
                                    <button type="button" class="ed-btn" onclick="insertTag('<sub>', '</sub>')">H₂O</button>
                                    <div class="ed-sep"></div>
                                    <button type="button" class="ed-btn" onclick="insertTag('<details><summary>Spoiler</summary>', '</details>')">👁 Spoiler</button>
                                    <button type="button" class="ed-btn" onclick="insertTag('<br>', '')" style="background:#e7f3ff; border-color:#1877f2; color:#1877f2; font-weight:bold;">&lt;br&gt;</button>
                                </div>
                            </div>
                            <div class="editor-split">
                                <div class="editor-area">
                                    <textarea name="content" id="markdown-input" required placeholder="..." style="min-height:250px;"></textarea>
                                </div>
                                <div id="preview-box" class="preview-area" style="min-height:250px;"></div>
                            </div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
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

<div id="mediaModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3><?= htmlspecialchars($peLang['modal_media'] ?? 'Media') ?></h3>
            <div style="display:flex; gap:10px;">
                <input type="file" id="uploadInput" style="display:none" onchange="uploadFile(this)">
                <button class="ed-btn" onclick="document.getElementById('uploadInput').click()"><?= htmlspecialchars($peLang['btn_upload'] ?? 'Upload') ?></button>
                <button class="ed-btn" onclick="document.getElementById('mediaModal').style.display='none'"><?= htmlspecialchars($peLang['btn_close'] ?? 'Close') ?></button>
            </div>
        </div>
        <div id="uploadStatus" style="font-size:12px; margin-bottom:10px; display:none;"></div>
        <div class="media-grid" id="mediaGrid">
            <?php foreach($allFiles as $f): ?>
                <div class="media-item" onclick="selectImage('<?= htmlspecialchars($f) ?>')">
                    <?php if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)): ?>
                        <img src="/uploads/<?= htmlspecialchars($f) ?>">
                    <?php else: ?>
                        <div class="file-icon">📄</div>
                        <div style="font-size:10px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($f) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div id="iconModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3><?= htmlspecialchars($peLang['modal_icons'] ?? 'Icons') ?></h3>
            <button class="ed-btn" onclick="document.getElementById('iconModal').style.display='none'"><?= htmlspecialchars($peLang['btn_close'] ?? 'Close') ?></button>
        </div>
        <input type="text" id="iconSearch" class="form-input" placeholder="Search..." onkeyup="filterIcons()">
        <div id="iconGrid" class="icon-grid"></div>
    </div>
</div>

<?php 
try { $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn(); $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn(); } catch (Exception $e) { $totalViews=0; $todayViews=0; }
$t['footer_total'] = $iniLang['frontend']['footer_stats_total'] ?? 'Total Visits';
$t['footer_today'] = $iniLang['frontend']['footer_stats_today'] ?? 'Today';
include 'footer.php'; 
?>

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
    // --------------------------------------------------------

    const input = document.getElementById('markdown-input');
    const preview = document.getElementById('preview-box');
    
    if (input) {
        function updatePreview() { 
            if(typeof marked !== 'undefined') {
                preview.innerHTML = marked.parse(input.value);
                if(window.hljs) hljs.highlightAll();
            }
        }
        input.addEventListener('input', updatePreview);

        document.getElementById('html-color-picker').addEventListener('change', function(e) {
            const color = e.target.value;
            insertTag('<span style="color:' + color + '">', '</span>');
        });

        function insertTag(open, close = '') {
            const scrollTop = input.scrollTop; 
            
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value.substring(start, end);

            const matchStart = text.match(/^\s*/);
            const prefixWS = matchStart ? matchStart[0] : "";

            const matchEnd = text.match(/\s*$/);
            const suffixWS = matchEnd ? matchEnd[0] : "";

            const coreText = text.trim();

            let replacement = "";

            if (coreText.length === 0) {
                replacement = prefixWS + open + close + suffixWS;
            } else {
                replacement = prefixWS + open + coreText + close + suffixWS;
            }

            input.value = input.value.substring(0, start) + replacement + input.value.substring(end);

            if (coreText.length === 0) {
                const newPos = start + prefixWS.length + open.length;
                input.focus();
                input.setSelectionRange(newPos, newPos);
            } else {
                const newPos = start + replacement.length;
                input.focus();
                input.setSelectionRange(newPos, newPos);
            }
            
            input.scrollTop = scrollTop; 
            updatePreview();
        }

        function insertLink() {
            if (!input) return;
            const scrollTop = input.scrollTop;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            let text = input.value.substring(start, end);

            let url = prompt("<?= htmlspecialchars($peLang['prompt_url'] ?? 'Enter URL:') ?>", "https://");
            if (url === null) return;

            let label = text.length > 0 ? text : "<?= htmlspecialchars($peLang['default_link_text'] ?? 'Link Text') ?>";
            const replacement = `[${label}](${url})`;

            input.value = input.value.substring(0, start) + replacement + input.value.substring(end);
            
            const newPos = start + replacement.length;
            input.focus();
            input.setSelectionRange(newPos, newPos);
            input.scrollTop = scrollTop;
            updatePreview();
        }
    }

    function openMediaModal() { document.getElementById('mediaModal').style.display='flex'; }
    function selectImage(f) { 
        if(input) { 
            insertTag(`\n![Image](/uploads/${f})\n`); 
            document.getElementById('mediaModal').style.display='none'; 
        }
    }
    
    function uploadFile(el) {
        if(el.files.length===0) return;
        const fd = new FormData(); fd.append('file', el.files[0]);
        const st = document.getElementById('uploadStatus'); st.style.display='block'; st.innerText='Uploading...';
        
        fetch('/upload-handler.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(d=>{
            if(d.status==='ok') {
                st.innerText='Uploaded!';
                const grid = document.getElementById('mediaGrid');
                const div = document.createElement('div'); div.className='media-item';
                div.onclick=()=>selectImage(d.filename);
                if(d.filename.match(/\.(jpg|jpeg|png|gif)$/i)) div.innerHTML=`<img src="${d.url}">`;
                else div.innerHTML=`<div class="file-icon">📄</div><div style="font-size:10px">${d.filename}</div>`;
                grid.insertBefore(div, grid.firstChild);
                setTimeout(()=>{st.style.display='none'},2000);
            } else { st.innerText='Error: '+d.error; }
        });
        el.value='';
    }

    const smileys = ["😀","😃","😄","😁","😆","😅","😂","🤣","😊","😇","🙂","🙃","😉","😌","😍","🥰","😘","😗","😙","😚","😋","😛","😝","😜","🤪","🤨","🧐","🤓","😎","🤩","🥳","😏","😒","😞","😔","😟","😕","🙁","☹️","😣","😖","😫","😩","🥺","😢","😭","😤","😠","😡","🤬","🤯","😳","🥵","🥶","😱","😨","😰","😥","🤗","🤔","🤭","🤫","🤥","😶","😐","😑","😬","🙄","😯","😦","😧","😮","😲","🥱","😴","🤤","😪","😵","🤐","🥴","🤢","🤮","🤧","😷","🤒","🤕","🤑","🤠","😈","👿","👹","👺","🤡","💩","👻","💀","👽","👾","🤖","🎃","😺","😸","😹","😻","😼","😼","😽","🙀","😿","𘘾","🤲","👐","🙌","👏","🤝","👍","👎","👊","✊","🤛","🤜","🤞","✌️","🤟","🤘","👌","🤏","👈","👉","👆","👇","☝️","✋","🤚","🖐","🖖","👋","🤙","💪","🧠","🦷","🦴","👀","👁","👄","💋","🦶","🦵","👃","👂","🦻","👣","🔥","💥","✨","🌟","💫","❤️","🧡","💛","💚","💙","💜","🖤","🤍","🤎","💔","❣️","💕","💞","💓","💗","💖","💘","💝","💟"];
    const smGrid = document.getElementById('smileyGrid');
    
    if (smGrid) {
        smileys.forEach(s=>{
            const sp = document.createElement('span'); sp.className='smiley-item'; sp.innerText=s;
            sp.onclick=()=>{ 
                if(input) { insertTag(s); document.getElementById('smileyPopover').style.display='none'; }
            };
            smGrid.appendChild(sp);
        });
    }

    function toggleSmileyPicker(btn) {
        const p = document.getElementById('smileyPopover');
        if(p.style.display==='block') p.style.display='none';
        else { p.style.display='block'; p.style.top=(btn.offsetTop+btn.offsetHeight+5)+'px'; p.style.left=btn.offsetLeft+'px'; }
    }

    const icons = ["fa-solid fa-user", "fa-regular fa-user", "fa-solid fa-users", "fa-solid fa-user-plus", "fa-solid fa-house", "fa-solid fa-magnifying-glass", "fa-solid fa-bars", "fa-solid fa-envelope", "fa-regular fa-envelope", "fa-solid fa-heart", "fa-regular fa-heart", "fa-solid fa-star", "fa-regular fa-star", "fa-solid fa-check", "fa-solid fa-xmark", "fa-solid fa-image", "fa-solid fa-video", "fa-solid fa-camera", "fa-solid fa-download", "fa-solid fa-upload", "fa-solid fa-share", "fa-solid fa-thumbs-up", "fa-solid fa-thumbs-down", "fa-solid fa-comment", "fa-solid fa-pen", "fa-solid fa-trash", "fa-solid fa-gear", "fa-solid fa-folder", "fa-solid fa-file", "fa-solid fa-code", "fa-solid fa-terminal", "fa-solid fa-bug", "fa-solid fa-database", "fa-solid fa-cloud", "fa-solid fa-wifi", "fa-solid fa-desktop", "fa-solid fa-mobile", "fa-brands fa-facebook", "fa-brands fa-twitter", "fa-brands fa-instagram", "fa-brands fa-github", "fa-brands fa-discord", "fa-brands fa-youtube", "fa-brands fa-docker", "fa-brands fa-php", "fa-brands fa-js", "fa-brands fa-html5", "fa-brands fa-css3", "fa-brands fa-python", "fa-brands fa-linux", "fa-brands fa-windows", "fa-brands fa-apple", "fa-brands fa-android", "fa-brands fa-google"];
    const icGrid = document.getElementById('iconGrid');
    
    if (icGrid) {
        function renderIcons(flt='') {
            icGrid.innerHTML=''; const l=flt.toLowerCase();
            icons.forEach(c=>{
                if(c.toLowerCase().includes(l)) {
                    const d=document.createElement('div'); d.className='icon-item';
                    d.innerHTML=`<i class="${c}"></i><div style="font-size:9px;overflow:hidden;width:100%">${c.replace('fa-solid fa-','')}</div>`;
                    d.onclick=()=>{ 
                        if(input) { insertTag(`<i class="${c}"></i>`); document.getElementById('iconModal').style.display='none'; }
                    };
                    icGrid.appendChild(d);
                }
            });
        }
        function openIconModal(){ document.getElementById('iconModal').style.display='flex'; renderIcons(); document.getElementById('iconSearch').focus(); }
        function filterIcons(){ renderIcons(document.getElementById('iconSearch').value); }
    }

    function toggleLang() { document.getElementById('langMenu').classList.toggle('show'); }
    window.addEventListener('click', function(e) { 
        if (!document.getElementById('langDropdown').contains(e.target)) document.getElementById('langMenu').classList.remove('show'); 
        if (!e.target.closest('button') && document.getElementById('smileyPopover') && !document.getElementById('smileyPopover').contains(e.target)) document.getElementById('smileyPopover').style.display='none';
    });
</script>
</body>

</html>
