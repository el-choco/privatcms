<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$settings = [];
try {
    $stmtSettings = $pdo->query("SELECT * FROM settings");
    while ($row = $stmtSettings->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin = false;

if ($isLoggedIn) {
    $stmtP = $pdo->prepare("SELECT p.slug FROM permissions p JOIN permission_role pr ON p.id = pr.permission_id JOIN users u ON pr.role_id = u.role_id WHERE u.id = ?");
    $stmtP->execute([$_SESSION['user_id']]);
    $perms = $stmtP->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('wiki_manage', $perms) || !empty($_SESSION['admin'])) {
        $isAdmin = true;
    }
}

if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['ajax_search']);
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    
    $stmt = $pdo->prepare("SELECT title, slug FROM wiki_pages WHERE title LIKE ? OR content LIKE ? ORDER BY title ASC LIMIT 20");
    $stmt->execute(["%$q%", "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    
    if (isset($jsonInput['action']) && $jsonInput['action'] === 'update_order') {
        header('Content-Type: application/json');
        
        if (!$isAdmin) { 
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); 
            exit; 
        }
        
        if (isset($jsonInput['order']) && is_array($jsonInput['order'])) {
            $stmtOrder = $pdo->prepare("UPDATE wiki_pages SET sort_order = ? WHERE slug = ?");
            foreach ($jsonInput['order'] as $index => $slug) {
                $stmtOrder->execute([$index, $slug]);
            }
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        }
        exit;
    }
}

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $params = $_GET; unset($params['lang']);
    $queryString = http_build_query($params);
    header("Location: " . $_SERVER['PHP_SELF'] . ($queryString ? '?' . $queryString : ''));
    exit;
}

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];

$wLang = $iniLang['wiki'] ?? [
    'title' => 'Wiki',
    'create_new' => 'New Page',
    'edit' => 'Edit',
    'save' => 'Save',
    'delete' => 'Delete',
    'confirm_delete' => 'Really delete?',
    'page_title' => 'Page Title',
    'content' => 'Content',
    'last_update' => 'Last updated:',
    'home' => 'Home',
    'not_found_title' => '404',
    'not_found_content' => 'Page not found.',
    'error_exists' => 'Error: Page might already exist.',
    'cancel' => 'Cancel',
    'search_placeholder' => 'Search...',
    'search_results' => 'Search Results',
    'no_results' => 'No results found.'
];

$peLang = array_merge($iniLang['editor'] ?? [], $wLang);

$msg = '';
$currentSlug = $_GET['page'] ?? 'home';
$action = $_GET['action'] ?? 'view';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST)) {
        if (!$isLoggedIn) { header('Location: /login.php'); exit; }

        if (isset($_POST['save_page'])) {
            $title = trim($_POST['title']);
            $content = $_POST['content'];
            $slugInput = trim($_POST['slug'] ?? '');
            
            if (empty($slugInput)) {
                $slugInput = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            }

            $check = $pdo->prepare("SELECT id FROM wiki_pages WHERE slug = ?");
            $check->execute([$slugInput]);
            $existing = $check->fetch();

            if ($existing && $action !== 'create' && $slugInput === $currentSlug) {
                $stmt = $pdo->prepare("UPDATE wiki_pages SET title = ?, content = ? WHERE slug = ?");
                $stmt->execute([$title, $content, $slugInput]);
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO wiki_pages (slug, title, content) VALUES (?, ?, ?)");
                    $stmt->execute([$slugInput, $title, $content]);
                    $currentSlug = $slugInput; 
                } catch (Exception $e) {
                    $msg = $wLang['error_exists'] ?? 'Error';
                }
            }
            header("Location: /wiki.php?page=" . $currentSlug);
            exit;
        }
        elseif (isset($_POST['delete_page'])) {
            $pdo->prepare("DELETE FROM wiki_pages WHERE slug = ?")->execute([$currentSlug]);
            header("Location: /wiki.php");
            exit;
        }
    }
}

$stmtList = $pdo->query("SELECT title, slug FROM wiki_pages ORDER BY sort_order ASC, title ASC");
$pages = $stmtList->fetchAll();

if ($action === 'create') {
    $pageData = []; 
} else {
    $stmtPage = $pdo->prepare("SELECT * FROM wiki_pages WHERE slug = ?");
    $stmtPage->execute([$currentSlug]);
    $pageData = $stmtPage->fetch();

    if (!$pageData) {
        $pageData = ['title' => $wLang['not_found_title'] ?? '404', 'content' => $wLang['not_found_content'] ?? 'Not Found', 'slug' => '404'];
    }
}

$uploadDir = __DIR__ . '/uploads/';
$allFiles = is_dir($uploadDir) ? array_diff(scandir($uploadDir), ['.', '..']) : [];
usort($allFiles, function($a, $b) use ($uploadDir) { return filemtime($uploadDir . $b) - filemtime($uploadDir . $a); });
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($wLang['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="/assets/styles/main.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .wiki-container { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start; margin-top: 30px; }
        .wiki-sidebar { flex: 1; min-width: 250px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 15px; }
        .wiki-content { flex: 3; min-width: 300px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 30px; }
        
        .wiki-nav-list { list-style: none; padding: 0; margin: 0; }
        .wiki-nav-list li { margin-bottom: 8px; }
        .wiki-nav-list li.draggable { cursor: grab; }
        .wiki-nav-list li.draggable:active { cursor: grabbing; }
        .wiki-nav-list li.dragging { opacity: 0.5; }
        .wiki-nav-list a { text-decoration: none; color: var(--text-main); display: block; padding: 8px; border-radius: 6px; transition: 0.2s; font-weight: 750; }
        .wiki-nav-list a:hover, .wiki-nav-list a.active { background: var(--bg-body); color: var(--primary); font-weight: bold; }
        
        .wiki-search-box { margin-bottom: 15px; position: relative; }
        .wiki-search-box input { width: 100%; padding: 8px 30px 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text-main); box-sizing: border-box; }
        .wiki-search-box i { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); cursor: pointer; }

        .wiki-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        .wiki-actions a, .wiki-actions button { margin-left: 10px; font-size: 0.9rem; text-decoration: none; cursor: pointer; border: none; background: none; color: var(--text-muted); }
        .wiki-actions a:hover, .wiki-actions button:hover { color: var(--primary); }
        
        .form-input { width: 100%; padding: 10px; background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border); border-radius: 6px; margin-bottom: 15px; }
        
        .wiki-text table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        .wiki-text th, .wiki-text td { border: 1px solid var(--border); padding: 10px; }
        .wiki-text th { background-color: var(--bg-body); font-weight: bold; text-align: left; }
        .wiki-text tr:nth-child(even) { background-color: rgba(0,0,0,0.02); }
        .wiki-text :not(pre) > code { background-color: #23241f; color: #f8f8f2; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; border: 1px solid #3e3d32; }
        .wiki-text blockquote { border-left: 4px solid #1877f2; background: rgba(0,0,0,0.02); margin: 1em 0; padding: 10px 20px; color: var(--text-muted); font-style: italic; border-radius: 0 4px 4px 0; }
        .wiki-text img { max-width: 100%; height: auto; border-radius: 4px; }
        
        .wiki-text pre { background: #23241f; color: #f8f8f2; padding: 1em; border-radius: 6px; overflow-x: auto; margin-bottom: 15px; position: relative; }
        .wiki-text code { font-family: 'Fira Code', Consolas, monospace; font-size: 14px; }
        
        .copy-btn { position: absolute; top: 5px; right: 5px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px; cursor: pointer; opacity: 0; transition: opacity 0.2s; }
        .wiki-text pre:hover .copy-btn { opacity: 1; }
        .copy-btn:hover { background: rgba(255,255,255,0.2); }

        #searchModal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-box { background: var(--bg-card); width: 90%; max-width: 500px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; flex-direction: column; max-height: 80vh; }
        .modal-header { padding: 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-weight: bold; }
        .modal-body { padding: 15px; overflow-y: auto; flex: 1; }
        .search-result-item { display: block; padding: 10px; border-bottom: 1px solid var(--border); text-decoration: none; color: var(--text-main); }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: var(--bg-body); color: var(--primary); }

        [data-theme="dark"] .wiki-text th { background-color: #4a5568; border-color: #4a5568; color: #fff; }
        [data-theme="dark"] .wiki-text td { border-color: #4a5568; color: #e2e8f0; }
        [data-theme="dark"] .wiki-text tr:nth-child(even) { background-color: #2d3748; }
        [data-theme="dark"] .wiki-text :not(pre) > code { background-color: #4a5568; border-color: #4a5568; }
        [data-theme="dark"] .wiki-text blockquote { background: #1a202c; border-left-color: #4299e1; }
        
        @media (max-width: 768px) {
            .editor-split { flex-direction: column; height: auto; }
            .editor-area { border-right: none; border-bottom: 1px solid var(--border); height: 300px; }
            .preview-area { height: 300px; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div class="wiki-container">
        
        <aside class="wiki-sidebar">
            <h3 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">
                <i class="fa-solid fa-book"></i> Index
            </h3>
            
            <div class="wiki-search-box">
                <input type="text" id="searchInput" placeholder="<?= htmlspecialchars($wLang['search_placeholder'] ?? 'Search...') ?>">
                <i class="fa-solid fa-search" onclick="performSearch()"></i>
            </div>

            <ul class="wiki-nav-list" id="wiki-nav">
                <li><a href="/wiki.php?page=home" class="<?= $currentSlug === 'home' ? 'active' : '' ?>"><?= htmlspecialchars($wLang['home']) ?></a></li>
                <?php foreach($pages as $p): ?>
                    <?php if($p['slug'] !== 'home'): ?>
                    <li class="<?= $isAdmin ? 'draggable' : '' ?>" draggable="<?= $isAdmin ? 'true' : 'false' ?>" data-slug="<?= htmlspecialchars($p['slug']) ?>">
                        <a href="/wiki.php?page=<?= htmlspecialchars($p['slug']) ?>" class="<?= $currentSlug === $p['slug'] ? 'active' : '' ?>">
                            <?php if($isAdmin): ?><i class="fa-solid fa-grip-vertical" style="color:var(--text-muted); margin-right:5px; font-size:0.8em; cursor:grab;"></i><?php endif; ?>
                            <?= htmlspecialchars($p['title']) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <?php if ($isLoggedIn): ?>
            <div style="margin-top:20px; text-align:center;">
                <a href="/wiki.php?action=create" class="btn-primary" style="display: inline-block; text-decoration: none; width: 100%; box-sizing: border-box; max-width: 215px; padding: 10px; border: 1px solid grey; border-radius: 35px; background: #1877f2; color: white; font-weight: 700; float: left;">
                    <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($wLang['create_new']) ?>
                </a>
            </div>
            <?php endif; ?>
        </aside>

        <div class="wiki-content">
            <?php if ($action === 'edit' || $action === 'create'): ?>
                <?php if (!$isLoggedIn) { header('Location: /login.php'); exit; } ?>
                <form method="POST">
                    <h2><?= $action === 'create' ? $wLang['create_new'] : $wLang['edit'] ?></h2>
                    
                    <label style="display:block; margin-bottom:5px; font-weight:bold;"><?= htmlspecialchars($wLang['page_title']) ?></label>
                    <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($pageData['title'] ?? '') ?>" required>
                    
                    <?php if($action === 'edit'): ?>
                        <input type="hidden" name="slug" value="<?= htmlspecialchars($pageData['slug']) ?>">
                    <?php endif; ?>

                    <label style="display:block; margin-bottom:5px; font-weight:bold;"><?= htmlspecialchars($wLang['content']) ?></label>
                    
                    <?php 
                        $editorContent = $pageData['content'] ?? '';
                        $editorName = 'content';
                        $editorUploadUrl = '/upload-handler.php';
                        include 'templates/editor.php'; 
                    ?>

                    <div style="margin-top:15px; text-align:right;">
                        <a href="/wiki.php?page=<?= htmlspecialchars($currentSlug) ?>" style="margin-right:15px; color:var(--text-muted); text-decoration:none;">
                            <?= htmlspecialchars($wLang['cancel'] ?? 'Cancel') ?>
                        </a>
                        <button type="submit" name="save_page" class="btn-primary" style="padding:10px 20px; border:none; border-radius:6px; background:var(--primary); color:#fff; cursor:pointer;">
                            <i class="fa-solid fa-save"></i> <?= htmlspecialchars($wLang['save']) ?>
                        </button>
                    </div>
                </form>

            <?php else: ?>
                <div class="wiki-header">
                    <h1 style="margin:0;"><?= htmlspecialchars($pageData['title']) ?></h1>
                    <?php if ($isLoggedIn): ?>
                    <div class="wiki-actions">
                        <a href="/wiki.php?page=<?= htmlspecialchars($pageData['slug']) ?>&action=edit" title="<?= htmlspecialchars($wLang['edit']) ?>">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <?php if($pageData['slug'] !== 'home'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?= htmlspecialchars($wLang['confirm_delete']) ?>');">
                            <button type="submit" name="delete_page" title="<?= htmlspecialchars($wLang['delete']) ?>">
                                <i class="fa-solid fa-trash" style="color:#e53e3e;"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="wiki-render-output" class="wiki-text" style="line-height:1.6; color:var(--text-main);"></div>
                <script>
                    const rawContent = <?= json_encode($pageData['content'] ?? '') ?>;
                    document.addEventListener('DOMContentLoaded', () => {
                        const out = document.getElementById('wiki-render-output');
                        out.innerHTML = marked.parse(rawContent);
                        if(window.hljs) hljs.highlightAll();

                        const blocks = out.querySelectorAll('pre');
                        blocks.forEach(block => {
                            if (navigator.clipboard) {
                                const btn = document.createElement('button');
                                btn.className = 'copy-btn';
                                btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy';
                                btn.addEventListener('click', async () => {
                                    const code = block.querySelector('code');
                                    const text = code ? code.innerText : block.innerText;
                                    try {
                                        await navigator.clipboard.writeText(text);
                                        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                                        setTimeout(() => btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy', 2000);
                                    } catch (err) {
                                        console.error('Failed to copy', err);
                                    }
                                });
                                block.appendChild(btn);
                            }
                        });
                    });
                </script>

                <div style="margin-top:40px; border-top:1px solid var(--border); padding-top:10px; font-size:0.8rem; color:var(--text-muted);">
                    <?= htmlspecialchars($wLang['last_update']) ?> <?= htmlspecialchars($pageData['last_updated'] ?? '-') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="searchModal">
    <div class="modal-box">
        <div class="modal-header">
            <span><?= htmlspecialchars($wLang['search_results'] ?? 'Search Results') ?></span>
            <button onclick="document.getElementById('searchModal').style.display='none'" style="border:none; background:none; cursor:pointer; font-size:1.2rem; color:var(--text-main);">&times;</button>
        </div>
        <div class="modal-body" id="searchResults"></div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
    const toggleBtn = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    if (toggleBtn) { toggleBtn.addEventListener('click', () => { const current = html.getAttribute('data-theme'); const next = current === 'dark' ? 'light' : 'dark'; html.setAttribute('data-theme', next); localStorage.setItem('theme', next); }); }

    function toggleLang() { document.getElementById('langMenu').classList.toggle('show'); }
    window.addEventListener('click', function(e) { 
        if (document.getElementById('langDropdown') && !document.getElementById('langDropdown').contains(e.target)) {
            document.getElementById('langMenu').classList.remove('show');
        }
    });

    const searchInput = document.getElementById('searchInput');
    const modal = document.getElementById('searchModal');
    const results = document.getElementById('searchResults');

    function performSearch() {
        const q = searchInput.value.trim();
        if(q.length < 2) return;
        
        results.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);">Loading...</div>';
        modal.style.display = 'flex';
        
        fetch('/wiki.php?ajax_search=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            results.innerHTML = '';
            if(data.length === 0) {
                results.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);"><?= htmlspecialchars($wLang['no_results'] ?? 'No results.') ?></div>';
            } else {
                data.forEach(item => {
                    const a = document.createElement('a');
                    a.className = 'search-result-item';
                    a.href = '/wiki.php?page=' + item.slug;
                    a.innerText = item.title;
                    results.appendChild(a);
                });
            }
        });
    }

    if(searchInput) {
        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    <?php if ($isAdmin): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const list = document.getElementById('wiki-nav');
        if(!list) return;
        let draggedItem = null;

        list.addEventListener('dragstart', (e) => {
            if (!e.target.closest('li.draggable')) return;
            draggedItem = e.target.closest('li.draggable');
            draggedItem.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        list.addEventListener('dragend', () => {
            if (draggedItem) draggedItem.classList.remove('dragging');
            draggedItem = null;
            saveOrder();
        });

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            const afterElement = getDragAfterElement(list, e.clientY);
            const currentDraggable = document.querySelector('.dragging');
            if (afterElement == null) {
                list.appendChild(currentDraggable);
            } else {
                list.insertBefore(currentDraggable, afterElement);
            }
        });

        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.draggable:not(.dragging)')];
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        function saveOrder() {
            const items = [...list.querySelectorAll('li[data-slug]')];
            const order = items.map(item => item.getAttribute('data-slug'));
            
            fetch('/wiki.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_order', order: order })
            });
        }
    });
    <?php endif; ?>
</script>
</body>
</html>