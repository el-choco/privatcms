<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$forumLang = $t_temp['forum'] ?? [];
$cmnLang = $t_temp['common'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_order' && isset($_POST['order'])) {
        header('Content-Type: application/json');
        try {
            $order = $_POST['order']; 
            foreach ($order as $index => $id) {
                $stmt = $pdo->prepare("UPDATE forum_boards SET sort_order = ? WHERE id = ?");
                $stmt->execute([$index + 1, (int)$id]);
            }
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $isCat = !empty($_POST['is_category']) ? 1 : 0;
    
    if ($title) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        if ($id) {
            $stmt = $pdo->prepare("UPDATE forum_boards SET title=?, description=?, slug=?, parent_id=?, is_category=? WHERE id=?");
            $stmt->execute([$title, $desc, $slug, $parentId, $isCat, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO forum_boards (title, description, slug, parent_id, is_category) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $slug, $parentId, $isCat]);
        }
        header("Location: forums.php");
        exit;
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM forum_boards WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    header("Location: forums.php");
    exit;
}

$allBoards = $pdo->query("SELECT * FROM forum_boards ORDER BY sort_order ASC, created_at ASC")->fetchAll();

function buildTree(array $elements, $parentId = null) {
    $branch = [];
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = buildTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

$tree = buildTree($allBoards);

require_once 'header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
    .content-area { max-width: 1500px; margin: 0 auto; width: 100%; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-top: 20px; }
    .page-title { margin: 0; font-size: 2rem; font-weight: 700; color: #1e293b; }
    
    .cat-header { background: #1877f2; color: #fff; font-weight: 700; font-size: 1rem; padding: 10px 15px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: space-between; height: 20px; }
    .cat-actions .btn-icon { color: rgb(100 116 139); border-color: rgba(255,255,255,0.3); }
    .cat-actions .btn-icon:hover { color: #fff; background: rgba(255,255,255,0.2); }
    
    .board-header { background: #edf2f7; color: #2d3748; font-weight: 600; font-size: 0.95rem; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #cbd5e0; padding: 8px 15px; display: flex; justify-content: space-between; align-items: center;}
    
    .forum-row { background: #fff; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; padding: 12px 15px; }
    .forum-row:last-child { border-bottom: none; }
    .forum-row:hover { background-color: #f8fafc; }
    
    .drag-handle { cursor: grab; color: #cbd5e1; margin-right: 15px; width: 20px; text-align: center; }
    .drag-handle:hover { color: #4a5568; }
    
    .forum-info { flex: 1; }
    .forum-title { font-weight: 600; color: #334155; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
    .forum-slug { font-size: 0.75rem; color: #94a3b8; font-family: monospace; margin-top: 2px; }
    
    .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-left: 10px; }
    .badge-cat { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .badge-forum { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

    .actions { display: flex; gap: 5px; }
    .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; transition: all 0.2s; cursor: pointer; font-size: 0.9rem; text-decoration: none; }
    .btn-icon:hover { background-color: #f8fafc; color: #334155; border-color: #cbd5e1; }
    .btn-icon.delete:hover { background-color: #fef2f2; color: #ef4444; border-color: #fee2e2; }
    
    .empty-placeholder { padding: 20px; text-align: center; color: #a0aec0; font-style: italic; background: #fff; }
    
    .sortable-container { display: flex; flex-direction: column; gap: 20px; }
    .board-card { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .board-card:first-child { border-top: 0; } 
</style>

<div class="content-area">
    <div class="page-header">
        <h1 class="page-title"><?= htmlspecialchars($forumLang['manage_boards'] ?? 'Manage Boards') ?></h1>
        <button class="btn btn-primary" onclick="openModal()">
            <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($forumLang['create_board'] ?? 'New Board') ?>
        </button>
    </div>

    <div id="sortable-root" class="sortable-container">
        
        <?php 
        foreach ($tree as $category) {
            if ($category['is_category']) {
                $jsonData = htmlspecialchars(json_encode($category), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="board-card" data-id="<?= $category['id'] ?>">
                    <div class="cat-header">
                        <span><i class="fa-regular fa-folder-open" style="margin-right:8px; opacity:0.7;"></i> <?= htmlspecialchars($category['title']) ?></span>
                        <div class="cat-actions actions">
                             <button class="btn-icon" title="<?= htmlspecialchars($cmnLang['edit'] ?? 'Edit') ?>" onclick='openModal(<?= $jsonData ?>)'><i class="fa-solid fa-pen"></i></button>
                             <a href="?delete=<?= $category['id'] ?>" class="btn-icon delete" title="<?= htmlspecialchars($cmnLang['delete'] ?? 'Delete') ?>" onclick="return confirm('<?= htmlspecialchars($forumLang['delete_confirm'] ?? 'Delete?') ?>')"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </div>
                    
                    <div class="sortable-children" data-parent-id="<?= $category['id'] ?>">
                        <?php if (!empty($category['children'])): ?>
                            <?php foreach ($category['children'] as $board): 
                                $jsonDataBoard = htmlspecialchars(json_encode($board), ENT_QUOTES, 'UTF-8');
                            ?>
                                <div class="forum-row" data-id="<?= $board['id'] ?>">
                                    <div class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></div>
                                    <div class="forum-info">
                                        <div class="forum-title">
                                            <?= htmlspecialchars($board['title']) ?>
                                            <span class="badge badge-forum"><?= htmlspecialchars($forumLang['board_label'] ?? 'Forum') ?></span>
                                        </div>
                                        <div class="forum-slug">/forum/<?= htmlspecialchars($board['slug']) ?></div>
                                    </div>
                                    <div class="actions">
                                        <button class="btn-icon" title="<?= htmlspecialchars($cmnLang['edit'] ?? 'Edit') ?>" onclick='openModal(<?= $jsonDataBoard ?>)'><i class="fa-solid fa-pen"></i></button>
                                        <a href="?delete=<?= $board['id'] ?>" class="btn-icon delete" title="<?= htmlspecialchars($cmnLang['delete'] ?? 'Delete') ?>" onclick="return confirm('<?= htmlspecialchars($forumLang['delete_confirm'] ?? 'Delete?') ?>')"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-placeholder"><?= htmlspecialchars($forumLang['no_boards'] ?? 'No forums in this category') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        }

        $orphans = array_filter($tree, function($item) { return !$item['is_category']; });
        
        if (!empty($orphans)) {
            ?>
            <div class="board-card" data-id="orphans">
                <div class="cat-header" style="background: #4a5568;">
                    <span><i class="fa-solid fa-layer-group" style="margin-right:8px; opacity:0.7;"></i> <?= htmlspecialchars($forumLang['uncategorized'] ?? 'Uncategorized / Top Level Forums') ?></span>
                </div>
                <div class="sortable-children" data-parent-id="0">
                    <?php foreach ($orphans as $board): 
                         $jsonDataBoard = htmlspecialchars(json_encode($board), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="forum-row" data-id="<?= $board['id'] ?>">
                            <div class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></div>
                            <div class="forum-info">
                                <div class="forum-title">
                                    <?= htmlspecialchars($board['title']) ?>
                                    <span class="badge badge-forum"><?= htmlspecialchars($forumLang['board_label'] ?? 'Forum') ?></span>
                                </div>
                                <div class="forum-slug">/forum/<?= htmlspecialchars($board['slug']) ?></div>
                            </div>
                            <div class="actions">
                                <button class="btn-icon" title="<?= htmlspecialchars($cmnLang['edit'] ?? 'Edit') ?>" onclick='openModal(<?= $jsonDataBoard ?>)'><i class="fa-solid fa-pen"></i></button>
                                <a href="?delete=<?= $board['id'] ?>" class="btn-icon delete" title="<?= htmlspecialchars($cmnLang['delete'] ?? 'Delete') ?>" onclick="return confirm('<?= htmlspecialchars($forumLang['delete_confirm'] ?? 'Delete?') ?>')"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
        
        if (empty($tree)) {
             echo '<div class="empty-placeholder" style="padding: 50px; border: 2px dashed #e2e8f0; border-radius: 8px;">' . htmlspecialchars($forumLang['no_boards'] ?? 'No boards found') . '</div>';
        }
        ?>
    </div>
</div>

<div id="boardModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.6); z-index:100; backdrop-filter: blur(2px); justify-content:center; align-items:center;">
    <div class="card" style="width:100%; max-width:500px; padding:30px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <h3 id="modalTitle" style="margin-top:0; margin-bottom:20px; font-size:1.25rem; color:#1e293b;"><?= htmlspecialchars($forumLang['create_board'] ?? 'New Board') ?></h3>
        <form method="POST">
            <input type="hidden" name="id" id="boardId">
            <div class="form-group">
                <label class="form-label"><?= htmlspecialchars($forumLang['board_title'] ?? 'Title') ?></label>
                <input type="text" name="title" id="boardTitle" class="form-control" required style="padding:10px;">
            </div>
            <div class="form-group">
                <label class="form-label"><?= htmlspecialchars($forumLang['board_desc'] ?? 'Description') ?></label>
                <textarea name="description" id="boardDesc" class="form-control" rows="3" style="padding:10px;"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label"><?= htmlspecialchars($forumLang['parent_board'] ?? 'Parent Board') ?></label>
                <select name="parent_id" id="boardParent" class="form-control" style="padding:10px;">
                    <option value=""><?= htmlspecialchars($forumLang['no_parent'] ?? 'None (Top Level)') ?></option>
                    <?php foreach($allBoards as $opt): ?>
                        <option value="<?= $opt['id'] ?>"><?= htmlspecialchars($opt['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="background:#f8fafc; padding:15px; border-radius:6px; border:1px solid #e2e8f0; margin-top:20px;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; margin:0; color:#334155; font-weight:500;">
                    <input type="checkbox" name="is_category" id="boardIsCat" value="1" style="width:16px; height:16px;">
                    <?= htmlspecialchars($forumLang['is_category'] ?? 'Is Category') ?>
                </label>
                <div style="font-size:0.8rem; color:#64748b; margin-top:5px; margin-left:26px;">
                    <?= htmlspecialchars($forumLang['cat_hint'] ?? 'Categories contain forums and cannot contain threads directly.') ?>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:25px;">
                <button type="button" class="btn" onclick="document.getElementById('boardModal').style.display='none'" style="background:#fff; border:1px solid #cbd5e0; color:#475569; padding:10px 20px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding:10px 20px;"><?= htmlspecialchars($forumLang['save_board'] ?? 'Save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(data = null) {
    const modal = document.getElementById('boardModal');
    const title = document.getElementById('modalTitle');
    const inpId = document.getElementById('boardId');
    const inpTitle = document.getElementById('boardTitle');
    const inpDesc = document.getElementById('boardDesc');
    const inpParent = document.getElementById('boardParent');
    const inpIsCat = document.getElementById('boardIsCat');

    if (data) {
        title.innerText = "<?= htmlspecialchars($forumLang['edit_board'] ?? 'Edit Board') ?>";
        inpId.value = data.id;
        inpTitle.value = data.title;
        inpDesc.value = data.description;
        inpParent.value = data.parent_id || "";
        inpIsCat.checked = data.is_category == 1;
    } else {
        title.innerText = "<?= htmlspecialchars($forumLang['create_board'] ?? 'New Board') ?>";
        inpId.value = "";
        inpTitle.value = "";
        inpDesc.value = "";
        inpParent.value = "";
        inpIsCat.checked = false;
    }
    modal.style.display = 'flex';
}
window.onclick = function(e) {
    const modal = document.getElementById('boardModal');
    if (e.target == modal) modal.style.display = 'none';
}

document.querySelectorAll('.sortable-children').forEach(function(el) {
    Sortable.create(el, {
        group: 'nested', 
        handle: '.drag-handle',
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        onEnd: function (evt) {
            
            const parent = evt.to;
            const items = parent.querySelectorAll('.forum-row');
            const order = [];
            items.forEach(item => order.push(item.getAttribute('data-id')));
            
            
            fetch('forums.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=update_order&order[]=' + order.join('&order[]=')
            });
        }
    });
});

const root = document.getElementById('sortable-root');
if (root) {
    Sortable.create(root, {
        handle: '.cat-header', 
        animation: 150,
        onEnd: function (evt) {
            const items = root.querySelectorAll('.board-card');
            const order = [];
            items.forEach(item => order.push(item.getAttribute('data-id')));
            
            const cleanOrder = order.filter(id => id !== 'orphans');

            if(cleanOrder.length > 0) {
                fetch('forums.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=update_order&order[]=' + cleanOrder.join('&order[]=')
                });
            }
        }
    });
}
</script>

<?php include 'footer.php'; ?>