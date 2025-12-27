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
$ftLang = $t_temp['forum_threads'] ?? []; 
$cLang = $t_temp['common'] ?? [];

$currentUser = $_SESSION['admin'];
$isAdmin = ($currentUser['role'] ?? 'viewer') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_moves') {
    header('Content-Type: application/json');
    if (!$isAdmin) {
        echo json_encode(['status' => 'error', 'message' => 'Denied']);
        exit;
    }
    
    $moves = json_decode($_POST['moves'] ?? '[]', true);
    $count = 0;
    
    if (is_array($moves)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE forum_threads SET board_id = ?, sort_order = ? WHERE id = ?");
            foreach ($moves as $move) {
                if (!empty($move['thread_id'])) {
                    $order = isset($move['sort_order']) ? (int)$move['sort_order'] : 0;
                    $boardId = (int)$move['new_board_id'];
                    $threadId = (int)$move['thread_id'];
                    
                    $stmt->execute([$boardId, $order, $threadId]);
                    $count++;
                }
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'count' => $count]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = $_POST['ids'] ?? [];

    if ($action === 'bulk_delete' && !empty($ids) && $isAdmin) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM forum_threads WHERE id IN ($placeholders)")->execute($ids);
    } elseif ($id > 0) {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM forum_threads WHERE id = ?")->execute([$id]);
        } elseif ($action === 'toggle_sticky') {
            $pdo->prepare("UPDATE forum_threads SET is_sticky = 1 - is_sticky WHERE id = ?")->execute([$id]);
        } elseif ($action === 'toggle_locked') {
            $pdo->prepare("UPDATE forum_threads SET is_locked = 1 - is_locked WHERE id = ?")->execute([$id]);
        }
    }
    header("Location: forum-posts.php"); exit;
}

$search = $_GET['q'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND t.title LIKE ?";
    $params[] = "%$search%";
}

$boardsData = $pdo->query("SELECT * FROM forum_boards ORDER BY sort_order ASC, title ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT t.*, l.title as label_title, l.css_class as label_class, u.username 
        FROM forum_threads t 
        LEFT JOIN forum_labels l ON t.label_id = l.id
        LEFT JOIN users u ON t.user_id = u.id
        $where
        ORDER BY t.is_sticky DESC, t.sort_order ASC, t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allThreads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$threadsByBoard = [];
foreach ($allThreads as $t) {
    $threadsByBoard[$t['board_id']][] = $t;
}

$structure = [];
$orphans = [];
foreach ($boardsData as $b) {
    if ($b['is_category']) {
        $structure[$b['id']] = [ 'info' => $b, 'boards' => [] ];
    }
}
foreach ($boardsData as $b) {
    if (!$b['is_category']) {
        if ($b['parent_id'] && isset($structure[$b['parent_id']])) {
            $structure[$b['parent_id']]['boards'][] = $b;
        } else {
            $orphans[] = $b;
        }
    }
}

require_once 'header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
    .drag-handle { cursor: grab; color: #cbd5e1; transition: color 0.2s; width: 30px; text-align: center; }
    .drag-handle:hover { color: #4a5568; }
    .cat-header { background: #1877f2; color: #fff; font-weight: 700; font-size: 1rem; padding: 10px 15px; text-transform: uppercase; letter-spacing: 1px; }
    .board-header { background: #edf2f7; color: #2d3748; font-weight: 600; font-size: 0.95rem; border-top: 2px solid #e2e8f0; border-bottom: 2px solid #cbd5e0; padding: 8px 15px; }
    .sortable-ghost { opacity: 0.4; background: #ebf8ff !important; }
    .save-bar { display: none; background: #c6f6d5; border: 1px solid #9ae6b4; color: #22543d; padding: 10px 20px; border-radius: 6px; margin-bottom: 20px; align-items: center; justify-content: space-between; }
    .thread-row { background: #fff; border-bottom: 1px solid #f1f5f9; }
    .empty-placeholder { height: 10px; background: transparent; }
    td {border-radius: 8px;}
    :not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}
</style>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1500px;">

            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="margin:0; font-size: 2rem; color: #1a202c;">
                    <?= htmlspecialchars($ftLang['title'] ?? 'Forum Posts') ?>
                </h1>
                
                <div style="display:flex; gap:10px;">
                    <button id="btnSaveOrder" class="btn btn-primary" onclick="saveOrder()" disabled style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars($ftLang['save_order'] ?? 'Save Order') ?>
                    </button>
                    
                    <form method="get" style="display:flex; gap:10px;">
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars($ftLang['search_placeholder'] ?? 'Search...') ?>" class="input" style="padding: 8px; border:1px solid #ddd; border-radius:6px;">
                        <button type="submit" class="btn btn-primary">🔍</button>
                    </form>
                </div>
            </header>

            <div id="saveFeedback" class="save-bar">
                <span><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($ftLang['move_success'] ?? 'Änderungen vorgemerkt. Bitte speichern!') ?></span>
            </div>

            <form method="post" id="bulkForm">
                <input type="hidden" name="action" value="bulk_delete">
                
                <div class="card" style="overflow-x: auto; border-top: 4px solid #1877f2;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 15px; width: 40px;"></th>
                                <th style="padding: 15px; width: 40px;"><input type="checkbox" onclick="document.querySelectorAll('.chk-thread').forEach(c => c.checked = this.checked)"></th>
                                <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                    <?= htmlspecialchars($ftLang['col_title'] ?? 'Title') ?>
                                </th>
                                <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                    <?= htmlspecialchars($ftLang['col_author'] ?? 'Author') ?>
                                </th>
                                <th style="padding: 15px; text-align: center; color: #718096; font-size: 12px; text-transform: uppercase;">
                                    <?= htmlspecialchars($ftLang['col_status'] ?? 'Status') ?>
                                </th>
                                <th style="padding: 15px; text-align: right; color: #718096; font-size: 12px; text-transform: uppercase;">
                                    <?= htmlspecialchars($ftLang['col_actions'] ?? 'Actions') ?>
                                </th>
                            </tr>
                        </thead>
                        
                        <?php if (empty($allThreads) && empty($search) && empty($structure) && empty($orphans)): ?>
                            <tbody>
                                <tr>
                                    <td colspan="6" style="padding: 40px; text-align: center; color: #a0aec0;">
                                        <?= htmlspecialchars($ftLang['no_threads'] ?? 'No threads found.') ?>
                                    </td>
                                </tr>
                            </tbody>
                        <?php else: ?>
                        
                            <?php 
                            $renderBoardRows = function($board, $threads, $ftLang) {
                                ?>
                                <tbody class="board-container" data-board-id="<?= $board['id'] ?>">
                                    <tr class="ignore-drag">
                                        <td colspan="6" class="board-header">
                                            <i class="fa-regular fa-folder-open"></i> <?= htmlspecialchars($board['title']) ?>
                                        </td>
                                    </tr>
                                    <?php if(empty($threads)): ?>
                                        <tr class="empty-placeholder"><td colspan="6"></td></tr>
                                    <?php endif; ?>
                                    <?php foreach($threads as $t): ?>
                                        <tr class="thread-row" data-id="<?= $t['id'] ?>">
                                            <td style="padding: 15px;" class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></td>
                                            <td style="padding: 15px;">
                                                <input type="checkbox" name="ids[]" value="<?= $t['id'] ?>" class="chk-thread">
                                            </td>
                                            <td style="padding: 15px;">
                                                <div style="font-weight: bold; font-size: 1.05rem; color: #2d3748;">
                                                    <?= htmlspecialchars($t['title']) ?>
                                                </div>
                                                <?php if ($t['label_title']): ?>
                                                    <span class="badge <?= htmlspecialchars($t['label_class']) ?>" style="font-size: 10px; padding: 2px 6px; border-radius: 4px; color:white; background:#718096;">
                                                        <?= htmlspecialchars($t['label_title']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 15px;">
                                                <?= htmlspecialchars($t['username'] ?? 'Unknown') ?>
                                            </td>
                                            <td style="padding: 15px; text-align: center;">
                                                <?php if($t['is_sticky']): ?>
                                                    <span title="<?= htmlspecialchars($ftLang['label_sticky'] ?? 'Sticky') ?>">📌</span>
                                                <?php endif; ?>
                                                <?php if($t['is_locked']): ?>
                                                    <span title="<?= htmlspecialchars($ftLang['label_locked'] ?? 'Locked') ?>">🔒</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 15px; text-align: right;">
                                                <div style="display: flex; justify-content: flex-end; gap: 5px;">
                                                    <button type="button" class="btn" onclick="postAction('toggle_sticky', <?= $t['id'] ?>)" style="background: #fff; border: 1px solid #e2e8f0; padding: 5px 8px;" title="<?= htmlspecialchars($ftLang['tooltip_stick'] ?? 'Stick') ?>">
                                                        <?= $t['is_sticky'] ? '📍' : '📌' ?>
                                                    </button>
                                                    <button type="button" class="btn" onclick="postAction('toggle_locked', <?= $t['id'] ?>)" style="background: #fff; border: 1px solid #e2e8f0; padding: 5px 8px;" title="<?= htmlspecialchars($ftLang['tooltip_lock'] ?? 'Lock') ?>">
                                                        <?= $t['is_locked'] ? '🔓' : '🔒' ?>
                                                    </button>
                                                    <a href="forum-post-edit.php?id=<?= $t['id'] ?>" class="btn" style="background: #fff; border: 1px solid #e2e8f0; padding: 5px 8px;" title="<?= htmlspecialchars($ftLang['tooltip_edit'] ?? 'Edit') ?>">✏️</a>
                                                    <button type="button" class="btn" onclick="postAction('delete', <?= $t['id'] ?>)" style="background: #fff; border: 1px solid #fed7d7; color: #e53e3e; padding: 5px 8px;" title="<?= htmlspecialchars($ftLang['tooltip_delete'] ?? 'Delete') ?>">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <?php
                            };
                            ?>

                            <?php foreach ($orphans as $board): $renderBoardRows($board, $threadsByBoard[$board['id']] ?? [], $ftLang); endforeach; ?>

                            <?php foreach ($structure as $catId => $cat): ?>
                                <tbody class="ignore-drag">
                                    <tr><td colspan="6" class="cat-header"><?= htmlspecialchars($cat['info']['title']) ?></td></tr>
                                </tbody>
                                <?php foreach ($cat['boards'] as $board): $renderBoardRows($board, $threadsByBoard[$board['id']] ?? [], $ftLang); endforeach; ?>
                            <?php endforeach; ?>
                        
                        <?php endif; ?>
                    </table>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn" style="background: #e53e3e; color:white;" onclick="return confirm('<?= htmlspecialchars($ftLang['confirm_delete'] ?? 'Delete?') ?>')">
                        <?= htmlspecialchars($ftLang['bulk_delete'] ?? 'Delete Selected') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="singleActionForm" method="post" style="display:none;">
    <input type="hidden" name="action" id="formAction">
    <input type="hidden" name="id" id="formId">
</form>

<script>
function postAction(action, id) {
    if(action === 'delete' && !confirm('<?= htmlspecialchars($ftLang['confirm_delete'] ?? 'Delete?') ?>')) return;
    document.getElementById('formAction').value = action;
    document.getElementById('formId').value = id;
    document.getElementById('singleActionForm').submit();
}

const btnSave = document.getElementById('btnSaveOrder');
const feedback = document.getElementById('saveFeedback');

document.querySelectorAll('.board-container').forEach(function(tbody) {
    Sortable.create(tbody, {
        group: 'shared_boards', 
        handle: '.drag-handle',
        animation: 150,
        filter: '.ignore-drag, .empty-placeholder',
        onEnd: function (evt) {
            btnSave.disabled = false;
            btnSave.style.opacity = 1;
            btnSave.style.cursor = 'pointer';
            feedback.style.display = 'flex';
        }
    });
});

function saveOrder() {
    const moves = [];
    
    document.querySelectorAll('.board-container').forEach(tbody => {
        const currentBoardId = tbody.getAttribute('data-board-id');
        
        tbody.querySelectorAll('.thread-row').forEach((row, index) => {
            const threadId = row.getAttribute('data-id');
            if (threadId && currentBoardId) {
                moves.push({
                    thread_id: threadId,
                    new_board_id: currentBoardId,
                    sort_order: index 
                });
            }
        });
    });

    if (moves.length === 0) {
        alert("Keine Threads gefunden (Logikfehler?).");
        return;
    }

    const formData = new FormData();
    formData.append('action', 'save_moves');
    formData.append('moves', JSON.stringify(moves));

    const originalBtnText = btnSave.innerHTML;
    btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    
    fetch('forum-posts.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            location.reload(); 
        } else {
            alert('Error: ' + (data.message || 'Unknown'));
            btnSave.innerHTML = originalBtnText;
        }
    })
    .catch(e => {
        alert('Network Error');
        console.error(e);
        btnSave.innerHTML = originalBtnText;
    });
}
</script>

<?php include 'footer.php'; ?>