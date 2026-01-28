<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

$userRole = $_SESSION['admin']['role'] ?? 'viewer';
if ($userRole !== 'admin') { header('Location: /admin/'); exit; }

require_once __DIR__ . '/../src/App/Database.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$cLang = $t['comments'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($action === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM comments WHERE parent_id = ?")->execute([$id]);
    } 
    elseif ($action === 'approve' && $id > 0) {
        $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?")->execute([$id]);
    } 
    elseif ($action === 'spam' && $id > 0) {
        $pdo->prepare("UPDATE comments SET status = 'spam' WHERE id = ?")->execute([$id]);
    } 
    elseif ($action === 'update_content' && $id > 0) {
        $content = trim($_POST['content'] ?? '');
        $pdo->prepare("UPDATE comments SET content = ? WHERE id = ?")->execute([$content, $id]);
    } 
    elseif ($action === 'reply') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $parentId = (int)($_POST['parent_id'] ?? 0); 
        $replyContent = trim($_POST['reply_content'] ?? '');
        $adminName = $_SESSION['admin']['username'] ?? 'Admin';
        
        if ($postId > 0 && $replyContent !== '') {
            $stmt = $pdo->prepare("INSERT INTO comments (post_id, parent_id, author_name, author_email, content, status, created_at) VALUES (?, ?, ?, '', ?, 'approved', NOW())");
            $stmt->execute([$postId, ($parentId > 0 ? $parentId : null), $adminName, $replyContent]);
        }
    }
    
    header("Location: comments.php");
    exit;
}

$allComments = $pdo->query("SELECT c.*, p.title as post_title FROM comments c JOIN posts p ON c.post_id = p.id ORDER BY c.created_at DESC")->fetchAll();

$parents = [];
$children = [];

foreach ($allComments as $c) {
    if (!empty($c['parent_id'])) {
        $children[$c['parent_id']][] = $c; 
    } else {
        $parents[] = $c; 
    }
}

function renderCommentRow($c, $cLang, $t, $isChild = false) {
    $rowStyle = "border-bottom: 1px solid #f1f5f9; transition: background 0.2s;";
    $cellStyle = "padding: 18px; vertical-align: top;";
    $indentStyle = $isChild ? "padding-left: 50px; background: #fdfdfd;" : "";
    
    $statusKey = 'status_' . $c['status']; 
    $statusLabel = $cLang[$statusKey] ?? ucfirst($c['status']);
    $statusColor = '#edf2f7'; $textColor = '#4a5568';
    if($c['status'] === 'approved') { $statusColor = '#c6f6d5'; $textColor = '#22543d'; }
    if($c['status'] === 'spam') { $statusColor = '#fed7d7'; $textColor = '#822727'; }

    $arrowIcon = $isChild ? '<span style="color:#cbd5e0; margin-right:10px; font-size:1.2rem;">↳</span>' : '';

    ob_start(); 
    ?>
    <tr style="<?= $rowStyle ?> <?= $isChild ? 'background-color: #fafafa;' : '' ?>" onmouseover="this.style.background='#fcfcfd'" onmouseout="this.style.background='<?= $isChild ? '#fafafa' : 'transparent' ?>'">
        <td style="<?= $cellStyle ?> <?= $indentStyle ?>">
            <div style="display:flex;">
                <?= $arrowIcon ?>
                <div style="flex:1;">
                    <div style="font-weight: bold; color: #2d3748; font-size: 1rem;">
                        <?= htmlspecialchars($c['author_name']) ?>
                        <?php if($isChild): ?><span style="font-size:0.7em; color:#a0aec0; font-weight:normal;">(Antwort)</span><?php endif; ?>
                    </div>
                    
                    <div id="content-view-<?= $c['id'] ?>">
                        <div style="font-size: 0.95rem; color: #4a5568; margin-top: 6px; line-height: 1.5;">
                            <?= nl2br(htmlspecialchars($c['content'])) ?>
                        </div>
                    </div>

                    <form method="post" id="edit-form-<?= $c['id'] ?>" class="inline-editor">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="action" value="update_content">
                        <textarea name="content" style="width: 100%; height: 80px; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: inherit; margin-bottom: 5px;"><?= htmlspecialchars($c['content']) ?></textarea>
                        <button type="submit" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: #3182ce; color: #fff; border:none;"><?= htmlspecialchars($cLang['btn_save'] ?? 'Save') ?></button>
                        <button type="button" class="btn" style="padding: 4px 10px; font-size: 0.8rem;" onclick="toggleEdit(<?= $c['id'] ?>)"><?= htmlspecialchars($cLang['btn_cancel'] ?? 'Cancel') ?></button>
                    </form>

                    <form method="post" id="reply-form-<?= $c['id'] ?>" class="reply-box reply-wrapper">
                        <div style="font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; color: #2b6cb0;">
                            <?= htmlspecialchars($cLang['label_reply_as'] ?? 'Reply as Admin') ?>:
                        </div>
                        <input type="hidden" name="parent_id" value="<?= $isChild ? $c['parent_id'] : $c['id'] ?>"> 
                        <input type="hidden" name="post_id" value="<?= $c['post_id'] ?>">
                        <input type="hidden" name="action" value="reply">
                        <textarea name="reply_content" style="width: 100%; height: 60px; padding: 8px; border: 1px solid #90cdf4; border-radius: 6px; font-family: inherit; margin-bottom: 5px;" placeholder="<?= htmlspecialchars($cLang['placeholder_reply'] ?? 'Write a reply...') ?>"></textarea>
                        <button type="submit" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: #3182ce; color: #fff; border:none;"><?= htmlspecialchars($cLang['btn_reply'] ?? 'Reply') ?></button>
                        <button type="button" class="btn" style="padding: 4px 10px; font-size: 0.8rem;" onclick="toggleReply(<?= $c['id'] ?>)"><?= htmlspecialchars($cLang['btn_cancel'] ?? 'Cancel') ?></button>
                    </form>

                    <div style="font-size: 11px; color: #a0aec0; margin-top: 8px;">
                        In: <strong><a href="/article.php?id=<?= $c['post_id'] ?>" target="_blank" style="text-decoration:none; color:inherit;"><?= htmlspecialchars($c['post_title']) ?></a></strong> • <?= date($t['common']['date_fmt_full'] ?? 'd.m.Y H:i', strtotime($c['created_at'])) ?>
                    </div>
                </div>
            </div>
        </td>
        <td style="<?= $cellStyle ?>">
            <span style="padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; background: <?= $statusColor ?>; color: <?= $textColor ?>; display: inline-block;">
                <?= htmlspecialchars($statusLabel) ?>
            </span>
        </td>
        <td style="<?= $cellStyle ?> text-align: right; white-space: nowrap;">
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                
                <button type="button" class="action-icon" title="<?= htmlspecialchars($cLang['btn_edit'] ?? 'Edit') ?>" onclick="toggleEdit(<?= $c['id'] ?>)">✏️</button>
                <button type="button" class="action-icon" title="<?= htmlspecialchars($cLang['btn_reply'] ?? 'Reply') ?>" onclick="toggleReply(<?= $c['id'] ?>)">↩️</button>

                <?php if ($c['status'] !== 'approved'): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="action-icon" style="color: #38a169;" title="<?= htmlspecialchars($cLang['button_approve'] ?? 'Approve') ?>">✅</button>
                    </form>
                <?php endif; ?>

                <?php if ($c['status'] !== 'spam'): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="action" value="spam">
                        <button type="submit" class="action-icon" style="color: #d69e2e;" title="<?= htmlspecialchars($cLang['button_spam'] ?? 'Spam') ?>">🚫</button>
                    </form>
                <?php endif; ?>

                <form method="post" onsubmit="return confirm('<?= htmlspecialchars($cLang['confirm_delete'] ?? 'Delete?') ?>')" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="action-icon" style="color: #e53e3e;" title="<?= htmlspecialchars($cLang['button_delete'] ?? 'Delete') ?>">🗑️</button>
                </form>
            </div>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

include 'header.php'; 
?>

<style>
    .inline-editor, .reply-box { display: none; margin-top: 10px; }
    .inline-editor.active, .reply-box.active { display: block; }
    .action-icon { background: none; border: none; cursor: pointer; font-size: 1.1rem; padding: 5px; transition: transform 0.2s; }
    .action-icon:hover { transform: scale(1.2); }
    .reply-wrapper { background: #f8fafc; border-left: 3px solid #3182ce; padding: 10px; border-radius: 4px; margin-top: 10px; }
</style>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1500px;">
            
            <header style="margin-bottom: 30px;">
                <h1 style="margin:0; font-size: 2rem; color: #1a202c;">
                    <?= htmlspecialchars($cLang['manage_title'] ?? 'Manage Comments') ?>
                </h1>
            </header>

            <div class="card" style="padding: 0; overflow: hidden; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 12px; border-top: 4px solid #3182ce;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 18px; text-align: left; font-size: 11px; color: #718096; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($cLang['col_author_content'] ?? 'Author & Content') ?>
                            </th>
                            <th style="padding: 18px; text-align: left; font-size: 11px; color: #718096; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($cLang['col_status'] ?? 'Status') ?>
                            </th>
                            <th style="padding: 18px; text-align: right; font-size: 11px; color: #718096; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($cLang['col_actions'] ?? 'Actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allComments)): ?>
                            <tr>
                                <td colspan="3" style="padding: 50px; text-align: center; color: #a0aec0;">
                                    <?= htmlspecialchars($cLang['no_comments'] ?? 'No comments found.') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($parents as $p): ?>
                                <?= renderCommentRow($p, $cLang, $t, false) ?>
                                
                                <?php if (isset($children[$p['id']])): ?>
                                    <?php 
                                        usort($children[$p['id']], function($a, $b) {
                                            return strtotime($a['created_at']) - strtotime($b['created_at']);
                                        });
                                        foreach ($children[$p['id']] as $child): 
                                    ?>
                                        <?= renderCommentRow($child, $cLang, $t, true) ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEdit(id) {
    const contentDiv = document.getElementById('content-view-' + id);
    const form = document.getElementById('edit-form-' + id);
    if (form.classList.contains('active')) {
        form.classList.remove('active');
        contentDiv.style.display = 'block';
    } else {
        form.classList.add('active');
        contentDiv.style.display = 'none';
        document.getElementById('reply-form-' + id).classList.remove('active');
    }
}

function toggleReply(id) {
    const form = document.getElementById('reply-form-' + id);
    if (form.classList.contains('active')) {
        form.classList.remove('active');
    } else {
        form.classList.add('active');
        document.getElementById('edit-form-' + id).classList.remove('active');
        document.getElementById('content-view-' + id).style.display = 'block';
    }
}
</script>

<?php include 'footer.php'; ?>