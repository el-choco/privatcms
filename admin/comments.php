<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
use App\Database;
use App\I18n;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$i18n = I18n::fromConfig($ini, $_GET['lang'] ?? null);
$pdo = (new Database($ini['database'] ?? []))->pdo();

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// --- AKTIONEN (Approve, Spam, Delete) ---
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

<header class="top-header">
    <h1>Kommentare verwalten</h1>
</header>

<div class="content-area">
    <div class="card">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 15px; text-align: left; font-size: 12px; color: #718096;">AUTOR & INHALT</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; color: #718096;">STATUS</th>
                    <th style="padding: 15px; text-align: right; font-size: 12px; color: #718096;">AKTIONEN</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comments)): ?>
                    <tr><td colspan="3" style="padding: 50px; text-align: center; color: #a0aec0;">Keine Kommentare vorhanden.</td></tr>
                <?php else: ?>
                    <?php foreach ($comments as $c): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 15px;">
                            <div style="font-weight: bold; color: #2d3748;"><?= htmlspecialchars($c['author_name']) ?></div>
                            <div style="font-size: 13px; color: #4a5568; margin-top: 4px;"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                            <div style="font-size: 11px; color: #a0aec0; margin-top: 8px;"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></div>
                        </td>
                        <td style="padding: 15px; vertical-align: top;">
                            <?php 
                                $statusColor = '#edf2f7'; $textColor = '#4a5568';
                                if($c['status'] === 'approved') { $statusColor = '#c6f6d5'; $textColor = '#22543d'; }
                                if($c['status'] === 'spam') { $statusColor = '#fed7d7'; $textColor = '#822727'; }
                            ?>
                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; background: <?= $statusColor ?>; color: <?= $textColor ?>;">
                                <?= ucfirst($c['status']) ?>
                            </span>
                        </td>
                        <td style="padding: 15px; text-align: right; vertical-align: top;">
                            <div style="display: flex; justify-content: flex-end; gap: 5px;">
                                <?php if ($c['status'] !== 'approved'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn" title="Freischalten">✅</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($c['status'] !== 'spam'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="action" value="spam">
                                        <button type="submit" class="btn" title="Spam">🚫</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" onsubmit="return confirm('Wirklich löschen?')" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger" title="Löschen">🗑️</button>
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

<?php include 'footer.php'; ?>