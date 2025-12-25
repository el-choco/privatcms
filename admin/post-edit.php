<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$id = (int)($_GET['id'] ?? 0);

$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) { die("Beitrag nicht gefunden."); }

include 'header.php';
?>

<header class="top-header">
    <div style="display: flex; align-items: center; gap: 15px;">
        <a href="posts.php" class="btn" style="text-decoration: none; padding: 8px 15px; background: #edf2f7; color: #4a5568;">← Zurück</a>
        <h1 style="margin:0; font-size: 1.25rem;">Pro-Editor</h1>
    </div>
    <div style="display: flex; align-items: center; gap: 20px;">
        <div id="save-status" style="font-size: 13px; color: #718096; font-style: italic;"></div>
        <button id="save-btn" class="btn btn-primary" style="padding: 10px 25px; font-weight: bold; min-width: 180px;">💾 Beibehalten & Sichern</button>
    </div>
</header>

<div class="content-area" style="width: 1400px; margin: 0 auto;">
    <div class="card" style="margin-bottom: 15px; padding: 20px; border-left: 5px solid #3182ce; display: flex; gap: 20px; align-items: flex-end;">
        <div style="flex: 1;">
            <label style="display: block; font-size: 11px; font-weight: 800; color: #a0aec0; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Beitragstitel</label>
            <input type="text" id="post-title" value="<?= htmlspecialchars($data['title']) ?>" 
                   style="width: 100%; font-size: 1.8rem; font-weight: 700; border: none; outline: none; color: #2d3748; background: transparent;"
                   placeholder="Titel hier eingeben...">
        </div>
        
        <div style="width: 250px;">
            <label style="display: block; font-size: 11px; font-weight: 800; color: #a0aec0; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Kategorie</label>
            <select id="post-category" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; font-weight: 600; color: #4a5568;">
                <option value="">Keine Kategorie</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($data['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="card" style="margin-bottom: 15px; padding: 15px; border-left: 5px solid #1877f2; display: flex; align-items: center; gap: 20px;">
        <div id="image-preview-container" style="width: 120px; height: 80px; background: #f0f2f5; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0; flex-shrink: 0;">
            <?php if (!empty($data['hero_image'])): ?>
                <img src="/uploads/images/<?= htmlspecialchars($data['hero_image']) ?>" id="current-hero-preview" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <span style="color: #a0aec0; font-size: 10px; text-align: center; padding: 5px;">Kein Bild</span>
            <?php endif; ?>
        </div>
        
        <div style="flex: 1;">
            <label style="display: block; font-size: 11px; font-weight: 800; color: #a0aec0; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Vorschaubild (Thumb)</label>
            <input type="file" id="hero-upload" accept="image/*" style="font-size: 13px;">
            <input type="hidden" id="hero-image-name" value="<?= htmlspecialchars($data['hero_image'] ?? '') ?>">
        </div>
    </div>

    <div class="card" style="margin-bottom: 15px; padding: 15px; border-left: 5px solid #718096;">
        <label style="display: block; font-size: 11px; font-weight: 800; color: #a0aec0; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Kurztext (Vorschau auf Startseite)</label>
        <textarea id="post-excerpt" rows="3" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; font-family: inherit; resize: vertical; outline: none;"><?= htmlspecialchars($data['excerpt'] ?? '') ?></textarea>
    </div>

    <div class="card" style="margin-bottom: 10px; padding: 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; display: flex; flex-direction: column; gap: 10px;">
        <div class="toolbar-row">
            <span class="toolbar-label">HTML</span>
            <button class="tool-btn" onclick="format('<b>', '</b>')">b</button>
            <button class="tool-btn" onclick="format('<i>', '</i>')">i</button>
            <button class="tool-btn" onclick="format('<u>', '</u>')">u</button>
            <button class="tool-btn" onclick="format('<center>', '</center>')">center</button>
            <button class="tool-btn" onclick="format('<a href=\'\'>', '</a>')">link</button>
            <button class="tool-btn" onclick="format('<img src=\'\' alt=\'\'>', '')">img</button>
            <button class="tool-btn" onclick="format('<blockquote>', '</blockquote>')">quote</button>
            <button class="tool-btn" onclick="format('<ul>\n<li>', '</li>\n</ul>')">ul</button>
            <button class="tool-btn" onclick="format('<li>', '</li>')">li</button>
            <button class="tool-btn" onclick="format('<p>', '</p>')">p</button>
            <button class="tool-btn" onclick="format('<div>', '</div>')">div</button>
            <button class="tool-btn btn-br" onclick="format('<br>', '')" title="Line Break">&lt;br&gt;</button>
        </div>
        
        <div class="toolbar-row">
            <span class="toolbar-label">MARKUP</span>
            <button class="tool-btn" onclick="format('**', '**')"><strong>B</strong></button>
            <button class="tool-btn" onclick="format('*', '*')"><em>I</em></button>
            <button class="tool-btn" onclick="format('# ', '')">H1</button>
            <button class="tool-btn" onclick="format('## ', '')">H2</button>
            <button class="tool-btn" onclick="format('- ', '')">List</button>
            <button class="tool-btn" onclick="insertTable()">📋 Tabelle</button>
            <button class="tool-btn" onclick="format('`', '`')">Code</button>
            
            <div style="margin-left: auto; display: flex; background: #e2e8f0; padding: 2px; border-radius: 4px; gap: 2px;">
                <button id="view-preview-btn" class="toggle-btn active" onclick="setView('preview')">👁️ Vorschau</button>
                <button id="view-html-btn" class="toggle-btn" onclick="setView('html')">⚡ HTML</button>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; height: calc(100vh - 450px); min-height: 400px;">
        <div class="card" style="padding: 0; display: flex; flex-direction: column;">
            <div class="view-header">MARKDOWN EDITOR</div>
            <textarea id="markdown-editor" class="main-editor"><?= htmlspecialchars($data['content']) ?></textarea>
        </div>
        
        <div class="card" style="padding: 0; display: flex; flex-direction: column; overflow: hidden;">
            <div id="preview-label" class="view-header">LIVE-VORSCHAU</div>
            <div id="preview" class="prose" style="flex: 1; padding: 25px; overflow-y: auto; background: #fff;"></div>
            <textarea id="html-picker" readonly class="html-view" style="display: none;"></textarea>
        </div>
    </div>
</div>

<div id="toast">Beitrag erfolgreich gespeichert!</div>

<style>
    .toolbar-row { display: flex; gap: 5px; align-items: center; flex-wrap: wrap; }
    .toolbar-label { font-size: 9px; font-weight: 800; color: #cbd5e1; width: 50px; text-transform: uppercase; }
    .tool-btn { background: white; border: 1px solid #cbd5e1; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #4a5568; min-width: 45px; height: 32px; display: flex; align-items: center; justify-content: center; }
    .tool-btn:hover { background: #3182ce; color: white; border-color: #3182ce; }
    .btn-br { color: #e53e3e; font-weight: bold; border-color: #feb2b2; background: #fff5f5; }
    .toggle-btn { border: none; padding: 4px 12px; font-size: 10px; font-weight: bold; border-radius: 3px; cursor: pointer; background: transparent; color: #718096; height: 28px; }
    .toggle-btn.active { background: white; color: #3182ce; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .view-header { padding: 6px 15px; background: #edf2f7; font-size: 10px; font-weight: bold; color: #718096; border-bottom: 1px solid #e2e8f0; }
    .main-editor { flex: 1; width: 100%; padding: 20px; border: none; font-family: 'Fira Code', monospace; font-size: 15px; line-height: 1.6; resize: none; outline: none; }
    .html-view { flex: 1; width: 100%; padding: 20px; border: none; font-family: monospace; font-size: 13px; background: #f8fafc; outline: none; resize: none; }
    #toast { visibility: hidden; min-width: 250px; background-color: #38a169; color: white; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 1000; left: 50%; bottom: 30px; transform: translateX(-50%); font-weight: bold; }
</style>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
    const editor = document.getElementById('markdown-editor');
    const preview = document.getElementById('preview');
    const htmlPicker = document.getElementById('html-picker');
    const titleInput = document.getElementById('post-title');
    const excerptInput = document.getElementById('post-excerpt');
    const categoryInput = document.getElementById('post-category');
    const heroUpload = document.getElementById('hero-upload');
    const heroImageName = document.getElementById('hero-image-name');
    const previewContainer = document.getElementById('image-preview-container');
    const saveBtn = document.getElementById('save-btn');

    function setView(mode) {
        const isPreview = mode === 'preview';
        preview.style.display = isPreview ? 'block' : 'none';
        htmlPicker.style.display = isPreview ? 'none' : 'block';
        document.getElementById('view-preview-btn').classList.toggle('active', isPreview);
        document.getElementById('view-html-btn').classList.toggle('active', !isPreview);
    }

    function updatePreview() {
        const html = marked.parse(editor.value);
        preview.innerHTML = html;
        htmlPicker.value = html;
    }
    editor.addEventListener('input', updatePreview);
    window.addEventListener('DOMContentLoaded', updatePreview);

    function format(prefix, suffix) {
        const start = editor.selectionStart;
        const end = editor.selectionEnd;
        const scrollPos = editor.scrollTop;
        editor.value = editor.value.substring(0, start) + prefix + editor.value.substring(start, end) + suffix + editor.value.substring(end);
        editor.focus();
        const newPos = start + prefix.length + (end - start);
        editor.setSelectionRange(newPos, newPos);
        editor.scrollTop = scrollPos;
        updatePreview();
    }

    function insertTable() {
        format("\n| Kopf | Kopf |\n|---|---|\n| Text | Text |\n", "");
    }

    // Bild-Upload Logik
    heroUpload.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);

        fetch('upload-handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                heroImageName.value = result.filename;
                previewContainer.innerHTML = `<img src="/uploads/images/${result.filename}" style="width: 100%; height: 100%; object-fit: cover;">`;
            } else {
                alert("Fehler: " + result.message);
            }
        });
    });

    saveBtn.addEventListener('click', () => {
        saveBtn.innerText = "⏳ Speichere...";
        saveBtn.disabled = true;
        fetch('save-post-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: <?= $id ?>,
                title: titleInput.value,
                excerpt: excerptInput.value,
                content: editor.value,
                category_id: categoryInput.value,
                hero_image: heroImageName.value
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                const toast = document.getElementById('toast');
                toast.style.visibility = "visible";
                setTimeout(() => { toast.style.visibility = "hidden"; }, 3000);
                document.getElementById('save-status').innerText = "Gesichert: " + new Date().toLocaleTimeString();
            }
        })
        .finally(() => {
            saveBtn.innerText = "💾 Beibehalten & Sichern";
            saveBtn.disabled = false;
        });
    });
</script>
<?php include 'footer.php'; ?>