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
$pLang = $t_temp['posts'] ?? [];
$cLang = $t_temp['common'] ?? [];

$currentUser = $_SESSION['admin'];
$isAdmin = ($currentUser['role'] ?? 'viewer') === 'admin';
$currentUserId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT author_id FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if ($post) {
            $isOwner = (int)($post['author_id'] ?? 0) === $currentUserId;
            
            if ($isAdmin || $isOwner) {
                if ($_POST['action'] === 'delete') {
                    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
                    try {
                        $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'delete', ?, ?)");
                        $log->execute([$currentUserId, "Deleted post ID $id", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    } catch (Exception $e) {}
                } elseif ($_POST['action'] === 'publish') {
                    $pdo->prepare("UPDATE posts SET status = 'published' WHERE id = ?")->execute([$id]);
                    try {
                        $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'publish', ?, ?)");
                        $log->execute([$currentUserId, "Published post ID $id", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    } catch (Exception $e) {}
                } elseif ($_POST['action'] === 'unpublish') {
                    $pdo->prepare("UPDATE posts SET status = 'draft' WHERE id = ?")->execute([$id]);
                    try {
                        $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'unpublish', ?, ?)");
                        $log->execute([$currentUserId, "Unpublished post ID $id", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    } catch (Exception $e) {}
                } elseif ($_POST['action'] === 'toggle_sticky') {
                    $pdo->prepare("UPDATE posts SET is_sticky = 1 - is_sticky WHERE id = ?")->execute([$id]);
                    try {
                        $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'toggle_sticky', ?, ?)");
                        $log->execute([$currentUserId, "Toggled sticky for post ID $id", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    } catch (Exception $e) {}
                }
            }
        }
    }
    header("Location: posts.php"); exit;
}

$sql = "SELECT p.*, c.name as category_name 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.id ";

if (!$isAdmin) {
    $sql .= "WHERE p.author_id = " . $currentUserId . " ";
}

$sql .= "ORDER BY p.is_sticky DESC, p.created_at DESC";
$posts = $pdo->query($sql)->fetchAll();

require_once 'header.php'; 
?>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1600px;">

            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin:0; font-size: 2rem; color: #1a202c;">
                    <?= htmlspecialchars($pLang['manage_title'] ?? 'Manage Posts') ?>
                </h1>
                <a href="post-create.php" class="btn btn-primary" style="font-weight: bold;">
                    <?= htmlspecialchars($pLang['button_new'] ?? 'New Post') ?>
                </a>
            </header>

            <div class="card" style="overflow-x: auto; border-top: 4px solid #3182ce;">
                <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($pLang['col_title_cat'] ?? 'Title') ?>
                            </th>
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($pLang['col_status'] ?? 'Status') ?>
                            </th>
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($pLang['col_date'] ?? 'Date') ?>
                            </th>
                            <th style="padding: 15px; text-align: right; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($pLang['col_actions'] ?? 'Actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                            <tr>
                                <td colspan="4" style="padding: 40px; text-align: center; color: #a0aec0;">
                                    <?= htmlspecialchars($pLang['no_posts'] ?? 'No posts found.') ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($posts as $p): ?>
                        <?php $isSticky = (bool)($p['is_sticky'] ?? false); ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s; <?= $isSticky ? 'background: #fffdf5;' : '' ?>" 
                            onmouseover="this.style.background='#f8fafc'" 
                            onmouseout="this.style.background='<?= $isSticky ? '#fffdf5' : 'transparent' ?>'">
                            <td style="padding: 15px;">
                                <div style="font-weight: bold; font-size: 1.05rem; color: #2d3748; margin-bottom: 4px;">
                                    <?php if ($isSticky): ?>
                                        <span title="<?= htmlspecialchars($pLang['tooltip_sticky'] ?? 'Pinned') ?>">📌</span> 
                                    <?php endif; ?>
                                    <?= htmlspecialchars($p['title']) ?>
                                </div>
                                <?php if ($p['category_name']): ?>
                                    <span style="display: inline-block; font-size: 10px; background: #ebf8ff; color: #2b6cb0; padding: 2px 8px; border-radius: 4px; border: 1px solid #bee3f8; text-transform: uppercase; font-weight: 700;">
                                        📁 <?= htmlspecialchars($p['category_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; font-size: 10px; background: #f7fafc; color: #a0aec0; padding: 2px 8px; border-radius: 4px; border: 1px solid #edf2f7; text-transform: uppercase; font-weight: 400;">
                                        <?= htmlspecialchars($pLang['no_category'] ?? 'No Category') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px;">
                                <?php 
                                    $statusKey = 'status_' . $p['status'];
                                    $statusLabel = $pLang[$statusKey] ?? ucfirst($p['status']);
                                ?>
                                <span style="padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; 
                                      background: <?= $p['status']==='published'?'#c6f6d5':'#feebc8' ?>; 
                                      color: <?= $p['status']==='published'?'#22543d':'#744210' ?>;">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </td>
                            <td style="padding: 15px; color: #718096; font-size: 14px;">
                                <?= date($cLang['date_fmt'] ?? 'd.m.Y', strtotime($p['created_at'])) ?>
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="action" value="toggle_sticky">
                                        <button type="submit" class="btn" style="background: #fff; border: 1px solid #e2e8f0; font-size: 1.2rem; padding: 5px 10px; border-radius: 6px;" 
                                                title="<?= htmlspecialchars($isSticky ? ($pLang['tooltip_unstick'] ?? 'Unpin') : ($pLang['tooltip_stick'] ?? 'Pin')) ?>">
                                            <?= $isSticky ? '📍' : '📌' ?>
                                        </button>
                                    </form>

                                    <a href="post-edit.php?id=<?= $p['id'] ?>" class="btn" 
                                       title="<?= htmlspecialchars($pLang['button_edit'] ?? 'Edit') ?>" 
                                       style="background: #fff; border: 1px solid #e2e8f0; font-size: 1.2rem; padding: 5px 10px; border-radius: 6px;">✏️</a>
                                    
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="action" value="<?= $p['status']==='published'?'unpublish':'publish' ?>">
                                        <button type="submit" class="btn" style="background: #fff; border: 1px solid #e2e8f0; font-size: 1.2rem; padding: 5px 10px; border-radius: 6px;" 
                                                title="<?= htmlspecialchars($p['status']==='published' ? ($pLang['tooltip_unpublish'] ?? 'Unpublish') : ($pLang['tooltip_publish'] ?? 'Publish')) ?>">
                                            <?= $p['status'] === 'published' ? '📦' : '🚀' ?>
                                        </button>
                                    </form>

                                    <form method="post" onsubmit="return confirm('<?= htmlspecialchars($pLang['confirm_delete'] ?? 'Delete?') ?>')" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn" style="background: #fff; border: 1px solid #fed7d7; color: #e53e3e; font-size: 1.2rem; padding: 5px 10px; border-radius: 6px;" 
                                                title="<?= htmlspecialchars($pLang['button_delete'] ?? 'Delete') ?>">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>