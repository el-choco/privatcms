<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$peLang = $t['post_edit'] ?? []; 
$pgLang = $t['pages'] ?? [];

$uploadDir = __DIR__ . '/../public/uploads/';
$allFiles = is_dir($uploadDir) ? array_diff(scandir($uploadDir), ['.', '..']) : [];
usort($allFiles, function($a, $b) use ($uploadDir) {
    return filemtime($uploadDir . $b) - filemtime($uploadDir . $a);
});

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = ['title' => '', 'slug' => '', 'content' => '', 'status' => 'draft', 'show_in_header' => 0, 'show_in_footer' => 0];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch();
    if ($fetched) $page = $fetched;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    if (!$slug) $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $title));
    $content = $_POST['content'];
    $status = $_POST['status'];
    $showHeader = isset($_POST['show_in_header']) ? 1 : 0;
    $showFooter = isset($_POST['show_in_footer']) ? 1 : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE pages SET title=?, slug=?, content=?, status=?, show_in_header=?, show_in_footer=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$title, $slug, $content, $status, $showHeader, $showFooter, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pages (title, slug, content, status, show_in_header, show_in_footer) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $slug, $content, $status, $showHeader, $showFooter]);
        $id = $pdo->lastInsertId();
    }
    header("Location: pages.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($peLang['title_prefix'] ?? 'Editor') ?> - <?= htmlspecialchars($page['title']) ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="/admin/assets/styles/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .editor-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; height: calc(100vh - 120px); }
        .editor-main-card { display: flex; flex-direction: column; background: #fff; border-radius: 8px; border: 1px solid #ddd; overflow: hidden; }
        .sidebar-card { background: #fff; border-radius: 8px; border: 1px solid #ddd; padding: 20px; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; }
        #page-title { width: 100%; padding: 15px; font-size: 18px; font-weight: bold; border: none; border-bottom: 1px solid #eee; outline: none; box-sizing: border-box; background: #fff; }
        .form-label { font-weight: bold; font-size: 12px; display: block; margin-bottom: 5px; color: #4a5568; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .checkbox-group label { cursor: pointer; font-size: 13px; color: #2d3748; }
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
            <a href="/admin/pages.php"><?= htmlspecialchars($peLang['back_btn'] ?? 'Back') ?></a>
            <div style="margin-top: 20px; padding: 0 15px;">
                <button type="button" onclick="document.getElementById('pageForm').submit()" class="btn btn-primary" style="width: 100%;">
                    <?= htmlspecialchars($peLang['save_btn'] ?? 'Save') ?>
                </button>
            </div>
        </nav>
    </aside>

    <main class="admin-content">
        <form method="POST" id="pageForm" class="editor-layout">
            
            <div class="editor-main-card">
                <input type="text" id="page-title" name="title" value="<?= htmlspecialchars($page['title']) ?>" placeholder="<?= htmlspecialchars($t['common']['title'] ?? 'Title') ?>" required>

                <?php 
                    $editorContent = $page['content'];
                    $editorUploadUrl = 'upload-handler.php';
                    include __DIR__ . '/../public/templates/editor.php'; 
                ?>
            </div>

            <div class="sidebar-card">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($peLang['label_status'] ?? 'Status') ?></label>
                    <select name="status" class="form-control">
                        <option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($peLang['status_draft'] ?? 'Draft') ?>
                        </option>
                        <option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($peLang['status_published'] ?? 'Published') ?>
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Slug (URL)</label>
                    <input type="text" id="page-slug" name="slug" class="form-control" value="<?= htmlspecialchars($page['slug']) ?>">
                    <div style="text-align: right; margin-top: 2px;">
                        <small style="color:#1877f2; font-size:11px; cursor:pointer; text-decoration:underline;" onclick="generateSlugFromTitle()">
                            <?= htmlspecialchars($peLang['btn_gen_slug'] ?? 'Generate') ?>
                        </small>
                    </div>
                </div>

                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">

                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($pgLang['label_placement'] ?? 'Placement') ?></label>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="chk_header" name="show_in_header" value="1" <?= (!empty($page['show_in_header'])) ? 'checked' : '' ?>>
                        <label for="chk_header"><?= htmlspecialchars($pgLang['opt_header'] ?? 'Header') ?></label>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="chk_footer" name="show_in_footer" value="1" <?= (!empty($page['show_in_footer'])) ? 'checked' : '' ?>>
                        <label for="chk_footer"><?= htmlspecialchars($pgLang['opt_footer'] ?? 'Footer') ?></label>
                    </div>
                </div>
            </div>

        </form>
    </main>

<script>
    function generateSlugFromTitle() {
        const title = document.getElementById('page-title').value;
        const slug = title.toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss').replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-');
        document.getElementById('page-slug').value = slug;
    }

    document.getElementById('page-slug').addEventListener('input', function(e) {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
    });
</script>
</body>
</html>