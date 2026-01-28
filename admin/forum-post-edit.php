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
        .editor-split { display: flex; flex: 1; overflow: hidden; }
        .editor-area, .preview-area { flex: 1; padding: 15px; overflow-y: auto; }
        .editor-area { border-right: 1px solid #ddd; }
        textarea { width: 100%; height: 100%; border: none; outline: none; font-family: monospace; resize: none; font-size: 14px; }
        #mediaModal, #iconModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 20px; border-radius: 12px; width: 80%; max-width: 900px; max-height: 80vh; overflow-y: auto; display: flex; flex-direction: column; }
        .media-grid, .icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px; overflow-y: auto; }
        .media-item, .icon-item { cursor: pointer; border: 1px solid #eee; border-radius: 6px; padding: 5px; text-align: center; background: #f8fafc; transition: all 0.2s; }
        .media-item:hover, .icon-item:hover { border-color: #3182ce; transform: scale(1.05); }
        .media-item img { width: 100%; height: 80px; object-fit: cover; border-radius: 4px; }
        .media-item .file-icon { font-size: 2.5rem; line-height: 80px; height: 80px; }
        #thread-title { width: 100%; padding: 15px; font-size: 18px; font-weight: bold; border: none; border-bottom: 1px solid #eee; outline: none; background: #fff; box-sizing: border-box; }
        .preview-area pre { background: #23241f; color: #f8f8f2; padding: 1em; border-radius: 6px; overflow-x: auto; }
        .preview-area code { font-family: 'Fira Code', Consolas, monospace; font-size: 14px; }
        .editor-toolbar { background: #f8fafc; border-bottom: 1px solid #cbd5e0; padding: 10px; font-family: sans-serif; }
        .editor-row { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; }
        .editor-row:last-child { margin-bottom: 0; }
        .editor-label { font-weight: 800; color: #1877f2; width: 90px; font-size: 11px; text-transform: uppercase; flex-shrink: 0; }
        .editor-label.green { color: #42b72a; }
        .ed-btn { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 5px 10px; font-size: 13px; cursor: pointer; color: #444; display: inline-flex; align-items: center; justify-content: center; min-width: 30px; transition: all .2s; font-weight: 500; }
        .ed-btn:hover { background: #f0f2f5; border-color: #bbb; color: #000; }
        .ed-sep { width: 1px; height: 20px; background: #eee; margin: 0 5px; }
        .ico-img::before { content: "🖼️"; font-size: 12px; }
        .ico-link::before { content: "🔗"; font-size: 12px; }
        .smiley-popover { display: none; position: absolute; background: #fff; border: 1px solid #ccc; border-radius: 8px; padding: 10px; width: 300px; height: 250px; overflow-y: auto; box-shadow: 0 4px 15px #0003; z-index: 1000; }
        .smiley-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 5px; }
        .smiley-item { cursor: pointer; font-size: 20px; text-align: center; padding: 5px; border-radius: 4px; }
        .smiley-item:hover { background: #f0f2f5; }
        .input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .icon-search-bar { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        .icon-name { font-size: 10px; color: #666; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; width: 100%; }
        :not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}
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

                <div class="editor-toolbar">
                    <div class="editor-row">
                        <span class="editor-label"><?= htmlspecialchars($fteLang['label_markdown'] ?? 'Markdown:') ?></span>
                        <button type="button" class="ed-btn" onclick="insertTag('**', '**')" title="<?= htmlspecialchars($fteLang['tooltip_bold'] ?? 'Bold') ?>"><b>B</b></button>
                        <button type="button" class="ed-btn" onclick="insertTag('*', '*')" title="<?= htmlspecialchars($fteLang['tooltip_italic'] ?? 'Italic') ?>"><i>I</i></button>
                        <button type="button" class="ed-btn" onclick="insertTag('~~', '~~')" title="<?= htmlspecialchars($fteLang['tooltip_strike'] ?? 'Strike') ?>"><s>S</s></button>
                        <div class="ed-sep"></div>
                        <button type="button" class="ed-btn" onclick="insertTag('# ', '')">H1</button>
                        <button type="button" class="ed-btn" onclick="insertTag('## ', '')">H2</button>
                        <button type="button" class="ed-btn" onclick="insertTag('### ', '')">H3</button>
                        <div class="ed-sep"></div>
                        
                        <button type="button" class="ed-btn ico-link" onclick="insertLink()" title="<?= htmlspecialchars($fteLang['tooltip_link'] ?? 'Link') ?>"></button>
                        <button type="button" class="ed-btn ico-img" onclick="openMediaModal()" title="<?= htmlspecialchars($fteLang['tooltip_image'] ?? 'Image') ?>"></button>
                        <div class="ed-sep"></div>
                        <button type="button" class="ed-btn" onclick="insertTag('`', '`')" style="font-family:monospace;">`code`</button>
                        <button type="button" class="ed-btn" onclick="insertTag('```\n', '\n```')" title="<?= htmlspecialchars($fteLang['tooltip_code_block'] ?? 'Code') ?>">...</button>
                        
                        <div class="ed-sep"></div>
                        <div style="position:relative;">
                            <button type="button" class="ed-btn" onclick="toggleSmileyPicker(this)" title="<?= htmlspecialchars($fteLang['tooltip_emojis'] ?? 'Emojis') ?>">😀</button>
                            <div id="smileyPopover" class="smiley-popover">
                                <div id="smileyGrid" class="smiley-grid"></div>
                            </div>
                        </div>
                        <button type="button" class="ed-btn" onclick="openIconModal()" title="<?= htmlspecialchars($fteLang['modal_icons'] ?? 'Icons') ?>"><?= htmlspecialchars($fteLang['btn_fa_icons'] ?? 'FA Icons') ?></button>

                        <div class="ed-sep"></div>
                        <button type="button" class="ed-btn" onclick="insertTag('* ', '')"><?= htmlspecialchars($fteLang['btn_list_bullet'] ?? '• List') ?></button>
                        <button type="button" class="ed-btn" onclick="insertTag('1. ', '')"><?= htmlspecialchars($fteLang['btn_list_number'] ?? '1. List') ?></button>
                        <button type="button" class="ed-btn" onclick="insertTag('> ', '')">💬</button>
                        <button type="button" class="ed-btn" onclick="insertTag('\n---\n', '')">---</button>
                    </div>
                    <div class="editor-row">
                        <span class="editor-label green"><?= htmlspecialchars($fteLang['label_html'] ?? 'HTML:') ?></span>
                        <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:center\'>', '</div>')"><?= htmlspecialchars($fteLang['btn_center'] ?? 'Center') ?></button>
                        <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:right\'>', '</div>')"><?= htmlspecialchars($fteLang['btn_right'] ?? 'Right') ?></button>
                        <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:left\'>', '</div>')"><?= htmlspecialchars($fteLang['btn_left'] ?? 'Left') ?></button>
                        <div class="ed-sep"></div>
                        
                        <button type="button" class="ed-btn" onclick="document.getElementById('html-color-picker').click()">
                            <?= htmlspecialchars($fteLang['btn_color'] ?? 'Color') ?>
                        </button>
                        <input type="color" id="html-color-picker" style="display:none">
                        
                        <button type="button" class="ed-btn" onclick="insertTag('<mark>', '</mark>')"><?= htmlspecialchars($fteLang['btn_mark'] ?? 'Mark') ?></button>
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
                        <textarea id="markdown-input" spellcheck="false"><?= htmlspecialchars($content) ?></textarea>
                    </div>
                    <div id="preview-box" class="preview-area"></div>
                </div>
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

    <div id="mediaModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h3 style="margin:0;"><?= htmlspecialchars($fteLang['modal_media'] ?? 'Media') ?></h3>
                <div style="display:flex; gap:10px;">
                    <input type="file" id="uploadInput" style="display:none" onchange="uploadFile(this)">
                    <button class="ed-btn" onclick="document.getElementById('uploadInput').click()" style="background:#e7f3ff; color:#1877f2; border-color:#bee3f8;">
                        <?= htmlspecialchars($fteLang['btn_upload'] ?? 'Upload') ?>
                    </button>
                    <button class="ed-btn" onclick="closeMediaModal()"><?= htmlspecialchars($fteLang['btn_close'] ?? 'Close') ?></button>
                </div>
            </div>
            <div id="uploadStatus" style="font-size: 12px; margin-bottom: 10px; display:none;"></div>
            <div class="media-grid" id="mediaGrid">
                <?php foreach($allFiles as $f): ?>
                    <div class="media-item" onclick="selectImage('<?= htmlspecialchars($f) ?>')">
                        <?php if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)): ?>
                            <img src="/uploads/<?= htmlspecialchars($f) ?>" title="<?= htmlspecialchars($f) ?>">
                        <?php else: ?>
                            <div class="file-icon" title="<?= htmlspecialchars($f) ?>">📄</div>
                            <div style="font-size: 10px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($f) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="iconModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h3 style="margin:0;"><?= htmlspecialchars($fteLang['modal_icons'] ?? 'Icons') ?></h3>
                <button class="ed-btn" onclick="closeIconModal()"><?= htmlspecialchars($fteLang['btn_close'] ?? 'Close') ?></button>
            </div>
            <input type="text" id="iconSearch" class="icon-search-bar" placeholder="<?= htmlspecialchars($fteLang['placeholder_icons'] ?? 'Search...') ?>" onkeyup="filterIcons()">
            <div id="iconGrid" class="icon-grid"></div>
        </div>
    </div>

    <script>
        const input = document.getElementById('markdown-input');
        const preview = document.getElementById('preview-box');
        const txtEnterUrl = "<?= htmlspecialchars($fteLang['prompt_url'] ?? 'Enter URL:') ?>";
        const txtLinkDefault = "<?= htmlspecialchars($fteLang['default_link_text'] ?? 'Link Text') ?>";
        const msgUploaded = "<?= htmlspecialchars($fteLang['msg_uploaded'] ?? 'File uploaded successfully.') ?>";
        const errorUpload = "<?= htmlspecialchars($fteLang['error_upload'] ?? 'Upload failed.') ?>";
        const txtNetworkError = "<?= htmlspecialchars($fteLang['error_network'] ?? 'Network Error') ?>";

        function updatePreview() {
            preview.innerHTML = marked.parse(input.value);
            if(window.hljs) hljs.highlightAll();
        }
        input.addEventListener('input', updatePreview);
        setTimeout(updatePreview, 100);

        function insertTag(open, close) {
            if (!input) return;
            const scrollTop = input.scrollTop;
            const s = input.selectionStart;
            const e = input.selectionEnd;
            const val = input.value;
            
            let text = val.substring(s, e);
            let trailingSpace = "";
            if (text.length > 0 && text.endsWith(" ")) {
                text = text.trimEnd();
                trailingSpace = " ";
            }
            
            const replacement = open + text + close + trailingSpace;
            input.value = val.substring(0, s) + replacement + val.substring(e);
            
            const newPos = s + open.length + text.length + (text.length === 0 ? 0 : close.length);
            input.focus();
            input.setSelectionRange(newPos, newPos);
            input.scrollTop = scrollTop;
            updatePreview();
        }

        function insertLink() {
            if (!input) return;
            const scrollTop = input.scrollTop;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            let text = input.value.substring(start, end);

            let url = prompt(txtEnterUrl, "https://");
            if (url === null) return;

            let label = text.length > 0 ? text : txtLinkDefault;
            const replacement = `[${label}](${url})`;

            input.value = input.value.substring(0, start) + replacement + input.value.substring(end);
            
            const newPos = start + replacement.length;
            input.focus();
            input.setSelectionRange(newPos, newPos);
            input.scrollTop = scrollTop;
            updatePreview();
        }

        function generateSlug() {
            const title = document.getElementById('thread-title').value;
            const slug = title.toLowerCase()
                .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim().replace(/\s+/g, '-');
            document.getElementById('thread-slug').value = slug;
        }

        document.getElementById('html-color-picker').addEventListener('change', function(e) {
            insertTag('<span style="color:' + e.target.value + '">', '</span>');
        });

        function openMediaModal() { document.getElementById('mediaModal').style.display = 'flex'; }
        function closeMediaModal() { document.getElementById('mediaModal').style.display = 'none'; }
        
        function selectImage(filename) {
            insertTag(`\n![Image](/uploads/${filename})\n`, '');
            closeMediaModal();
        }

        function uploadFile(inputElement) {
            if(inputElement.files.length === 0) return;
            
            const file = inputElement.files[0];
            const formData = new FormData();
            formData.append('file', file);
            
            const statusDiv = document.getElementById('uploadStatus');
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#4a5568';
            statusDiv.innerText = "Uploading...";

            fetch('upload-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'ok') {
                    statusDiv.style.color = 'green';
                    statusDiv.innerText = msgUploaded;
                    
                    const grid = document.getElementById('mediaGrid');
                    const div = document.createElement('div');
                    div.className = 'media-item';
                    div.onclick = function() { selectImage(data.filename); };
                    
                    if (data.filename.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
                        div.innerHTML = `<img src="/uploads/${data.filename}" title="${data.filename}">`;
                    } else {
                        div.innerHTML = `<div class="file-icon" title="${data.filename}">📄</div><div style="font-size: 10px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${data.filename}</div>`;
                    }
                    
                    grid.insertBefore(div, grid.firstChild);
                    setTimeout(() => { statusDiv.style.display = 'none'; }, 2000);
                } else {
                    statusDiv.style.color = 'red';
                    statusDiv.innerText = errorUpload + " " + (data.error || '');
                }
            })
            .catch(err => {
                statusDiv.style.color = 'red';
                statusDiv.innerText = txtNetworkError;
            });
            inputElement.value = ''; 
        }

        const smileys = [
            "😀","😃","😄","😁","😆","😅","😂","🤣","😊","😇","🙂","🙃","😉","😌","😍","🥰","😘","😗","😙","😚","😋","😛","😝","😜","🤪","🤨","🧐","🤓","😎","🤩","🥳","😏","😒","😞","😔","😟","😕","🙁","☹️","😣","😖","😫","😩","🥺","😢","😭","😤","😠","😡","🤬","🤯","😳","🥵","🥶","😱","😨","😰","😥","😓","🤗","🤔","🤭","🤫","🤥","😶","😐","😑","😬","🙄","😯","😦","😧","😮","😲","🥱","😴","🤤","😪","😵","🤐","🥴","🤢","🤮","🤧","😷","🤒","🤕","🤑","🤠","😈","👿","👹","👺","🤡","💩","👻","💀","👽","👾","🤖","🎃","😺","😸","😹","😻","😼","😽","🙀","😿","𘘾","🤲","👐","🙌","👏","🤝","👍","👎","👊","✊","🤛","🤜","🤞","✌️","🤟","🤘","👌","🤏","👈","👉","👆","👇","☝️","✋","🤚","🖐","🖖","👋","🤙","💪","🧠","🦷","🦴","👀","👁","👄","💋","🦶","🦵","👃","👂","🦻","👣","🔥","💥","✨","🌟","💫","❤️","🧡","💛","💚","💙","💜","🖤","🤍","🤎","💔","❣️","💕","💞","💓","💗","💖","💘","💝","💟"
        ];
        
        const smileyGrid = document.getElementById('smileyGrid');
        smileys.forEach(s => {
            const span = document.createElement('span');
            span.className = 'smiley-item';
            span.innerText = s;
            span.onclick = () => { insertTag(s, ''); document.getElementById('smileyPopover').style.display='none'; };
            smileyGrid.appendChild(span);
        });

        function toggleSmileyPicker(btn) {
            const pop = document.getElementById('smileyPopover');
            if (pop.style.display === 'block') {
                pop.style.display = 'none';
            } else {
                pop.style.display = 'block';
                pop.style.top = (btn.offsetTop + btn.offsetHeight + 5) + 'px';
                pop.style.left = btn.offsetLeft + 'px';
            }
        }
        
        document.addEventListener('click', function(e) {
            const pop = document.getElementById('smileyPopover');
            const btn = document.querySelector('button[title="<?= htmlspecialchars($fteLang['tooltip_emojis'] ?? 'Emojis') ?>"]');
            if(pop.style.display === 'block' && !pop.contains(e.target) && e.target !== btn) {
                pop.style.display = 'none';
            }
        });

        const icons = [
            "fa-solid fa-user", "fa-regular fa-user", "fa-solid fa-users", "fa-solid fa-user-plus", "fa-solid fa-house", "fa-solid fa-magnifying-glass",
            "fa-solid fa-bars", "fa-solid fa-envelope", "fa-regular fa-envelope", "fa-solid fa-heart", "fa-regular fa-heart", "fa-solid fa-star",
            "fa-regular fa-star", "fa-solid fa-check", "fa-solid fa-xmark", "fa-solid fa-music", "fa-solid fa-image", "fa-regular fa-image",
            "fa-solid fa-video", "fa-solid fa-camera", "fa-solid fa-circle-check", "fa-solid fa-circle-xmark", "fa-solid fa-circle-info",
            "fa-solid fa-download", "fa-solid fa-upload", "fa-solid fa-share", "fa-solid fa-share-nodes", "fa-solid fa-thumbs-up", "fa-regular fa-thumbs-up",
            "fa-solid fa-thumbs-down", "fa-regular fa-thumbs-down", "fa-solid fa-comment", "fa-regular fa-comment", "fa-solid fa-comments",
            "fa-solid fa-quote-left", "fa-solid fa-quote-right", "fa-solid fa-pen", "fa-solid fa-pencil", "fa-solid fa-trash", "fa-regular fa-trash-can",
            "fa-solid fa-gear", "fa-solid fa-gears", "fa-solid fa-list", "fa-solid fa-folder", "fa-regular fa-folder", "fa-solid fa-folder-open",
            "fa-solid fa-file", "fa-regular fa-file", "fa-solid fa-file-pdf", "fa-solid fa-file-word", "fa-solid fa-file-excel", "fa-solid fa-file-code",
            "fa-solid fa-code", "fa-solid fa-terminal", "fa-solid fa-bug", "fa-solid fa-laptop-code", "fa-solid fa-microchip", "fa-solid fa-memory",
            "fa-solid fa-hard-drive", "fa-solid fa-server", "fa-solid fa-database", "fa-solid fa-cloud", "fa-solid fa-cloud-arrow-up", "fa-solid fa-cloud-arrow-down",
            "fa-solid fa-wifi", "fa-solid fa-signal", "fa-solid fa-battery-full", "fa-solid fa-plug", "fa-solid fa-power-off", "fa-solid fa-desktop",
            "fa-solid fa-laptop", "fa-solid fa-mobile-screen", "fa-solid fa-tablet-screen-button", "fa-solid fa-keyboard", "fa-solid fa-mouse",
            "fa-solid fa-print", "fa-solid fa-floppy-disk", "fa-regular fa-floppy-disk", "fa-solid fa-bell", "fa-regular fa-bell", "fa-solid fa-calendar",
            "fa-regular fa-calendar", "fa-solid fa-calendar-days", "fa-solid fa-clock", "fa-regular fa-clock", "fa-solid fa-location-dot", "fa-solid fa-map",
            "fa-solid fa-compass", "fa-regular fa-compass", "fa-solid fa-signs-post", "fa-solid fa-car", "fa-solid fa-truck", "fa-solid fa-bicycle",
            "fa-solid fa-plane", "fa-solid fa-train", "fa-solid fa-rocket", "fa-solid fa-ship", "fa-solid fa-anchor", "fa-solid fa-money-bill",
            "fa-regular fa-money-bill-1", "fa-solid fa-credit-card", "fa-regular fa-credit-card", "fa-solid fa-wallet", "fa-solid fa-cart-shopping",
            "fa-solid fa-basket-shopping", "fa-solid fa-bag-shopping", "fa-solid fa-tag", "fa-solid fa-tags", "fa-solid fa-percent", "fa-solid fa-gift",
            "fa-solid fa-trophy", "fa-solid fa-medal", "fa-solid fa-award", "fa-solid fa-crown", "fa-solid fa-gamepad", "fa-solid fa-puzzle-piece",
            "fa-solid fa-ghost", "fa-solid fa-skull", "fa-solid fa-book", "fa-solid fa-book-open", "fa-solid fa-graduation-cap", "fa-solid fa-school",
            "fa-solid fa-lightbulb", "fa-regular fa-lightbulb", "fa-solid fa-sun", "fa-regular fa-sun", "fa-solid fa-moon", "fa-regular fa-moon",
            "fa-solid fa-cloud-rain", "fa-solid fa-bolt", "fa-solid fa-snowflake", "fa-solid fa-fire", "fa-solid fa-water", "fa-solid fa-leaf",
            "fa-solid fa-tree", "fa-solid fa-seedling", "fa-solid fa-paw", "fa-solid fa-dog", "fa-solid fa-cat", "fa-solid fa-fish", "fa-solid fa-dragon",
            "fa-solid fa-globe", "fa-solid fa-earth-americas", "fa-solid fa-earth-europe", "fa-solid fa-lock", "fa-solid fa-unlock", "fa-solid fa-key",
            "fa-solid fa-shield-halved", "fa-solid fa-eye", "fa-regular fa-eye", "fa-solid fa-eye-slash", "fa-regular fa-eye-slash", "fa-solid fa-link",
            "fa-solid fa-paperclip", "fa-solid fa-scissors", "fa-solid fa-filter", "fa-solid fa-arrow-up", "fa-solid fa-arrow-down", "fa-solid fa-arrow-left",
            "fa-solid fa-arrow-right", "fa-solid fa-rotate-right", "fa-solid fa-rotate-left", "fa-solid fa-play", "fa-solid fa-pause", "fa-solid fa-stop",
            "fa-brands fa-facebook", "fa-brands fa-twitter", "fa-brands fa-instagram", "fa-brands fa-linkedin", "fa-brands fa-github", "fa-brands fa-discord",
            "fa-brands fa-youtube", "fa-brands fa-tiktok", "fa-brands fa-twitch", "fa-brands fa-whatsapp", "fa-brands fa-telegram", "fa-brands fa-pinterest",
            "fa-brands fa-reddit", "fa-brands fa-snapchat", "fa-brands fa-spotify", "fa-brands fa-soundcloud", "fa-brands fa-apple", "fa-brands fa-windows",
            "fa-brands fa-linux", "fa-brands fa-android", "fa-brands fa-docker", "fa-brands fa-php", "fa-brands fa-js", "fa-brands fa-html5", "fa-brands fa-css3",
            "fa-brands fa-python", "fa-brands fa-java", "fa-brands fa-rust", "fa-brands fa-react", "fa-brands fa-vuejs", "fa-brands fa-angular",
            "fa-brands fa-node", "fa-brands fa-npm", "fa-brands fa-git", "fa-brands fa-gitlab", "fa-brands fa-bitbucket", "fa-brands fa-aws",
            "fa-brands fa-google", "fa-brands fa-microsoft", "fa-brands fa-paypal", "fa-brands fa-stripe", "fa-brands fa-bitcoin", "fa-brands fa-ethereum",
            "fa-brands fa-chrome", "fa-brands fa-firefox", "fa-brands fa-edge", "fa-brands fa-safari"
        ];

        const iconGrid = document.getElementById('iconGrid');
        
        function renderIcons(filter = "") {
            iconGrid.innerHTML = "";
            const lowerFilter = filter.toLowerCase();
            icons.forEach(cls => {
                if(cls.toLowerCase().includes(lowerFilter)) {
                    const div = document.createElement('div');
                    div.className = 'icon-item';
                    div.innerHTML = `<i class="${cls}"></i><div class="icon-name">${cls.replace('fa-solid fa-', '').replace('fa-brands fa-', '').replace('fa-regular fa-', '')}</div>`;
                    div.onclick = () => { insertTag(`<i class="${cls}"></i>`, ''); closeIconModal(); };
                    iconGrid.appendChild(div);
                }
            });
        }

        function openIconModal() { document.getElementById('iconModal').style.display='flex'; renderIcons(); document.getElementById('iconSearch').focus(); }
        function closeIconModal() { document.getElementById('iconModal').style.display='none'; }
        function filterIcons() { renderIcons(document.getElementById('iconSearch').value); }

        document.getElementById('save-btn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerText = "<?= htmlspecialchars($fteLang['saving'] ?? 'Saving...') ?>";

            const payload = {
                id: <?= $id ?>,
                title: document.getElementById('thread-title').value,
                slug: document.getElementById('thread-slug').value,
                board_id: document.getElementById('board-id').value,
                label_id: document.getElementById('label-id').value,
                is_sticky: document.getElementById('thread-sticky').checked ? 1 : 0,
                is_locked: document.getElementById('thread-locked').checked ? 1 : 0,
                content: input.value
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