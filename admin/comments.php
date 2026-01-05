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

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'approve') {
            $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'spam') {
            $pdo->prepare("UPDATE comments SET status = 'spam' WHERE id = ?")->execute([$id]);
        }
    }
    header("Location: comments.php");
    exit;
}

$comments = $pdo->query("SELECT * FROM comments ORDER BY created_at DESC")->fetchAll();

include 'header.php'; 
?>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1000px;">
            
            <header style="margin-bottom: 30px;">
                <h1 style="margin:0; font-size: 1.5rem; color: #1a202c;">
                    <?= htmlspecialchars($t['comments']['manage_title']) ?>
                </h1>
            </header>

            <div class="card" style="padding: 0; overflow: hidden; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 12px; border-top: 4px solid #805ad5;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 18px; text-align: left; font-size: 11px; color: #718096; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($t['comments']['col_author_content']) ?>
                            </th>
                            <th style="padding: 18px; text-align: left; font-size: 11px; color: #718096; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($t['comments']['col_status']) ?>
                            </th>
                            <th style="padding: 18px; text-align: right; font-size: 11px; color: #718096; text-transform: uppercase; letter-spacing: 1px;">
                                <?= htmlspecialchars($t['comments']['col_actions']) ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comments)): ?>
                            <tr>
                                <td colspan="3" style="padding: 50px; text-align: center; color: #a0aec0;">
                                    <?= htmlspecialchars($t['comments']['no_comments']) ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($comments as $c): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfd'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 18px;">
                                    <div style="font-weight: bold; color: #2d3748; font-size: 1rem;"><?= htmlspecialchars($c['author_name']) ?></div>
                                    <div style="font-size: 0.95rem; color: #4a5568; margin-top: 6px; line-height: 1.5;">
                                        <?= nl2br(htmlspecialchars($c['content'])) ?>
                                    </div>
                                    <div style="font-size: 11px; color: #a0aec0; margin-top: 8px;">
                                        <?= date($t['common']['date_fmt_full'] ?? 'd.m.Y H:i', strtotime($c['created_at'])) ?>
                                    </div>
                                </td>
                                <td style="padding: 18px; vertical-align: top;">
                                    <?php 
                                        $statusKey = 'status_' . $c['status']; 
                                        $statusLabel = $t['comments'][$statusKey] ?? ucfirst($c['status']);
                                        
                                        $statusColor = '#edf2f7'; $textColor = '#4a5568';
                                        if($c['status'] === 'approved') { $statusColor = '#c6f6d5'; $textColor = '#22543d'; }
                                        if($c['status'] === 'spam') { $statusColor = '#fed7d7'; $textColor = '#822727'; }
                                    ?>
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; background: <?= $statusColor ?>; color: <?= $textColor ?>; display: inline-block;">
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </span>
                                </td>
                                <td style="padding: 18px; text-align: right; vertical-align: top;">
                                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                        <?php if ($c['status'] !== 'approved'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn" style="padding: 6px 12px;" title="<?= htmlspecialchars($t['comments']['button_approve']) ?>">✅</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($c['status'] !== 'spam'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <input type="hidden" name="action" value="spam">
                                                <button type="submit" class="btn" style="padding: 6px 12px;" title="<?= htmlspecialchars($t['comments']['button_spam']) ?>">🚫</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" onsubmit="return confirm('<?= htmlspecialchars($t['comments']['confirm_delete']) ?>')" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn" style="padding: 6px 12px; border-color: #feb2b2; color: #c53030;" title="<?= htmlspecialchars($t['comments']['button_delete']) ?>">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>