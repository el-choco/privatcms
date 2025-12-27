<?php
declare(strict_types=1);
session_start();

if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en', 'fr', 'es'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? 'de';

$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];

$t = [
    'title_fallback' => $iniLang['frontend']['welcome_title'] ?? 'PiperBlog',
    'admin'          => $iniLang['frontend']['login_link'] ?? 'Admin',
    'toggle_theme'   => $iniLang['settings']['check_dark_mode'] ?? 'Theme',
    'footer_total'   => $iniLang['frontend']['footer_stats_total'] ?? 'Total Visits',
    'footer_today'   => $iniLang['frontend']['footer_stats_today'] ?? 'Today',
    'nav_home'       => $iniLang['common']['nav_home'] ?? 'Home',
    'nav_contact'    => $iniLang['frontend']['nav_contact'] ?? 'Contact',
    'contact_title'  => $iniLang['frontend']['contact_title'] ?? 'Contact Us',
    'name_ph'        => $iniLang['common']['placeholder_name'] ?? 'Name',
    'email_ph'       => $iniLang['frontend']['contact_email'] ?? 'E-Mail',
    'subject_ph'     => $iniLang['frontend']['contact_subject'] ?? 'Subject',
    'message_ph'     => $iniLang['frontend']['contact_message'] ?? 'Message...',
    'submit_btn'     => $iniLang['frontend']['contact_submit'] ?? 'Send Message',
    'spam_q'         => $iniLang['common']['spam_protection'] ?? 'Spam Protection: %d + %d = ?',
    'success_msg'    => $iniLang['frontend']['contact_success'] ?? 'Message sent successfully!',
    'error_spam'     => $iniLang['common']['error_spam_wrong'] ?? 'Wrong answer.',
    'error_fill'     => $iniLang['common']['error_fill_fields'] ?? 'Please fill all fields.'
];

require_once __DIR__ . '/../src/App/Database.php';
use App\Database;

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED);
$db  = new Database($ini['database']);
$pdo = $db->pdo();
$pdo->exec("SET NAMES utf8mb4");

$settings = [];
try {
    foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { 
        $settings[$row['setting_key']] = $row['setting_value']; 
    }
} catch (Exception $e) { }

if (!isset($_SESSION['spam_a'])) {
    $_SESSION['spam_a'] = rand(1, 10);
    $_SESSION['spam_b'] = rand(1, 10);
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $spam_ans = (int)($_POST['spam_ans'] ?? 0);

    if ($name === '' || $email === '' || $message === '') {
        $error = $t['error_fill'];
    } elseif ($spam_ans !== ($_SESSION['spam_a'] + $_SESSION['spam_b'])) {
        $error = $t['error_spam'];
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (name, email, subject, content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $msg = $t['success_msg'];
            $_SESSION['spam_a'] = rand(1, 10);
            $_SESSION['spam_b'] = rand(1, 10);
            
            $name = $email = $subject = $message = ''; 
        } catch (Exception $e) {
            $error = 'Error saving message.';
        }
    }
}

try {
    $totalViews = (int)$pdo->query("SELECT SUM(views) FROM daily_stats")->fetchColumn();
    $todayViews = (int)$pdo->query("SELECT views FROM daily_stats WHERE date = CURDATE()")->fetchColumn();
} catch (Exception $e) { $totalViews = 0; $todayViews = 0; }

$languages = [
    'de' => ['label' => 'Deutsch',  'flag' => 'https://flagcdn.com/w40/de.png'],
    'en' => ['label' => 'English',  'flag' => 'https://flagcdn.com/w40/gb.png'],
    'fr' => ['label' => 'Français', 'flag' => 'https://flagcdn.com/w40/fr.png'],
    'es' => ['label' => 'Español',  'flag' => 'https://flagcdn.com/w40/es.png']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" data-theme="light">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($t['contact_title']) ?> - <?= htmlspecialchars($settings['blog_title'] ?? 'PiperBlog') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="/assets/styles/main.css" rel="stylesheet">
  <style>
    :root {
        --bg-body: #f0f2f5; --bg-card: #ffffff; --text-main: #1c1e21; --text-muted: #65676b;
        --border: #ccd0d5; --primary: #1877f2; --header-bg: #1877f2; --header-text: #ffffff;
        --bg-img: #ffffff; 
    }
    [data-theme="dark"] {
        --bg-body: #18191a; --bg-card: #242526; --text-main: #e4e6eb; --text-muted: #b0b3b8;
        --border: #3e4042; --primary: #2d88ff; --header-bg: #242526; --header-text: #e4e6eb;
        --bg-img: #ffffff; 
    }
    body { background-color: var(--bg-body); color: var(--text-main); margin: 0; font-family: -apple-system, sans-serif; transition: background 0.3s, color 0.3s; scroll-behavior: smooth; }
    
    .site-header { background-color: var(--header-bg); color: var(--header-text); padding: 12px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; max-width: 1500px; margin-right: auto; margin-left: auto; border-radius: 6px; }
    .header-container { max-width: 1600px; margin: 0 auto; width: 95%; display: flex; justify-content: space-between; align-items: center; }
    .site-title { font-size: 24px; font-weight: bold; color: var(--header-text); text-decoration: none; }
    .header-actions { display: flex; align-items: center; gap: 15px; }
    .btn-admin { background-color: rgba(255,255,255,0.2); color: var(--header-text); text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; }
    .btn-nav { background-color: rgba(255,255,255,0.2); color: var(--header-text); text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; margin-right: 5px; }
    .btn-nav:hover { background-color: rgba(255,255,255,0.3); }
    .theme-toggle { background: none; border: 1px solid rgba(255,255,255,0.3); color: var(--header-text); padding: 6px 12px; border-radius: 20px; cursor: pointer; font-size: 1.2rem; }
    
    .lang-dropdown { position: relative; }
    .lang-trigger { display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(255,255,255,0.15); padding: 6px 10px; border-radius: 6px; color: var(--header-text); font-weight: 600; font-size: 0.9rem; }
    .lang-trigger img { width: 20px; border-radius: 3px; }
    .lang-menu { display: none; position: absolute; right: 0; top: 120%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 1000; min-width: 150px; overflow: hidden; }
    .lang-menu.show { display: block; }
    .lang-option { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: var(--text-main); text-decoration: none; font-size: 0.95rem; }
    .lang-option:hover { background: var(--bg-body); color: var(--primary); }
    .lang-option img { width: 18px; border-radius: 2px; }

    .container { max-width: 800px; margin: 0 auto; width: 95%; padding-bottom: 50px; }
    .contact-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 40px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
    .contact-title { text-align: center; margin-bottom: 30px; font-size: 2rem; color: var(--text-main); }
    
    .form-group { margin-bottom: 20px; }
    .form-control { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; background: var(--bg-body); color: var(--text-main); font-size: 1rem; }
    textarea.form-control { resize: vertical; min-height: 150px; }
    .btn-submit { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; font-size: 1.1rem; transition: opacity 0.2s; }
    .btn-submit:hover { opacity: 0.9; }

    .alert { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-weight: 500; }
    .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .alert-danger { background: #f8d7da; color: #842029; border: 1px solid #f5c6cb; }

    #backToTop { position: fixed; bottom: 30px; right: 30px; z-index: 999; background: var(--primary); color: white; border: none; width: 50px; height: 50px; border-radius: 50%; font-size: 24px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2); opacity: 0; pointer-events: none; transition: opacity 0.3s, transform 0.3s; display: flex; align-items: center; justify-content: center; }
    #backToTop.show { opacity: 1; pointer-events: all; }
    #backToTop:hover { transform: translateY(-3px); }
  </style>
</head>
<body>
  <?php include 'header.php'; ?>

  <main class="container">
    <div class="contact-card">
        <h1 class="contact-title"><?= htmlspecialchars($t['contact_title']) ?></h1>
        
        <?php if($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="text" name="name" class="form-control" placeholder="<?= htmlspecialchars($t['name_ph']) ?>" value="<?= htmlspecialchars($name ?? '') ?>" required>
            </div>
            <div class="form-group">
                <input type="email" name="email" class="form-control" placeholder="<?= htmlspecialchars($t['email_ph']) ?>" value="<?= htmlspecialchars($email ?? '') ?>" required>
            </div>
            <div class="form-group">
                <input type="text" name="subject" class="form-control" placeholder="<?= htmlspecialchars($t['subject_ph']) ?>" value="<?= htmlspecialchars($subject ?? '') ?>">
            </div>
            <div class="form-group">
                <textarea name="message" class="form-control" placeholder="<?= htmlspecialchars($t['message_ph']) ?>" required><?= htmlspecialchars($message ?? '') ?></textarea>
            </div>
            <div class="form-group" style="background: var(--bg-body); padding: 15px; border-radius: 6px; display:flex; align-items:center; border: 1px solid var(--border);">
                <span style="margin-right: 15px; font-weight:bold;">
                    <?= sprintf(htmlspecialchars($t['spam_q']), $_SESSION['spam_a'], $_SESSION['spam_b']) ?>
                </span>
                <input type="number" name="spam_ans" class="form-control" style="width: 80px;" required>
            </div>
            <button type="submit" class="btn-submit"><?= htmlspecialchars($t['submit_btn']) ?></button>
        </form>
    </div>
  </main>

  <?php include 'footer.php'; ?>

  <button id="backToTop" title="Nach oben">↑</button>

  <script>
    function toggleLang() { document.getElementById('langMenu').classList.toggle('show'); }
    window.addEventListener('click', function(e) {
        if (!document.getElementById('langDropdown').contains(e.target)) {
            document.getElementById('langMenu').classList.remove('show');
        }
    });
    const toggleBtn = document.getElementById('theme-toggle');
    if (toggleBtn) {
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        toggleBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        });
    }
    const backToTopBtn = document.getElementById('backToTop');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) backToTopBtn.classList.add('show');
        else backToTopBtn.classList.remove('show');
    });
    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  </script>
</body>
</html>