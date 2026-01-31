<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$settings = [];
try {
    $stmtSettings = $pdo->query("SELECT * FROM settings");
    while ($row = $stmtSettings->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$pLang = $iniLang['profile'] ?? [];
$fLang = $iniLang['forum'] ?? [];

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$msg = '';
$msgType = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_FILES['avatar_upload']) && $_FILES['avatar_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar_upload']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['avatar_upload']['tmp_name'];
            $fileName = $_FILES['avatar_upload']['name'];
            $fileSize = $_FILES['avatar_upload']['size'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if ($fileSize > 5 * 1024 * 1024) {
                $msg = $pLang['error_file_size'] ?? 'File too large (Max 5MB)';
                $msgType = 'error';
            } elseif (!in_array($fileExt, $allowed)) {
                $msg = $pLang['error_file_type'] ?? 'Invalid file type (JPG, PNG, WEBP, GIF)';
                $msgType = 'error';
            } else {
                $uploadDir = __DIR__ . '/../public/uploads/';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

                if (!empty($user['avatar']) && file_exists($uploadDir . $user['avatar'])) {
                    @unlink($uploadDir . $user['avatar']);
                }

                $newFileName = 'avatar_' . $user['id'] . '_' . time() . '.' . $fileExt;
                
                if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                    $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$newFileName, $user['id']]);
                    $user['avatar'] = $newFileName; 
                    $msg = $pLang['avatar_updated'] ?? 'Avatar updated successfully';
                    $msgType = 'success';
                } else {
                    $msg = $pLang['error_upload'] ?? 'Upload failed (Check Permissions)';
                    $msgType = 'error';
                }
            }
        } else {
            $msg = $pLang['error_upload_code'] ?? 'Upload Error';
            $msgType = 'error';
        }
    }
    elseif (isset($_POST['delete_avatar'])) {
        if (!empty($user['avatar'])) {
            $path = __DIR__ . '/../public/uploads/' . $user['avatar'];
            if (file_exists($path)) { @unlink($path); }
            $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?")->execute([$user['id']]);
            $user['avatar'] = null;
            $msg = $pLang['avatar_removed'] ?? 'Avatar removed';
            $msgType = 'success';
        }
    }
    elseif (isset($_POST['email'])) {
        $email = trim($_POST['email'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $curPass = $_POST['current_password'] ?? '';

        if (!password_verify($curPass, $user['password_hash'])) {
            $msg = $pLang['error_password'] ?? 'Wrong password';
            $msgType = 'error';
        } else {
            try {
                $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $user['id']]);
                
                if (!empty($newPass)) {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user['id']]);
                }
                
                $msg = $pLang['success'] ?? 'Profile updated';
                $msgType = 'success';
                
                $stmt->execute([$user['id']]);
                $user = $stmt->fetch();
            } catch (Exception $e) {
                $msg = $pLang['error_general'] ?? 'Error updating profile';
                $msgType = 'error';
            }
        }
    }
}

function getAvatarProfile($u) {
    if (!empty($u['avatar'])) {
        return '<img src="/uploads/'.htmlspecialchars($u['avatar']).'?v='.time().'" class="avatar-img" alt="Avatar">';
    }
    $colors = ['#f56565', '#ed8936', '#ecc94b', '#48bb78', '#38b2ac', '#4299e1', '#667eea', '#9f7aea', '#ed64a6', '#a0aec0'];
    $name = $u['username'] ?? 'U';
    $val = 0;
    for ($i = 0; $i < strlen($name); $i++) { $val += ord($name[$i]); }
    $color = $colors[$val % count($colors)];
    $initial = strtoupper(substr($name, 0, 1));
    return '<div class="avatar-placeholder" style="background:'.$color.';">'.$initial.'</div>';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pLang['title'] ?? 'Profile') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="/assets/styles/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .profile-card { max-width: 600px; margin: 40px auto; background: var(--bg-card); padding: 40px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main); }
        .form-input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 1rem; box-sizing: border-box; background: var(--bg-body); color: var(--text-main); }
        .form-text { font-size: 0.85rem; color: var(--text-muted); margin-top: 5px; }
        .btn-primary { width: 100%; padding: 12px; background: var(--primary); color: #fff; border: none; border-radius: 6px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { opacity: 0.9; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: 500; }
        .alert.error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        .alert.success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .breadcrumb { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 12px 20px; margin-bottom: 25px; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; color: var(--text-muted); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .breadcrumb a { text-decoration: none; color: var(--primary); font-weight: 600; }

        /* Avatar Styles */
        .avatar-wrapper { position: relative; width: 120px; height: 120px; margin: 0 auto 20px auto; }
        .avatar-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .avatar-placeholder { width: 100%; height: 100%; border-radius: 50%; color: #fff; font-size: 48px; font-weight: bold; display: flex; align-items: center; justify-content: center; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .avatar-upload-btn { position: absolute; bottom: 0; right: 0; background: var(--primary); color: #fff; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: 0.2s; }
        .avatar-upload-btn:hover { transform: scale(1.1); }
        .del-link { display: inline-block; margin-top: 5px; color: #e53e3e; font-size: 0.85rem; text-decoration: none; cursor: pointer; background: none; border: none; }
        .del-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div class="profile-card">
        
        <form method="POST" enctype="multipart/form-data">
            <div class="avatar-wrapper">
                <?= getAvatarProfile($user) ?>
                
                <label for="avatarInput" class="avatar-upload-btn" title="<?= htmlspecialchars($pLang['upload_avatar'] ?? 'Upload Avatar') ?>">
                    <i class="fa-solid fa-camera"></i>
                </label>
                <input type="file" id="avatarInput" name="avatar_upload" style="display:none" onchange="this.form.submit()">
            </div>
            
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px;">
                <?= htmlspecialchars($pLang['avatar_hint'] ?? 'Max. 5 MB - JPG, PNG, GIF, WEBP') ?>
            </div>
        </form>

        <?php if(!empty($user['avatar'])): ?>
            <form method="POST">
                <button type="submit" name="delete_avatar" class="del-link">
                    <i class="fa-solid fa-trash"></i> <?= htmlspecialchars($pLang['remove_avatar'] ?? 'Remove Avatar') ?>
                </button>
            </form>
        <?php endif; ?>

        <h2 style="text-align:center; margin:10px 0 30px 0; color:var(--text-main);"><?= htmlspecialchars($user['username']) ?></h2>

        <?php if ($msg): ?>
            <div class="alert <?= $msgType ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label"><?= htmlspecialchars($pLang['username'] ?? 'Username') ?></label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($user['username']) ?>" disabled style="opacity:0.7; cursor:not-allowed;">
            </div>

            <div class="form-group">
                <label class="form-label"><?= htmlspecialchars($pLang['email'] ?? 'Email') ?></label>
                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>

            <div class="form-group" style="border-top:1px solid var(--border); margin-top:30px; padding-top:20px;">
                <label class="form-label"><?= htmlspecialchars($pLang['new_password'] ?? 'New Password') ?></label>
                <input type="password" name="new_password" class="form-input">
                <div class="form-text"><?= htmlspecialchars($pLang['new_password_hint'] ?? 'Leave blank to keep current password') ?></div>
            </div>

            <div class="form-group">
                <label class="form-label" style="color:#e53e3e;"><?= htmlspecialchars($pLang['current_password'] ?? 'Current Password') ?> *</label>
                <input type="password" name="current_password" class="form-input" required>
                <div class="form-text"><?= htmlspecialchars($pLang['current_password_hint'] ?? 'Required to save changes') ?></div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-save"></i> <?= htmlspecialchars($pLang['save'] ?? 'Save Changes') ?>
            </button>
        </form>
    </div>
</main>

<?php include 'footer.php'; ?>
<script>
    const toggleBtn = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    if (toggleBtn) { toggleBtn.addEventListener('click', () => { const current = html.getAttribute('data-theme'); const next = current === 'dark' ? 'light' : 'dark'; html.setAttribute('data-theme', next); localStorage.setItem('theme', next); }); }
</script>
</body>
</html>
