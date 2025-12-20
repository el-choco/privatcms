<?php
// admin/post-edit.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$i18n = I18n::fromConfig($ini, $_GET['lang'] ?? null);
$pdo = (new Database($ini['database'] ?? []))->pdo();

$id = (int)($_GET['id'] ?? 0);
$post = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$post->execute([$id]);
$data = $post->fetch();

if (!$data) { die("Beitrag nicht gefunden."); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Editor - <?= htmlspecialchars($data['title']) ?></title>
    <link href="/admin/assets/styles/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .editor-container { display: flex; flex-direction: column; height: calc(100vh - 160px); border: 1px solid #ddd; border-radius: 8px; background: #fff; margin-top: 10px; }
        .toolbar { background: #f1f1f1; padding: 10px; border-bottom: 1px solid #ddd; display: flex; gap: 8px; align-items: center; }
        .toolbar-btn { padding: 5px 12px; cursor: pointer; border: 1px solid #ccc; background: #fff; border-radius: 4px; font-weight: bold; transition: 0.2s; }
        .toolbar-btn:hover { background: #e9ecef; }
        
        .editor-main { display: flex; flex: 1; overflow: hidden; }
        .editor-area, .preview-area { flex: 1; padding: 20px; overflow-y: auto; }
        .editor-area { border-right: 1px solid #ddd; }
        textarea { width: 100%; height: 100%; border: none; outline: none; font-family: 'Courier New', monospace; font-size: 1.05rem; resize: none; line-height: 1.5; }
        
        /* Split-Screen Steuerung */
        .hidden { display: none; }
        .full-width { width: 100% !important; border: none !important; flex: 1 0 100% !important; }
        
        /* Preview Styling */
        .preview-area { background: #fdfdfd; line-height: 1.6; color: #333; }
        .preview-area h1, .preview-area h2 { border-bottom: 1px solid #eee; padding-bottom: 5px; }
        
        /* Toast Nachricht */
        #toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #27ae60;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 16px;
            position: fixed;
            z-index: 1000;
            right: 30px;
            bottom: 30px;
            font-size: 17px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }
    </style>
</head>
<body class="admin-layout">
<aside class="admin-sidebar">
    <h2 class="brand">Admin</h2>
    <nav>
        <a href="/admin/dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="/admin/posts.php" class="<?= basename($_SERVER['PHP_SELF']) == 'posts.php' ? 'active' : '' ?>">Beiträge</a>
        <a href="/admin/comments.php">Kommentare</a>
        <a href="/admin/files.php">Dateien</a>
        <a href="/admin/categories.php">Kategorien</a>
        <a href="/admin/settings.php">Einstellungen</a>
        <a href="/admin/logout.php">Abmelden</a>
    </nav>
</aside>
    <main class="admin-content">
        <div class="topbar">
            <h1 style="margin:0">Editor: <?= htmlspecialchars($data['title']) ?></h1>
            <div style="display:flex; gap:5px;">
                <button type="button" class="btn btn-sm" onclick="setMode('edit')">Nur Editor</button>
                <button type="button" class="btn btn-sm" onclick="setMode('split')">Split-View</button>
                <button type="button" class="btn btn-sm" onclick="setMode('preview')">Nur Vorschau</button>
            </div>
        </div>

        <div class="editor-container">
            <div class="toolbar">
                <button type="button" class="toolbar-btn" onclick="wrap('**','**')" title="Fett">B</button>
                <button type="button" class="toolbar-btn" onclick="wrap('*','*')" title="Kursiv">I</button>
                <button type="button" class="toolbar-btn" onclick="insert('### ')" title="Überschrift">H3</button>
                <button type="button" class="toolbar-btn" onclick="wrap('[Link-Text](', ')')" title="Link">Link</button>
                <button type="button" class="toolbar-btn" onclick="wrap('<code>', '</code>')" title="HTML Code">Code</button>
                <button type="button" class="toolbar-btn" onclick="wrap('<div class=\'alert\'>', '</div>')" title="Hinweisbox">Alert</button>
                
                <button type="button" id="save-btn" class="btn" style="margin-left: auto; background: #27ae60; color: white; border: none; padding: 6px 20px;">
                    Speichern
                </button>
            </div>

            <div class="editor-main">
                <div class="editor-area" id="edit-box">
                    <textarea id="markdown-input" name="content" spellcheck="false" placeholder="Schreibe etwas Wunderbares..."><?= htmlspecialchars($data['content']) ?></textarea>
                </div>
                <div class="preview-area" id="preview-box">
                    <div id="render-target" class="article-body"></div>
                </div>
            </div>
        </div>
    </main>

    <div id="toast">Änderungen erfolgreich gespeichert!</div>

    <script>
        const input = document.getElementById('markdown-input');
        const target = document.getElementById('render-target');
        const editBox = document.getElementById('edit-box');
        const previewBox = document.getElementById('preview-box');
        const saveBtn = document.getElementById('save-btn');

        // Markdown Live-Vorschau
        function updatePreview() {
            target.innerHTML = marked.parse(input.value);
        }
        input.addEventListener('input', updatePreview);

        // Ansichts-Modus wechseln
        function setMode(mode) {
            if(mode === 'edit') {
                editBox.classList.remove('hidden', 'full-width');
                editBox.classList.add('full-width');
                previewBox.classList.add('hidden');
            } else if(mode === 'preview') {
                editBox.classList.add('hidden');
                previewBox.classList.remove('hidden', 'full-width');
                previewBox.classList.add('full-width');
            } else {
                editBox.classList.remove('hidden', 'full-width');
                previewBox.classList.remove('hidden', 'full-width');
            }
        }

        // Toolbar Funktionen
        function wrap(before, after) {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value;
            input.value = text.substring(0, start) + before + text.substring(start, end) + after + text.substring(end);
            input.focus();
            input.setSelectionRange(start + before.length, end + before.length);
            updatePreview();
        }

        function insert(str) {
            const pos = input.selectionStart;
            input.value = input.value.substring(0, pos) + str + input.value.substring(pos);
            input.focus();
            updatePreview();
        }

        // Toast anzeigen
        function showToast() {
            const x = document.getElementById("toast");
            x.className = "show";
            setTimeout(function(){ x.className = x.className.replace("show", ""); }, 3000);
        }

        // AJAX Speicherung
        saveBtn.addEventListener('click', function() {
            const content = input.value;
            const postId = new URLSearchParams(window.location.search).get('id');

            saveBtn.disabled = true;
            saveBtn.innerText = 'Speichere...';

            fetch('save-post-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: postId, content: content })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'ok') {
                    showToast();
                } else {
                    alert('Fehler beim Speichern');
                }
            })
            .catch(err => alert('Netzwerkfehler: ' + err))
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerText = 'Speichern';
            });
        });

        // Initialer Render
        updatePreview();
        // Standardmäßig im Split-Modus starten
        setMode('split');
    </script>
</body>
</html>