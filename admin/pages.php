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
    $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_id']]);
}

$pages = $pdo->query("SELECT * FROM pages ORDER BY title ASC")->fetchAll();

include 'header.php';
?>

<div class="content-area" style="margin: 30px 30px 30px 30px;">    
    <div style="display:flex; justify-content:space-between; align-items:center; margin: 20px 10px 20px 15px;">
        <h1 style="margin:0;"><?= htmlspecialchars($t['pages']['title'] ?? 'Pages') ?></h1>
        <a href="page-edit.php" class="btn btn-primary">+ <?= htmlspecialchars($t['pages']['create_new'] ?? 'New Page') ?></a>
    </div>

    <div class="card" style="border-top: 5px solid #3182ce;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="text-align: left; background: #f7fafc; border-bottom: 2px solid #edf2f7;">
                    <th style="padding: 12px 20px; color: #718096;"><?= htmlspecialchars($t['common']['title'] ?? 'Title') ?></th>
                    <th style="padding: 12px 20px; color: #718096;">URL (Slug)</th>
                    <th style="padding: 12px 20px; color: #718096;">Status</th>
                    <th style="padding: 12px 20px; color: #718096; text-align:right;"><?= htmlspecialchars($t['common']['actions'] ?? 'Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pages as $p): ?>
                <tr style="border-bottom: 1px solid #edf2f7;">
                    <td style="padding: 15px 20px; font-weight:500;">
                        <a href="page-edit.php?id=<?= $p['id'] ?>" style="text-decoration:none; color:#2d3748;"><?= htmlspecialchars($p['title']) ?></a>
                    </td>
                    <td style="padding: 15px 20px; color:#718096; font-family:monospace;">/p/<?= htmlspecialchars($p['slug']) ?></td>
                    <td style="padding: 15px 20px;">
                        <span style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; background: <?= $p['status']==='published'?'#c6f6d5':'#feebc8' ?>; color: <?= $p['status']==='published'?'#22543d':'#744210' ?>;">
                            <?= $p['status'] ?>
                        </span>
                    </td>
                    <td style="padding: 15px 20px; text-align:right;">
                        <a href="/p/<?= htmlspecialchars($p['slug']) ?>" target="_blank" style="margin-right:10px; text-decoration:none;">👁️</a>
                        <a href="page-edit.php?id=<?= $p['id'] ?>" style="margin-right:10px; text-decoration:none;">✏️</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?= htmlspecialchars($t['common']['confirm_delete'] ?? 'Delete?') ?>');">
                            <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                            <button type="submit" style="background:none; border:none; cursor:pointer;">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>