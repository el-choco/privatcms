<?php
declare(strict_types=1);
session_start();

$userRole = $_SESSION['admin']['role'] ?? 'viewer';
if (empty($_SESSION['admin']) || $userRole !== 'admin') { 
    header('Location: /admin/'); exit; 
}

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$uLang = $t['users'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($id !== (int)$_SESSION['admin']['id']) { 
        if ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            try {
                $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'delete_user', ?, ?)")
                    ->execute([$_SESSION['admin']['id'], "Deleted user ID $id", $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (Exception $e) {}
        }
    }
    header("Location: users.php"); exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

require_once 'header.php'; 
?>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1500px;">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin:0; font-size: 1.5rem; color: #1a202c;">
                    <?= htmlspecialchars($uLang['title'] ?? 'User Management') ?>
                </h1>
                <a href="user-edit.php" class="btn btn-primary" style="font-weight: bold;">
                    <?= htmlspecialchars($uLang['btn_new'] ?? 'New User') ?>
                </a>
            </header>

            <div class="card" style="border-top: 4px solid #3182ce;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; color: #718096;">ID</th>
                            <th style="padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; color: #718096;"><?= htmlspecialchars($uLang['col_username'] ?? 'Username') ?></th>
                            <th style="padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; color: #718096;"><?= htmlspecialchars($uLang['col_email'] ?? 'Email') ?></th>
                            <th style="padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; color: #718096;"><?= htmlspecialchars($uLang['col_role'] ?? 'Role') ?></th>
                            <th style="padding: 15px; text-align: right; font-size: 12px; text-transform: uppercase; color: #718096;"><?= htmlspecialchars($uLang['col_actions'] ?? 'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 15px;">#<?= $u['id'] ?></td>
                            <td style="padding: 15px; font-weight: bold;"><?= htmlspecialchars($u['username']) ?></td>
                            <td style="padding: 15px;"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                            <td style="padding: 15px;">
                                <span style="background: #ebf8ff; color: #2b6cb0; padding: 3px 8px; border-radius: 10px; font-size: 0.85rem; font-weight: bold;">
                                    <?= htmlspecialchars($t['roles'][$u['role']] ?? $u['role']) ?>
                                </span>
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <a href="user-edit.php?id=<?= $u['id'] ?>" class="btn" style="background: #fff; border: 1px solid #e2e8f0; font-size: 1rem; padding: 5px 10px; border-radius: 6px;">✏️</a>
                                <?php if ((int)$u['id'] !== (int)$_SESSION['admin']['id']): ?>
                                <form method="post" onsubmit="return confirm('<?= htmlspecialchars($uLang['confirm_delete'] ?? 'Delete user?') ?>')" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn" style="background: #fff; border: 1px solid #fed7d7; color: #e53e3e; font-size: 1rem; padding: 5px 10px; border-radius: 6px;">🗑️</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 40px;">
                <h3 style="color: #4a5568; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px;">
                    <?= htmlspecialchars($uLang['perm_title'] ?? 'Permissions Overview') ?>
                </h3>
                <div class="card" style="overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse; text-align: center;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th style="padding: 12px; text-align: left; width: 40%; color: #4a5568;"><?= htmlspecialchars($uLang['perm_action'] ?? 'Action') ?></th>
                                <th style="padding: 12px; width: 20%; color: #718096;">Viewer</th>
                                <th style="padding: 12px; width: 20%; color: #3182ce;">Editor</th>
                                <th style="padding: 12px; width: 20%; color: #2f855a;">Admin</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 0.95rem;">
                            <tr style="border-bottom: 1px solid #f7fafc;">
                                <td style="padding: 10px; text-align: left;"><?= htmlspecialchars($uLang['p_login'] ?? 'Admin Login') ?></td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #38a169;">✅</td>
                                <td style="color: #38a169;">✅</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f7fafc;">
                                <td style="padding: 10px; text-align: left;"><?= htmlspecialchars($uLang['p_own_posts'] ?? 'Create/Edit Own Posts') ?></td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #38a169;">✅</td>
                                <td style="color: #38a169;">✅</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f7fafc;">
                                <td style="padding: 10px; text-align: left;"><?= htmlspecialchars($uLang['p_files'] ?? 'Upload Files') ?></td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #38a169;">✅</td>
                                <td style="color: #38a169;">✅</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f7fafc;">
                                <td style="padding: 10px; text-align: left;"><?= htmlspecialchars($uLang['p_publish'] ?? 'Publish/Stick Posts') ?></td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #38a169;"><?= htmlspecialchars($uLang['p_own_only'] ?? 'Own only') ?></td>
                                <td style="color: #38a169;">✅</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f7fafc;">
                                <td style="padding: 10px; text-align: left;"><?= htmlspecialchars($uLang['p_categories'] ?? 'Manage Categories') ?></td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #38a169;">✅</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f7fafc;">
                                <td style="padding: 10px; text-align: left;"><?= htmlspecialchars($uLang['p_comments'] ?? 'Manage Comments') ?></td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #38a169;">✅</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f7fafc;">
                                <td style="padding: 10px; text-align: left;"><?= htmlspecialchars($uLang['p_users'] ?? 'Manage Users') ?></td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #38a169;">✅</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; text-align: left;"><?= htmlspecialchars($uLang['p_settings'] ?? 'System Settings') ?></td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #e53e3e;">❌</td>
                                <td style="color: #38a169;">✅</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include 'footer.php'; ?>