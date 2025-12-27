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
    <style>
        .editor-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; height: calc(100vh - 120px); }
        .editor-main-card { display: flex; flex-direction: column; background: #fff; border-radius: 8px; border: 1px solid #ddd; overflow: hidden; }
        .sidebar-card { background: #fff; border-radius: 8px; border: 1px solid #ddd; padding: 20px; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; }
        .toolbar { background: #f8fafc; padding: 10px; border-bottom: 1px solid #ddd; display: flex; gap: 8px; }
        .editor-split { display: flex; flex: 1; overflow: hidden; }
        .editor-area, .preview-area { flex: 1; padding: 15px; overflow-y: auto; }
        .editor-area { border-right: 1px solid #ddd; }
        textarea { width: 100%; height: 100%; border: none; outline: none; font-family: monospace; resize: none; font-size: 14px; }
        #mediaModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 20px; border-radius: 12px; width: 80%; max-width: 900px; max-height: 80vh; overflow-y: auto; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px; margin-top: 15px; }
        .media-item { cursor: pointer; border: 2px solid transparent; border-radius: 6px; overflow: hidden; transition: 0.2s; text-align: center; background: #f8fafc; padding: 5px; }
        .media-item:hover { border-color: #3182ce; transform: scale(1.05); }
        .media-item img { width: 100%; height: 100px; object-fit: cover; display: block; border-radius: 4px; }
        .media-item .file-icon { font-size: 3rem; line-height: 100px; height: 100px; }
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
                <div class="toolbar">
                    <button class="btn btn-sm" onclick="wrap('**','**')"><b>B</b></button>
                    <button class="btn btn-sm" onclick="wrap('*','*')"><i>I</i></button>
                    <button class="btn btn-sm" onclick="insert('### ')">H3</button>
                    <button class="btn btn-sm" onclick="openMediaModal('content')">🖼️ Bild einfügen</button>
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
                    <label style="font-weight: bold; font-size: 12px;">Titel</label>
                    <input type="text" id="post-title" class="input" value="<?= htmlspecialchars($data['title']) ?>">
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

    <script>
        const input = document.getElementById('markdown-input');
        const preview = document.getElementById('preview-box');
        let currentTarget = 'hero'; 

        function updatePreview() { preview.innerHTML = marked.parse(input.value); }
        input.addEventListener('input', updatePreview);
        updatePreview();

        function wrap(before, after) {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = input.value.substring(0, start) + before + input.value.substring(start, end) + after + input.value.substring(end);
            updatePreview();
        }

        function insert(str) {
            const pos = input.selectionStart;
            input.value = input.value.substring(0, pos) + str + input.value.substring(pos);
            updatePreview();
        }

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
                insert(`\n![Bildbeschreibung](/uploads/${filename})\n`);
            }
            closeMediaModal();
        }

        document.getElementById('save-btn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerText = 'Speichere...';

            const payload = {
                id: <?= $id ?>,
                title: document.getElementById('post-title').value,
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