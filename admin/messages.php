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

if (isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_id']]);
}

$messages = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC")->fetchAll();

include 'header.php';
?>

<div class="content-area">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="margin:0;"><?= htmlspecialchars($t['messages']['title'] ?? 'Inbox') ?></h1>
    </div>

    <div class="card">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="text-align: left; background: #f7fafc; border-bottom: 2px solid #edf2f7;">
                    <th style="padding: 12px 20px; color: #718096;"><?= htmlspecialchars($t['messages']['col_date'] ?? 'Date') ?></th>
                    <th style="padding: 12px 20px; color: #718096;"><?= htmlspecialchars($t['messages']['col_from'] ?? 'From') ?></th>
                    <th style="padding: 12px 20px; color: #718096;"><?= htmlspecialchars($t['messages']['col_subject'] ?? 'Subject') ?></th>
                    <th style="padding: 12px 20px; color: #718096; text-align:right;"><?= htmlspecialchars($t['common']['actions'] ?? 'Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr><td colspan="4" style="padding: 30px; text-align: center; color: #a0aec0;"><?= htmlspecialchars($t['messages']['no_messages'] ?? 'No messages.') ?></td></tr>
                <?php else: ?>
                    <?php foreach($messages as $m): ?>
                    <tr style="border-bottom: 1px solid #edf2f7;">
                        <td style="padding: 15px 20px; vertical-align:top; white-space:nowrap; width:140px; color:#718096;">
                            <?= date('d.m.Y H:i', strtotime($m['created_at'])) ?>
                        </td>
                        <td style="padding: 15px 20px; vertical-align:top; width:200px;">
                            <div style="font-weight:bold;"><?= htmlspecialchars($m['name']) ?></div>
                            <a href="mailto:<?= htmlspecialchars($m['email']) ?>" style="font-size:0.85rem; color:#3182ce; text-decoration:none;"><?= htmlspecialchars($m['email']) ?></a>
                        </td>
                        <td style="padding: 15px 20px; vertical-align:top;">
                            <div style="font-weight:bold; margin-bottom:5px; color:#2d3748;"><?= htmlspecialchars($m['subject']) ?></div>
                            <div style="color:#4a5568; line-height:1.5; font-family:monospace; background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #edf2f7;">
                                <?= nl2br(htmlspecialchars($m['content'])) ?>
                            </div>
                        </td>
                        <td style="padding: 15px 20px; text-align:right; vertical-align:top;">
                            <form method="POST" onsubmit="return confirm('<?= htmlspecialchars($t['common']['confirm_delete'] ?? 'Delete?') ?>');">
                                <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                                <button type="submit" style="background:none; border:none; cursor:pointer; font-size:1.2rem;" title="Löschen">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>