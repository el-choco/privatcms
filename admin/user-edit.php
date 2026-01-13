<?php
declare(strict_types=1);
session_start();

$userRole = $_SESSION['admin']['role'] ?? 'viewer';
if (empty($_SESSION['admin']) || $userRole !== 'admin') { header('Location: /admin/'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$uLang = $t['users'] ?? [];
$rLang = $t['roles'] ?? [];

$id = (int)($_GET['id'] ?? 0);
$user = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) { header('Location: users.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'viewer';
    $password = $_POST['password'] ?? '';

    if ($id > 0) {
        $sql = "UPDATE users SET username=?, email=?, role=? WHERE id=?";
        $params = [$username, $email, $role, $id];
        if (!empty($password)) {
            $sql = "UPDATE users SET username=?, email=?, role=?, password_hash=? WHERE id=?";
            $params = [$username, $email, $role, password_hash($password, PASSWORD_DEFAULT), $id];
        }
        $pdo->prepare($sql)->execute($params);
        $action = 'update_user';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, role, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $role, password_hash($password, PASSWORD_DEFAULT)]);
        $action = 'create_user';
    }

    try {
        $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)")
            ->execute([$_SESSION['admin']['id'], $action, "User: $username", $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}

    header('Location: users.php'); exit;
}

require_once 'header.php';
?>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 40px;">
        <div class="card" style="width: 100%; max-width: 600px; padding: 40px; border-top: 4px solid #3182ce;">
            <h1 style="margin: 0 0 30px 0; text-align: center; color: #1a202c;">
                <?= htmlspecialchars($id > 0 ? ($uLang['title_edit'] ?? 'Edit User') : ($uLang['title_new'] ?? 'New User')) ?>
            </h1>
            
            <form method="post">
                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold; color:#4a5568;">
                        <?= htmlspecialchars($uLang['label_username'] ?? 'Username') ?>
                    </label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($user['username'] ?? '') ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:8px;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold; color:#4a5568;">
                        <?= htmlspecialchars($uLang['label_email'] ?? 'Email') ?>
                    </label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:8px;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold; color:#4a5568;">
                        <?= htmlspecialchars($uLang['label_role'] ?? 'Role') ?>
                    </label>
                    <select name="role" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:8px; background:white;">
                        <option value="member" <?= ($user['role']??'') === 'member' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rLang['member'] ?? 'Member') ?>
                        </option>
                        <option value="viewer" <?= ($user['role']??'') === 'viewer' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rLang['viewer'] ?? 'Viewer') ?>
                        </option>
                        <option value="editor" <?= ($user['role']??'') === 'editor' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rLang['editor'] ?? 'Editor') ?>
                        </option>
                        <option value="admin" <?= ($user['role']??'') === 'admin' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rLang['admin'] ?? 'Admin') ?>
                        </option>
                    </select>
                </div>
                <div style="margin-bottom: 30px;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold; color:#4a5568;">
                        <?= htmlspecialchars($uLang['label_password'] ?? 'Password') ?> 
                        <?= $id>0 ? '<span style="font-weight:normal; font-size:0.9em;">('. htmlspecialchars($uLang['password_hint'] ?? 'leave empty to keep current') .')</span>' : '' ?>
                    </label>
                    <input type="password" name="password" <?= $id===0 ? 'required' : '' ?> style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:8px;">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; padding:15px;">
                    <?= htmlspecialchars($uLang['btn_save'] ?? 'Save User') ?>
                </button>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>