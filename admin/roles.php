<?php
declare(strict_types=1);
session_start();

$userRole = $_SESSION['admin']['role'] ?? 'viewer';
if (empty($_SESSION['admin']) || ($userRole !== 'admin' && $userRole !== 1)) { 
    header('Location: /admin/'); exit; 
}

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$rLang = $t['roles'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($_POST['action'] === 'delete') {
        $check = $pdo->prepare("SELECT is_system FROM roles WHERE id = ?");
        $check->execute([$id]);
        $role = $check->fetch();
        if ($role && $role['is_system'] == 0) {
            $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
        }
    }
    header("Location: roles.php"); exit;
}

$roles = $pdo->query("
    SELECT r.*, COUNT(u.id) as user_count 
    FROM roles r 
    LEFT JOIN users u ON u.role_id = r.id 
    GROUP BY r.id 
    ORDER BY r.id ASC
")->fetchAll();

require_once 'header.php'; 
?>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1200px;">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin:0; font-size: 2rem; color: #1a202c;">
                    <?= htmlspecialchars($rLang['title_manage'] ?? 'Rollen & Rechte') ?>
                </h1>
                <div style="display:flex; gap:10px;">
                    <a href="users.php" class="btn" style="background: #fff; border: 1px solid #cbd5e0; color: #4a5568;">
                        🔙 <?= htmlspecialchars($rLang['back_to_users'] ?? 'Zurück zu Usern') ?>
                    </a>
                    <a href="role-edit.php" class="btn btn-primary" style="font-weight: bold;">
                        <?= htmlspecialchars($rLang['btn_new_role'] ?? 'Neue Rolle') ?>
                    </a>
                </div>
            </header>

            <div class="card" style="border-top: 5px solid #805ad5;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 15px; text-align: left; color: #718096;">ID</th>
                            <th style="padding: 15px; text-align: left; color: #718096;">Name</th>
                            <th style="padding: 15px; text-align: left; color: #718096;">User</th>
                            <th style="padding: 15px; text-align: right; color: #718096;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $r): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 15px;">#<?= $r['id'] ?></td>
                            <td style="padding: 15px;">
                                <span style="background: <?= htmlspecialchars($r['color']) ?>; color: #fff; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.9rem;">
                                    <?= htmlspecialchars($r['label']) ?>
                                </span>
                                <div style="font-size: 0.8rem; color: #a0aec0; margin-top: 4px;">
                                    <?= htmlspecialchars($r['description'] ?? '') ?>
                                </div>
                            </td>
                            <td style="padding: 15px;">
                                <?= $r['user_count'] ?> User
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <a href="role-edit.php?id=<?= $r['id'] ?>" class="btn" style="background: #fff; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px;">⚙️ Rechte</a>
                                
                                <?php if ($r['is_system'] == 0): ?>
                                <form method="post" onsubmit="return confirm('Wirklich löschen?')" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn" style="background: #fff; border: 1px solid #fed7d7; color: #e53e3e; padding: 5px 10px; border-radius: 6px; margin-left: 5px;">🗑️</button>
                                </form>
                                <?php endif; ?>
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