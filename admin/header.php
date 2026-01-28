<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

$role = $_SESSION['admin']['role'] ?? '';
if ($role === 'member' || $role === 'viewer') {
    header('Location: /'); 
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];

if (!empty($_SESSION['timezone'])) { date_default_timezone_set($_SESSION['timezone']); }
if (!empty($_SESSION['date_fmt'])) { $t['common']['date_fmt'] = $_SESSION['date_fmt']; }

$cur = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['admin']['role'] ?? 'viewer';
$isAdmin = $userRole === 'admin';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title><?= htmlspecialchars($ini['app']['name'] ?? 'Admin') ?></title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body, html { margin: 0; padding: 0; height: 100vh; font-family: 'Inter', sans-serif; background: #f7fafc; color: #2d3748; overflow: hidden; }
        .admin-wrapper { display: flex; height: 100vh; width: 100vw; }
        
        .admin-sidebar { width: 260px; background: #1a202c; color: #a0aec0; flex-shrink: 0; display: flex; flex-direction: column; overflow-y: auto; transition: width 0.3s; }
        .sidebar-brand { padding: 25px 20px; font-size: 1.4rem; font-weight: 800; color: #63b3ed; text-decoration: none; display: block; border-bottom: 1px solid #2d3748; margin-bottom: 10px; }
        
        .admin-sidebar nav { flex: 1; padding: 10px 15px; display: flex; flex-direction: column; gap: 5px; }
        .nav-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #4a5568; margin: 15px 10px 5px; letter-spacing: 0.05em; }
        
        .nav-item { display: flex; align-items: center; padding: 10px 15px; color: #cbd5e0; text-decoration: none; transition: 0.2s; font-size: 0.9rem; font-weight: 500; border-radius: 8px; }
        .nav-item:hover { color: #fff; background: rgba(255,255,255,0.08); }
        .nav-item.active { background: #2d3748; color: #63b3ed; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .nav-item i { width: 24px; text-align: center; margin-right: 12px; font-size: 1.1rem; opacity: 0.8; }
        .nav-item.active i { opacity: 1; color: #63b3ed; }

        .sidebar-footer { padding: 20px; border-top: 1px solid #2d3748; margin-top: auto; }
        .btn-back { display: flex; align-items: center; color: #a0aec0; text-decoration: none; font-size: 0.9rem; margin-bottom: 15px; transition: 0.2s; }
        .btn-back:hover { color: #fff; }
        .btn-logout { display: flex; align-items: center; color: #fc8181; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: 0.2s; }
        .btn-logout:hover { color: #f56565; }

        .main-viewport { flex: 1; display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden; position: relative; background: #f7fafc; }
        .admin-content { flex: 1; padding: 30px; }
        
        .card { background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 20px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; border: 1px solid transparent; text-decoration: none; font-size: 0.9rem; }
        .btn-primary { background: #3182ce; color: #fff; } .btn-primary:hover { background: #2b6cb0; }
        .btn-danger { background: #e53e3e; color: #fff; } .btn-danger:hover { background: #c53030; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: center; padding: 12px 15px; background: #f7fafc; color: #4a5568; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; color: #2d3748; vertical-align: middle; }
        tr:hover td { background: #f8fafc; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #4a5568; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .form-control:focus { border-color: #3182ce; outline: none; box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2); }
        :not(pre) > code{background-color:#23241f;color:#f8f8f2;padding:2px 6px;border-radius:4px;font-family:'Fira Code',Consolas,monospace;font-size:.9em;border:1px solid #3e3d32}
    </style>
</head>
<body>

<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <a href="index.php" class="sidebar-brand">
            <?= htmlspecialchars($ini['app']['name'] ?? 'Admin') ?>
        </a>
        
        <nav>
            <a href="dashboard.php" class="nav-item <?= $cur === 'dashboard.php' || $cur === 'index.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge"></i> <?= htmlspecialchars($t['dashboard']['title'] ?? 'Dashboard') ?>
            </a>
            
            <a href="posts.php" class="nav-item <?= in_array($cur, ['posts.php', 'post-create.php', 'post-edit.php']) ? 'active' : '' ?>">
                <i class="fa-solid fa-pen-nib"></i> <?= htmlspecialchars($t['posts']['title'] ?? 'Posts') ?>
            </a>            

            <a href="files.php" class="nav-item <?= $cur === 'files.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-folder-open"></i> <?= htmlspecialchars($t['files']['title'] ?? 'Files') ?>
            </a>
            
            <?php if ($isAdmin): ?>
                <div class="nav-label">Management</div>
                
                <a href="pages.php" class="nav-item <?= in_array($cur, ['pages.php', 'page-edit.php']) ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-lines"></i> <?= htmlspecialchars($t['pages']['title'] ?? 'Pages') ?>
                </a>

                <a href="menus.php" class="nav-item <?= $cur === 'menus.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-bars"></i> <?= htmlspecialchars($t['menu']['title'] ?? 'Menu') ?>
                </a>

                <a href="categories.php" class="nav-item <?= $cur === 'categories.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-tags"></i> <?= htmlspecialchars($t['categories']['title'] ?? 'Categories') ?>
                </a>

                <a href="forums.php" class="nav-item <?= $cur === 'forums.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-comments"></i> <?= htmlspecialchars($t['forum']['title'] ?? 'Forum') ?>
                </a>

                <a href="forum-posts.php" class="nav-item <?= in_array($cur, ['forum-posts.php', 'forum-post-edit.php']) ? 'active' : '' ?>">
                    <i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($t['forum_manage']['title'] ?? 'Forum Posts') ?>
                </a>

                <a href="forum-labels.php" class="nav-item <?= $cur === 'forum-labels.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($t['forum_labels']['title'] ?? 'Forum Labels') ?>
                </a>
                
                <a href="comments.php" class="nav-item <?= $cur === 'comments.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-comments"></i> <?= htmlspecialchars($t['comments']['title'] ?? 'Comments') ?>
                </a>
                
                <a href="users.php" class="nav-item <?= in_array($cur, ['users.php', 'user-edit.php']) ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i> <?= htmlspecialchars($t['users']['title'] ?? 'Users') ?>
                </a>

                <div class="nav-label">System</div>

                <a href="activity-log.php" class="nav-item <?= $cur === 'activity-log.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i> <?= htmlspecialchars($t['activity_log']['title'] ?? 'Log') ?>
                </a>            
                
                <a href="messages.php" class="nav-item <?= $cur === 'messages.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($t['messages']['menu_link'] ?? 'Inbox') ?>
                </a>
                
                <a href="change-language.php" class="nav-item <?= $cur === 'change-language.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-earth-americas"></i> <?= htmlspecialchars($t['language_settings']['title'] ?? 'Region') ?>
                </a>

                <a href="settings.php" class="nav-item <?= $cur === 'settings.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gear"></i> <?= htmlspecialchars($t['settings']['title'] ?? 'Settings') ?>
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="/" class="btn-back">
                <i class="fa-solid fa-arrow-left" style="width:24px; text-align:center; margin-right:12px;"></i> 
                <?= htmlspecialchars($t['common']['back_to_blog'] ?? 'Back') ?>
            </a>
            <a href="logout.php" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket" style="width:24px; text-align:center; margin-right:12px;"></i> 
                <?= htmlspecialchars($t['common']['logout'] ?? 'Logout') ?> 
            </a>
        </div>
    </aside>
    <main class="main-viewport">