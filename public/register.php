<?php
declare(strict_types=1);
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: /forum.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$aLang = $iniLang['auth'] ?? [];

$settings = []; try { foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; } } catch (Exception $e) {}
try { $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn(); $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn(); } catch (Exception $e) { $totalViews=0; $todayViews=0; }
$t = ['footer_total' => $iniLang['frontend']['footer_stats_total'] ?? 'Total','footer_today' => $iniLang['frontend']['footer_stats_today'] ?? 'Today','admin' => $iniLang['frontend']['login_link'] ?? 'Admin'];

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? ''); $email = trim($_POST['email'] ?? ''); $pass = $_POST['password'] ?? ''; $pass2 = $_POST['password_confirm'] ?? '';
    if (empty($username) || empty($email) || empty($pass)) { $error = $aLang['error_fill'] ?? 'Fill all fields'; } 
    elseif ($pass !== $pass2) { $error = $aLang['error_pwd_match'] ?? 'Mismatch'; } 
    elseif (strlen($pass) < 6) { $error = $aLang['error_pwd_len'] ?? 'Short password'; } 
    else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?"); $stmt->execute([$username, $email]);
        if ($stmt->fetch()) { $error = $aLang['error_exists'] ?? 'Exists'; } 
        else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, 'member', NOW())");
            if ($stmt->execute([$username, $email, $hash])) { $success = $aLang['success_reg'] ?? 'Success'; } else { $error = 'DB Error'; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($aLang['register_title'] ?? 'Register') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="/assets/styles/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
    .page-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); max-width: 450px; margin: 60px auto; }
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main); }
    .form-input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-size: 1rem; background: var(--bg-body); color: var(--text-main); }
    .btn-primary { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; display: block; text-align: center; text-decoration: none; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div class="page-card">
        <h2 style="text-align: center; margin-bottom: 25px; color: var(--text-main);">
            <?= htmlspecialchars($aLang['register_title'] ?? 'Register') ?>
        </h2>
        
        <?php if ($error): ?>
            <div style="background: #fed7d7; color: #822727; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; border:1px solid #feb2b2;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: #c6f6d5; color: #22543d; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; border:1px solid #9ae6b4;">
                <?= htmlspecialchars($success) ?>
                <br><br>
                <a href="/login.php" class="btn-primary" style="width:auto; display:inline-block;">
                    <?= htmlspecialchars($aLang['btn_login'] ?? 'Login') ?>
                </a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($aLang['username'] ?? 'User') ?></label>
                    <input type="text" name="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($aLang['email'] ?? 'Email') ?></label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($aLang['password'] ?? 'Pass') ?></label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($aLang['password_confirm'] ?? 'Confirm') ?></label>
                    <input type="password" name="password_confirm" class="form-input" required>
                </div>
                <button type="submit" class="btn-primary">
                    <?= htmlspecialchars($aLang['btn_register'] ?? 'Register') ?>
                </button>
            </form>
            <p style="text-align: center; margin-top: 20px;">
                <?= htmlspecialchars($aLang['have_account'] ?? 'Account?') ?> 
                <a href="/login.php" style="color: var(--primary); font-weight:bold; text-decoration:none;">
                    <?= htmlspecialchars($aLang['btn_login'] ?? 'Login') ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>
<script>
    function toggleLang() { document.getElementById('langMenu').classList.toggle('show'); }
    window.addEventListener('click', function(e) { if (!document.getElementById('langDropdown').contains(e.target)) { document.getElementById('langMenu').classList.remove('show'); } });
    const toggleBtn = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    if (toggleBtn) { toggleBtn.addEventListener('click', () => { const current = html.getAttribute('data-theme'); const next = current === 'dark' ? 'light' : 'dark'; html.setAttribute('data-theme', next); localStorage.setItem('theme', next); }); }
</script>
</body>
</html>