<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) { die("Beitrag nicht gefunden."); }

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Pfad auf das Haupt-Upload-Verzeichnis angepasst
$uploadDir = __DIR__ . '/../public/uploads/';
$allFiles = is_dir($uploadDir) ? array_diff(scandir($uploadDir), ['.', '..']) : [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Editor - <?= htmlspecialchars($data['title']) ?></title>
    <link href="/admin/assets/styles/admin.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/dockerfile.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ORIGINAL LAYOUT STYLES (Beibehalten) */
        .editor-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; height: calc(100vh - 120px); }
        .editor-main-card { display: flex; flex-direction: column; background: #fff; border-radius: 8px; border: 1px solid #ddd; overflow: hidden; }
        .sidebar-card { background: #fff; border-radius: 8px; border: 1px solid #ddd; padding: 20px; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; }
        
        .editor-split { display: flex; flex: 1; overflow: hidden; }
        .editor-area, .preview-area { flex: 1; padding: 15px; overflow-y: auto; }
        .editor-area { border-right: 1px solid #ddd; }
        textarea { width: 100%; height: 100%; border: none; outline: none; font-family: monospace; resize: none; font-size: 14px; }
        
        /* Modal Styles (Original) */
        #mediaModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 20px; border-radius: 12px; width: 80%; max-width: 900px; max-height: 80vh; overflow-y: auto; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px; margin-top: 15px; }
        .media-item { cursor: pointer; border: 2px solid transparent; border-radius: 6px; overflow: hidden; transition: 0.2s; text-align: center; background: #f8fafc; padding: 5px; }
        .media-item:hover { border-color: #3182ce; transform: scale(1.05); }
        .media-item img { width: 100%; height: 100px; object-fit: cover; display: block; border-radius: 4px; }
        .media-item .file-icon { font-size: 3rem; line-height: 100px; height: 100px; }

        /* Titel Input direkt über dem Editor */
        #post-title {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            border: none;
            border-bottom: 1px solid #eee;
            outline: none;
            box-sizing: border-box;
            background: #fff;
        }

        /* Vorschau Styling für Monokai Highlighting */
        .preview-area pre {
            background: #23241f; /* Dunkler Hintergrund */
            color: #f8f8f2;      /* Helle Schrift */
            padding: 1em;
            border-radius: 6px;
            overflow-x: auto;
        }
        .preview-area code {
            font-family: 'Fira Code', Consolas, monospace;
            font-size: 14px;
        }

        /* Umfangreiche Toolbar Styles */
        .editor-toolbar {
            background: #f8fafc;
            border-bottom: 1px solid #cbd5e0;
            padding: 10px;
            font-family: sans-serif;
        }
        .editor-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .editor-row:last-child { margin-bottom: 0; }
        
        .editor-label {
            font-weight: 800;
            color: #1877f2;
            width: 90px;
            font-size: 11px;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .editor-label.green { color: #42b72a; }

        .ed-btn {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 13px;
            cursor: pointer;
            color: #444;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 30px;
            transition: all 0.2s;
            font-weight: 500;
        }
        .ed-btn:hover { background: #f0f2f5; border-color: #bbb; color: #000; }
        .ed-sep { width: 1px; height: 20px; background: #eee; margin: 0 5px; }
        
        /* Icons */
        .ico-img::before { content: "🖼️"; font-size:12px; }
        .ico-link::before { content: "🔗"; font-size:12px; }

        /* NEU: SMILEY POPOVER STYLES */
        .smiley-popover {
            display: none;
            position: absolute;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            width: 300px;
            height: 250px;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .smiley-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 5px;
        }
        .smiley-item {
            cursor: pointer;
            font-size: 20px;
            text-align: center;
            padding: 5px;
            border-radius: 4px;
        }
        .smiley-item:hover { background: #f0f2f5; }

        /* NEU: ICON MODAL STYLES */
        #iconModal { display: none; position: fixed; z-index: 10001; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); align-items: center; justify-content: center; }
        .icon-search-bar { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        .icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 10px; max-height: 60vh; overflow-y: auto; }
        .icon-item {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 10px; border: 1px solid #eee; border-radius: 6px; cursor: pointer; transition: all 0.2s;
        }
        .icon-item:hover { background: #e7f3ff; border-color: #1877f2; color: #1877f2; }
        .icon-item i { font-size: 24px; margin-bottom: 5px; }
        .icon-name { font-size: 10px; color: #666; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; width: 100%; }
    </style>
</head>
<body class="admin-layout">
    <aside class="admin-sidebar">
        <h2 class="brand">PiperBlog</h2>
        <nav>
            <a href="/admin/posts.php">← Zurück</a>
            <div style="margin-top: 20px; padding: 0 15px;">
                <button id="save-btn" class="btn btn-primary" style="width: 100%;">Speichern</button>
            </div>
        </nav>
    </aside>

    <main class="admin-content">
        <div class="editor-layout">
            <div class="editor-main-card">
                
                <input type="text" id="post-title" value="<?= htmlspecialchars($data['title']) ?>" placeholder="Hier Titel eingeben...">

                <div class="editor-toolbar">
                    <div class="editor-row">
                        <span class="editor-label">Markdown:</span>
                        <button type="button" class="ed-btn" onclick="insertTag('**', '**')" title="Fett"><b>B</b></button>
                        <button type="button" class="ed-btn" onclick="insertTag('*', '*')" title="Kursiv"><i>I</i></button>
                        <button type="button" class="ed-btn" onclick="insertTag('~~', '~~')" title="Durchgestrichen"><s>S</s></button>
                        <div class="ed-sep"></div>
                        <button type="button" class="ed-btn" onclick="insertTag('# ', '')">H1</button>
                        <button type="button" class="ed-btn" onclick="insertTag('## ', '')">H2</button>
                        <button type="button" class="ed-btn" onclick="insertTag('### ', '')">H3</button>
                        <div class="ed-sep"></div>
                        <button type="button" class="ed-btn ico-link" onclick="insertTag('[Link Text](', ')')" title="Link"></button>
                        <button type="button" class="ed-btn ico-img" onclick="openMediaModal('content')" title="Bild"></button>
                        <div class="ed-sep"></div>
                        <button type="button" class="ed-btn" onclick="insertTag('`', '`')" style="font-family:monospace;">`code`</button>
                        <button type="button" class="ed-btn" onclick="insertTag('```\n', '\n```')" title="Code Block">...</button>
                        
                        <div class="ed-sep"></div>
                        <div style="position:relative;">
                            <button type="button" class="ed-btn" onclick="toggleSmileyPicker(this)" title="Emojis">😀</button>
                            <div id="smileyPopover" class="smiley-popover">
                                <div id="smileyGrid" class="smiley-grid"></div>
                            </div>
                        </div>
                        <button type="button" class="ed-btn" onclick="openIconModal()" title="FontAwesome Icons">🏁 FA Icons</button>

                        <div class="ed-sep"></div>
                        <button type="button" class="ed-btn" onclick="insertTag('* ', '')">• Liste</button>
                        <button type="button" class="ed-btn" onclick="insertTag('1. ', '')">1. Liste</button>
                        <button type="button" class="ed-btn" onclick="insertTag('> ', '')">💬</button>
                        <button type="button" class="ed-btn" onclick="insertTag('\n---\n', '')">---</button>
                    </div>
                    <div class="editor-row">
                        <span class="editor-label green">HTML:</span>
                        <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:center\'>', '</div>')">⬆ Center</button>
                        <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:right\'>', '</div>')">➡ Right</button>
                        <button type="button" class="ed-btn" onclick="insertTag('<div style=\'text-align:left\'>', '</div>')">⬅ Left</button>
                        <div class="ed-sep"></div>
                        <button type="button" class="ed-btn" onclick="insertTag('<span style=\'color:red\'>', '</span>')">🎨 Farbe</button>
                        <button type="button" class="ed-btn" onclick="insertTag('<mark>', '</mark>')">✨ Markieren</button>
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
                        <textarea id="markdown-input" spellcheck="false"><?= htmlspecialchars($data['content']) ?></textarea>
                    </div>
                    <div id="preview-box" class="preview-area article-body"></div>
                </div>
            </div>

            <div class="sidebar-card">
                <div>
                    <label style="font-weight: bold; font-size: 12px;">Status</label>
                    <select id="post-status" class="input">
                        <option value="draft" <?= $data['status'] === 'draft' ? 'selected' : '' ?>>Entwurf</option>
                        <option value="published" <?= $data['status'] === 'published' ? 'selected' : '' ?>>Veröffentlicht</option>
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="post-sticky" <?= ($data['is_sticky'] ?? 0) ? 'checked' : '' ?>>
                    <label for="post-sticky" style="font-weight: bold; font-size: 12px; cursor: pointer;">📌 Beitrag anheften</label>
                </div>

                <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                    <label style="font-weight: bold; font-size: 12px;">Einleitung (Auszug)</label>
                    <small style="display:block; color:#666; margin-bottom:5px;">Erscheint auf der Startseite</small>
                    <textarea id="post-excerpt" class="input" rows="4" style="resize:vertical; min-height:80px; font-family:inherit; width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px;"><?= htmlspecialchars($data['excerpt'] ?? '') ?></textarea>
                </div>

                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">

                <div>
                    <label style="font-weight: bold; font-size: 12px;">Kategorie</label>
                    <select id="post-category" class="input">
                        <option value="">Keine Kategorie</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $data['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="font-weight: bold; font-size: 12px;">Beitragsbild (Hero)</label>
                    <div style="display: flex; gap: 5px; margin-top: 5px;">
                        <input type="text" id="hero-image" class="input" value="<?= htmlspecialchars($data['hero_image'] ?? '') ?>" placeholder="bild.jpg">
                        <button class="btn" onclick="openMediaModal('hero')"> Wahl </button>
                    </div>
                    <div id="hero-preview" style="margin-top: 10px; height: 100px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if($data['hero_image']): ?>
                            <img src="/uploads/<?= htmlspecialchars($data['hero_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 12px;">Kein Bild</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label style="font-weight: bold; font-size: 12px;">Download Datei (Optional)</label>
                    <div style="display: flex; gap: 5px; margin-top: 5px;">
                        <input type="text" id="download-file" class="input" value="<?= htmlspecialchars($data['download_file'] ?? '') ?>" placeholder="datei.zip">
                        <button class="btn" onclick="openMediaModal('download')"> Wahl </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="mediaModal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">Mediathek</h3>
                <button class="btn" onclick="closeMediaModal()">Schließen</button>
            </div>
            <div class="media-grid">
                <?php foreach($allFiles as $f): ?>
                    <div class="media-item" onclick="selectImage('<?= htmlspecialchars($f) ?>')">
                        <?php if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)): ?>
                            <img src="/uploads/<?= htmlspecialchars($f) ?>" title="<?= htmlspecialchars($f) ?>">
                        <?php else: ?>
                            <div class="file-icon" title="<?= htmlspecialchars($f) ?>">📄</div>
                            <div style="font-size: 10px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($f) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="iconModal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0;">FontAwesome Icon Picker</h3>
                <button class="btn" onclick="closeIconModal()">Schließen</button>
            </div>
            <input type="text" id="iconSearch" class="icon-search-bar" placeholder="Suche nach Icons (z.B. user, car, arrow)..." onkeyup="filterIcons()">
            <div id="iconGrid" class="icon-grid">
                </div>
        </div>
    </div>

    <script>
        const input = document.getElementById('markdown-input');
        const preview = document.getElementById('preview-box');
        let currentTarget = 'hero'; 

        function updatePreview() { 
            // Markdown zu HTML
            preview.innerHTML = marked.parse(input.value);
            // Highlight.js anwenden, wenn geladen
            if(window.hljs) {
                hljs.highlightAll();
            }
        }
        
        input.addEventListener('input', updatePreview);
        setTimeout(updatePreview, 100);

        // Verbesserte Insert-Funktion mit Scroll-Fix
        function insertTag(open, close = '') {
            if (!input) return;

            const start = input.selectionStart;
            const end = input.selectionEnd;
            const scrollTop = input.scrollTop; // Position merken

            const selectedText = input.value.substring(start, end);
            const replacement = open + selectedText + close;

            input.value = input.value.substring(0, start) + replacement + input.value.substring(end);
            
            // Cursor neu setzen
            const newPos = start + open.length + selectedText.length + (selectedText.length === 0 ? 0 : close.length);
            input.focus();
            input.setSelectionRange(newPos, newPos);
            
            input.scrollTop = scrollTop; // Position wiederherstellen
            updatePreview();
        }

        // Alte Wrapper für Kompatibilität
        function wrap(before, after) { insertTag(before, after); }
        function insert(str) { insertTag(str, ''); }

        function openMediaModal(target) {
            currentTarget = target;
            document.getElementById('mediaModal').style.display = 'flex';
        }
        function closeMediaModal() { document.getElementById('mediaModal').style.display = 'none'; }

        function selectImage(filename) {
            if (currentTarget === 'hero') {
                document.getElementById('hero-image').value = filename;
                document.getElementById('hero-preview').innerHTML = `<img src="/uploads/${filename}" style="width:100%;height:100%;object-fit:cover;">`;
            } else if (currentTarget === 'download') {
                document.getElementById('download-file').value = filename;
            } else {
                insertTag(`\n![Bildbeschreibung](/uploads/${filename})\n`, '');
            }
            closeMediaModal();
        }

        /* --- SMILEY PICKER LOGIC --- */
        const smileys = [
            "😀","😃","😄","😁","😆","😅","😂","🤣","😊","😇","🙂","🙃","😉","😌","😍","🥰","😘","😗","😙","😚","😋","😛","😝","😜","🤪","🤨","🧐","🤓","😎","🤩","🥳","😏","😒","😞","😔","😟","😕","🙁","☹️","😣","😖","😫","😩","🥺","😢","😭","😤","😠","😡","🤬","🤯","😳","🥵","🥶","😱","😨","😰","😥","😓","🤗","🤔","🤭","🤫","🤥","😶","😐","😑","😬","🙄","😯","😦","😧","😮","😲","🥱","😴","🤤","😪","😵","🤐","🥴","🤢","🤮","🤧","😷","🤒","🤕","🤑","🤠","😈","👿","👹","👺","🤡","💩","👻","💀","👽","👾","🤖","🎃","😺","😸","😹","😻","😼","😽","🙀","😿","😾","🤲","👐","🙌","👏","🤝","👍","👎","👊","✊","🤛","🤜","🤞","✌️","🤟","🤘","👌","🤏","👈","👉","👆","👇","☝️","✋","🤚","🖐","🖖","👋","🤙","💪","🧠","🦷","🦴","👀","👁","👄","💋","🦶","🦵","👃","👂","🦻","👣","🔥","💥","✨","🌟","💫","❤️","🧡","💛","💚","💙","💜","🖤","🤍","🤎","💔","❣️","💕","💞","💓","💗","💖","💘","💝","💟"
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
                // Positionierung
                pop.style.top = (btn.offsetTop + btn.offsetHeight + 5) + 'px';
                pop.style.left = btn.offsetLeft + 'px';
            }
        }
        
        // Klick außerhalb schließt Picker
        document.addEventListener('click', function(e) {
            const pop = document.getElementById('smileyPopover');
            const btn = document.querySelector('button[title="Emojis"]');
            if(pop.style.display === 'block' && !pop.contains(e.target) && e.target !== btn) {
                pop.style.display = 'none';
            }
        });

        /* --- ICON PICKER LOGIC --- */
        // Eine Auswahl von ~200 nützlichen FA Icons
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

        function openIconModal() {
            document.getElementById('iconModal').style.display = 'flex';
            renderIcons(); // Liste rendern
            document.getElementById('iconSearch').focus();
        }
        function closeIconModal() { document.getElementById('iconModal').style.display = 'none'; }
        
        function filterIcons() {
            const val = document.getElementById('iconSearch').value;
            renderIcons(val);
        }

        /* --- SAVE LOGIC (Original) --- */
        document.getElementById('save-btn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerText = 'Speichere...';

            const payload = {
                id: <?= $id ?>,
                title: document.getElementById('post-title').value,
                excerpt: document.getElementById('post-excerpt').value, // Auszug
                content: input.value,
                hero_image: document.getElementById('hero-image').value,
                download_file: document.getElementById('download-file').value,
                category_id: document.getElementById('post-category').value,
                status: document.getElementById('post-status').value,
                is_sticky: document.getElementById('post-sticky').checked ? 1 : 0
            };

            fetch('save-post-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'ok') {
                    btn.innerText = '✅ Gespeichert';
                    setTimeout(() => { btn.innerText = 'Speichern'; btn.disabled = false; }, 1500);
                } else { alert('Fehler: ' + data.error); btn.disabled = false; }
            })
            .catch(err => { alert('Netzwerkfehler'); btn.disabled = false; });
        });
    </script>
</body>
</html>