<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$fteLang = $t_temp['forum_thread_edit'] ?? [];
$peLang = $t_temp['editor'] ?? [];

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM forum_threads WHERE id = ?");
$stmt->execute([$id]);
$thread = $stmt->fetch();

if (!$thread) { die($fteLang['error_thread_not_found'] ?? 'Thread not found.'); }

$stmtPost = $pdo->prepare("SELECT content FROM forum_posts WHERE thread_id = ? ORDER BY created_at ASC LIMIT 1");
$stmtPost->execute([$id]);
$firstPost = $stmtPost->fetch();
$content = $firstPost['content'] ?? '';

$boards = $pdo->query("SELECT id, title FROM forum_boards ORDER BY sort_order ASC")->fetchAll();
$labels = $pdo->query("SELECT id, title FROM forum_labels ORDER BY sort_order ASC")->fetchAll();

$uploadDir = __DIR__ . '/../public/uploads/';
$allFiles = is_dir($uploadDir) ? array_diff(scandir($uploadDir), ['.', '..']) : [];
usort($allFiles, function($a, $b) use ($uploadDir) {
    return filemtime($uploadDir . $b) - filemtime($uploadDir . $a);
});
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($fteLang['title_prefix'] ?? 'Forum Editor -') ?> <?= htmlspecialchars($thread['title']) ?></title>
    <link href="/admin/assets/styles/admin.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .editor-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; height: calc(100vh - 120px); }
        .editor-main-card { display: flex; flex-direction: column; background: #fff; border-radius: 8px; border: 1px solid #ddd; overflow: hidden; }
        .sidebar-card { background: #fff; border-radius: 8px; border: 1px solid #ddd; padding: 20px; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; }
        #thread-title { width: 100%; padding: 15px; font-size: 18px; font-weight: bold; border: none; border-bottom: 1px solid #eee; outline: none; background: #fff; box-sizing: border-box; }
        .input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .editor-container { border: none; margin-top: 0; box-shadow: none; border-radius: 0; flex: 1; display: flex; flex-direction: column; }
        .editor-split { flex: 1; }
        .editor-area textarea { background: #fff; }
        .preview-area { background: #fff; }
    </style>
</head>
<body class="admin-layout">
    <aside class="admin-sidebar">
        <h2 class="brand"><?= htmlspecialchars($ini['app']['name'] ?? 'Admin') ?></h2>
        <nav>
            <a href="/admin/forum-posts.php"><?= htmlspecialchars($fteLang['back_btn'] ?? 'Back') ?></a>
            <div style="margin-top: 20px; padding: 0 15px;">
                <button id="save-btn" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars($fteLang['save_btn'] ?? 'Save') ?></button>
            </div>
        </nav>
    </aside>

    <main class="admin-content">
        <div class="editor-layout">
            <div class="editor-main-card">
                <input type="text" id="thread-title" value="<?= htmlspecialchars($thread['title']) ?>" placeholder="<?= htmlspecialchars($fteLang['title_placeholder'] ?? '') ?>">

                <?php 
                    $editorContent = $content;
                    $editorUploadUrl = 'upload-handler.php';
                    include __DIR__ . '/../public/templates/editor.php'; 
                ?>
            </div>

            <div class="sidebar-card">
                <h3 style="margin-top:0; font-size:14px; text-transform:uppercase; color:#666;"><?= htmlspecialchars($fteLang['label_settings'] ?? 'Settings') ?></h3>
                
                <div>
                    <label style="font-weight:bold; font-size:12px;"><?= htmlspecialchars($fteLang['label_board'] ?? 'Forum Board') ?></label>
                    <select id="board-id" class="input">
                        <?php foreach($boards as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $b['id'] == $thread['board_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top:10px;">
                    <label style="font-weight:bold; font-size:12px;"><?= htmlspecialchars($fteLang['label_label'] ?? 'Label') ?></label>
                    <select id="label-id" class="input">
                        <option value="0"><?= htmlspecialchars($fteLang['no_label'] ?? 'No Label') ?></option>
                        <?php foreach($labels as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $l['id'] == $thread['label_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top:10px;">
                    <label style="font-weight:bold; font-size:12px;"><?= htmlspecialchars($fteLang['label_slug'] ?? 'Slug') ?></label>
                    <input type="text" id="thread-slug" class="input" value="<?= htmlspecialchars($thread['slug']) ?>">
                    <small style="color:#3182ce; cursor:pointer;" onclick="generateSlug()"><?= htmlspecialchars($fteLang['hint_slug_auto'] ?? 'Auto generate') ?></small>
                </div>

                <div style="margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="thread-sticky" <?= $thread['is_sticky'] ? 'checked' : '' ?>> 
                        <?= htmlspecialchars($fteLang['label_sticky'] ?? 'Pin Thread') ?>
                    </label>
                </div>
                
                <div style="margin-top:5px;">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="thread-locked" <?= $thread['is_locked'] ? 'checked' : '' ?>> 
                        <?= htmlspecialchars($fteLang['label_locked'] ?? 'Lock Thread') ?>
                    </label>
                </div>
            </div>
        </div>
    </main>

    <script>
        function generateSlug() {
            const title = document.getElementById('thread-title').value;
            const slug = title.toLowerCase()
                .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim().replace(/\s+/g, '-');
            document.getElementById('thread-slug').value = slug;
        }

        document.getElementById('save-btn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerText = "<?= htmlspecialchars($fteLang['saving'] ?? 'Saving...') ?>";

            const inputContent = document.getElementById('markdown-input');

            const payload = {
                id: <?= $id ?>,
                title: document.getElementById('thread-title').value,
                slug: document.getElementById('thread-slug').value,
                board_id: document.getElementById('board-id').value,
                label_id: document.getElementById('label-id').value,
                is_sticky: document.getElementById('thread-sticky').checked ? 1 : 0,
                is_locked: document.getElementById('thread-locked').checked ? 1 : 0,
                content: inputContent ? inputContent.value : ''
            };

            fetch('save-forum-post-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(d => {
                btn.disabled = false;
                if(d.status === 'ok') btn.innerText = "<?= htmlspecialchars($fteLang['saved'] ?? 'Saved') ?>";
                else alert("<?= htmlspecialchars($fteLang['error_prefix'] ?? 'Error:') ?> " + d.error);
                setTimeout(() => btn.innerText = "<?= htmlspecialchars($fteLang['save_btn'] ?? 'Save') ?>", 2000);
            }).catch(e => {
                alert("<?= htmlspecialchars($fteLang['error_network'] ?? 'Network Error') ?>");
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>