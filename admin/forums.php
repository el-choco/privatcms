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

function renderRows($boards, $parentId = null, $level = 0, $forumLang = [], $cmnLang = []) {
    $children = array_filter($boards, function($b) use ($parentId) {
        return $b['parent_id'] == $parentId;
    });

    if (empty($children)) return;

    foreach ($children as $b) {
        $padding = 10 + ($level * 35);
        $treeIcon = ($level > 0) ? '<span class="tree-icon"><i class="fa-solid fa-turn-up fa-rotate-90"></i></span>' : '';
        $rowClass = $b['is_category'] ? 'row-category' : 'row-forum';
        
        echo '<tr class="' . $rowClass . '" data-id="' . $b['id'] . '">';
        
        echo '<td style="width:40px; text-align:center; cursor:grab;" class="drag-handle"><i class="fa-solid fa-grip-vertical" style="color:#cbd5e1;"></i></td>';

        echo '<td style="padding-left: ' . $padding . 'px;">';
        echo '<div style="display:flex; align-items:center;">';
        echo $treeIcon;
        echo '<div>';
        echo '<div class="board-title">' . htmlspecialchars($b['title']) . '</div>';
        echo '<div class="board-slug">/forum/' . htmlspecialchars($b['slug']) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';

        echo '<td>';
        if ($b['is_category']) {
            echo '<span class="badge badge-cat">' . htmlspecialchars($forumLang['cat_label'] ?? 'Category') . '</span>';
        } else {
            echo '<span class="badge badge-forum">' . htmlspecialchars($forumLang['board_label'] ?? 'Forum') . '</span>';
        }
        echo '</td>';

        echo '<td class="text-muted">';
        if ($b['parent_id']) {
            foreach($boards as $p) { if($p['id'] == $b['parent_id']) { echo htmlspecialchars($p['title']); break; } }
        } else {
            echo '<span style="opacity:0.3;">—</span>';
        }
        echo '</td>';

        echo '<td style="text-align:right;">';
        $jsonData = htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8');
        echo "<button class='btn-icon' title='" . htmlspecialchars($cmnLang['edit'] ?? 'Edit') . "' onclick='openModal($jsonData)'><i class='fa-solid fa-pen'></i></button>";
        echo "<a href='?delete={$b['id']}' class='btn-icon delete' title='" . htmlspecialchars($cmnLang['delete'] ?? 'Delete') . "' onclick=\"return confirm('".htmlspecialchars($forumLang['delete_confirm'] ?? 'Delete?')."')\"><i class='fa-solid fa-trash'></i></a>";
        echo '</td>';
        
        echo '</tr>';

        renderRows($boards, $b['id'], $level + 1, $forumLang, $cmnLang);
    }
}

require_once 'header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
    .card-table { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; border-top: 5px solid #3182ce; }
    .forum-table { width: 100%; border-collapse: collapse; }
    .forum-table th { background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    .forum-table td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
    .forum-table tr:last-child td { border-bottom: none; }
    
    .row-category { background-color: #e6f0ff; }
    .row-category:hover td { background-color: #dcebff; }
    
    .row-forum { background-color: #f8fbff; }
    .row-forum:hover td { background-color: #f0f7ff; }

    .row-category .board-title { font-weight: 700; color: #0f172a; font-size: 1rem; }
    .row-forum .board-title { font-weight: 600; color: #334155; font-size: 0.95rem; }
    
    .board-slug { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; font-family: monospace; }
    
    .tree-icon { color: #cbd5e1; margin-right: 12px; font-size: 0.8rem; display: flex; align-items: center; height: 100%; }
    
    .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
    .badge-cat { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .badge-forum { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    
    .text-muted { color: #64748b; font-size: 0.9rem; }
    
    .btn-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid transparent; background: transparent; color: #64748b; transition: all 0.2s; cursor: pointer; margin-left: 4px; font-size: 0.9rem; }
    .btn-icon:hover { background-color: rgba(255,255,255,0.5); color: #334155; border-color: #cbd5e1; }
    .btn-icon.delete:hover { background-color: #fef2f2; color: #ef4444; border-color: #fee2e2; }
    
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .page-title { margin: 0; font-size: 1.5rem; font-weight: 700; color: #1e293b; }
    
    .drag-handle:hover i { color: #64748b !important; }
</style>

<div class="admin-content">
    <div class="page-header">
        <h1 class="page-title"><?= htmlspecialchars($forumLang['manage_boards'] ?? 'Manage Boards') ?></h1>
        <button class="btn btn-primary" onclick="openModal()">
            <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($forumLang['create_board'] ?? 'New Board') ?>
        </button>
    </div>

    <div class="card-table">
        <table class="forum-table">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th><?= htmlspecialchars($forumLang['board_title'] ?? 'Title') ?></th>
                    <th width="120"><?= htmlspecialchars($forumLang['type'] ?? 'Type') ?></th>
                    <th width="200"><?= htmlspecialchars($forumLang['parent_board'] ?? 'Parent') ?></th>
                    <th width="120" style="text-align:right;"><?= htmlspecialchars($cmnLang['actions'] ?? 'Actions') ?></th>
                </tr>
            </thead>
            <tbody id="sortable-list">
                <?php if (empty($allBoards)): ?>
                    <tr>
                        <td colspan="5" style="padding:40px; text-align:center; color:#94a3b8;">
                            <i class="fa-regular fa-folder-open" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                            <?= htmlspecialchars($forumLang['no_boards'] ?? 'No boards found') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php renderRows($allBoards, null, 0, $forumLang, $cmnLang); ?>
                <?php endif; ?>
            </tbody>
        </table>
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

const list = document.getElementById('sortable-list');
if (list) {
    Sortable.create(list, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function (evt) {
            const items = list.querySelectorAll('tr');
            const order = [];
            items.forEach(item => order.push(item.getAttribute('data-id')));
            
            fetch('forums.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=update_order&order[]=' + order.join('&order[]=')
            });
        }
    });
}
</script>

<?php include 'footer.php'; ?>