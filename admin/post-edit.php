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
$peLang = $t_temp['post_edit'] ?? [];
$eLang = $t_temp['editor'] ?? []; 

$currentUser = $_SESSION['admin'];
$isAdmin = ($currentUser['role'] ?? 'viewer') === 'admin';
$currentUserId = (int)$currentUser['id'];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) { die($peLang['error_not_found'] ?? 'Post not found.'); }

$isOwner = (int)($data['author_id'] ?? 0) === $currentUserId;
if (!$isAdmin && !$isOwner) {
    header('Location: posts.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$tagsStmt = $pdo->prepare("SELECT t.name FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ?");
$tagsStmt->execute([$id]);
$currentTags = implode(', ', $tagsStmt->fetchAll(PDO::FETCH_COLUMN));

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
    <title><?= htmlspecialchars($peLang['title_prefix'] ?? 'Editor -') ?> <?= htmlspecialchars($data['title']) ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="/admin/assets/styles/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/dockerfile.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .editor-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; height: calc(100vh - 120px); }
        .editor-main-card { display: flex; flex-direction: column; background: #fff; border-radius: 8px; border: 1px solid #ddd; overflow: hidden; }
        .sidebar-card { background: #fff; border-radius: 8px; border: 1px solid #ddd; padding: 20px; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; }
        #post-title { width: 100%; padding: 15px; font-size: 18px; font-weight: 700; border: none; border-bottom: 1px solid #eee; outline: none; box-sizing: border-box; background: #fff; }
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
            <a href="/admin/posts.php"><?= htmlspecialchars($peLang['back_btn'] ?? 'Back') ?></a>
            <div style="margin-top: 20px; padding: 0 15px;">
                <button id="save-btn" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars($peLang['save_btn'] ?? 'Save') ?></button>
            </div>
        </nav>
    </aside>

    <main class="admin-content">
        <div class="editor-layout">
            <div class="editor-main-card">
                <input type="text" id="post-title" value="<?= htmlspecialchars($data['title']) ?>" placeholder="<?= htmlspecialchars($peLang['title_placeholder'] ?? '') ?>">

                <?php 
                    $editorContent = $data['content'];
                    $editorUploadUrl = 'upload-handler.php';
                    include __DIR__ . '/../public/templates/editor.php'; 
                ?>
            </div>

            <div class="sidebar-card">
                <div>
                    <label style="font-weight: bold; font-size: 12px;"><?= htmlspecialchars($peLang['label_status'] ?? 'Status') ?></label>
                    <select id="post-status" class="input">
                        <option value="draft" <?= $data['status'] === 'draft' ? 'selected' : '' ?>><?= htmlspecialchars($peLang['status_draft'] ?? 'Draft') ?></option>
                        <option value="published" <?= $data['status'] === 'published' ? 'selected' : '' ?>><?= htmlspecialchars($peLang['status_published'] ?? 'Published') ?></option>
                    </select>
                </div>

                <div style="margin-top: 15px;">
                    <label style="font-weight: bold; font-size: 12px;"><?= htmlspecialchars($peLang['label_slug'] ?? 'SEO URL (Slug)') ?></label>
                    <input type="text" id="post-slug" class="input" value="<?= htmlspecialchars($data['slug'] ?? '') ?>" placeholder="<?= htmlspecialchars($peLang['placeholder_slug'] ?? 'my-article-slug') ?>">
                    <div style="text-align: right; margin-top: 2px;">
                        <small style="color:#1877f2; font-size:11px; cursor:pointer; text-decoration:underline;" onclick="generateSlugFromTitle()">
                            <?= htmlspecialchars($peLang['btn_gen_slug'] ?? 'Generate') ?>
                        </small>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                    <input type="checkbox" id="post-sticky" <?= ($data['is_sticky'] ?? 0) ? 'checked' : '' ?>>
                    <label for="post-sticky" style="font-weight: bold; font-size: 12px; cursor: pointer;"><?= htmlspecialchars($peLang['label_sticky'] ?? 'Pin post') ?></label>
                </div>

                <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                    <label style="font-weight: bold; font-size: 12px;"><?= htmlspecialchars($peLang['label_excerpt'] ?? 'Excerpt') ?></label>
                    <small style="display:block; color:#666; margin-bottom:5px;"><?= htmlspecialchars($peLang['hint_excerpt'] ?? '') ?></small>
                    <textarea id="post-excerpt" class="input" rows="4" style="resize:vertical; min-height:80px; font-family:inherit; width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px;"><?= htmlspecialchars($data['excerpt'] ?? '') ?></textarea>
                </div>

                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">

                <div>
                    <label style="font-weight: bold; font-size: 12px;"><?= htmlspecialchars($peLang['label_category'] ?? 'Category') ?></label>
                    <select id="post-category" class="input">
                        <option value=""><?= htmlspecialchars($peLang['opt_no_category'] ?? 'No Category') ?></option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $data['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top: 15px;">
                    <label style="font-weight: bold; font-size: 12px;"><?= htmlspecialchars($peLang['label_tags'] ?? 'Tags') ?></label>
                    <small style="display:block; color:#666; margin-bottom:5px;"><?= htmlspecialchars($peLang['hint_tags'] ?? 'Comma separated') ?></small>
                    <input type="text" id="post-tags" class="input" value="<?= htmlspecialchars($currentTags) ?>" placeholder="<?= htmlspecialchars($peLang['placeholder_tags'] ?? 'Linux, Docker...') ?>">
                </div>

                <div>
                    <label style="font-weight: bold; font-size: 12px; margin-top:15px; display:block;"><?= htmlspecialchars($peLang['label_hero'] ?? 'Hero Image') ?></label>
                    <div style="display: flex; gap: 5px; margin-top: 5px;">
                        <input type="text" id="hero-image" class="input" value="<?= htmlspecialchars($data['hero_image'] ?? '') ?>" placeholder="image.jpg">
                        <button class="btn" onclick="window.mediaTarget='hero'; document.getElementById('mediaModal').style.display='flex'"> <?= htmlspecialchars($peLang['btn_select'] ?? 'Select') ?> </button>
                    </div>
                    <div id="hero-preview" style="margin-top: 10px; height: 100px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if($data['hero_image']): ?>
                            <img src="/uploads/<?= htmlspecialchars($data['hero_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 12px;"><?= htmlspecialchars($peLang['no_image'] ?? 'No Image') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label style="font-weight: bold; font-size: 12px; margin-top:15px; display:block;"><?= htmlspecialchars($peLang['label_download'] ?? 'Download File') ?></label>
                    <div style="display: flex; gap: 5px; margin-top: 5px;">
                        <input type="text" id="download-file" class="input" value="<?= htmlspecialchars($data['download_file'] ?? '') ?>" placeholder="file.zip">
                        <button class="btn" onclick="window.mediaTarget='download'; document.getElementById('mediaModal').style.display='flex'"> <?= htmlspecialchars($peLang['btn_select'] ?? 'Select') ?> </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        window.mediaTarget = 'content';

        window.selectImage = function(filename) {
            if (window.mediaTarget === 'hero') {
                document.getElementById('hero-image').value = filename;
                document.getElementById('hero-preview').innerHTML = `<img src="/uploads/${filename}" style="width:100%;height:100%;object-fit:cover;">`;
            } else if (window.mediaTarget === 'download') {
                document.getElementById('download-file').value = filename;
            } else {
                insertTag(`\n![Image](/uploads/${filename})\n`, '');
            }
            document.getElementById('mediaModal').style.display = 'none';
            window.mediaTarget = 'content';
        };

        
        function generateSlugFromTitle() {
            const title = document.getElementById('post-title').value;
            const slug = title.toLowerCase()
                .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                .replace(/[^a-z0-9\s-]/g, '') 
                .trim()
                .replace(/\s+/g, '-'); 
            document.getElementById('post-slug').value = slug;
        }

        document.getElementById('post-slug').addEventListener('input', function(e) {
            this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        });

        document.getElementById('save-btn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerText = "<?= htmlspecialchars($peLang['saving'] ?? 'Saving...') ?>";

            const payload = {
                id: <?= $id ?>,
                title: document.getElementById('post-title').value,
                slug: document.getElementById('post-slug').value,
                excerpt: document.getElementById('post-excerpt').value,
                content: document.getElementById('markdown-input').value, // ID from template
                hero_image: document.getElementById('hero-image').value,
                download_file: document.getElementById('download-file').value,
                category_id: document.getElementById('post-category').value,
                status: document.getElementById('post-status').value,
                is_sticky: document.getElementById('post-sticky').checked ? 1 : 0,
                tags: document.getElementById('post-tags').value
            };

            fetch('save-post-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'ok') {
                    btn.innerText = "<?= htmlspecialchars($peLang['saved'] ?? 'Saved') ?>";
                    setTimeout(() => { btn.innerText = "<?= htmlspecialchars($peLang['save_btn'] ?? 'Save') ?>"; btn.disabled = false; }, 1500);
                } else { 
                    alert("<?= htmlspecialchars($peLang['error_prefix'] ?? 'Error: ') ?>" + data.error); 
                    btn.disabled = false; 
                }
            })
            .catch(err => { 
                alert("<?= htmlspecialchars($peLang['error_network'] ?? 'Network Error') ?>"); 
                btn.disabled = false; 
            });
        });
    </script>
</body>
</html>