<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

$userRole = $_SESSION['admin']['role'] ?? 'viewer';
if ($userRole !== 'admin') {
    header('Location: /admin/');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/BackupService.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();
$backupService = new App\BackupService($pdo, __DIR__ . '/..');

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$sLang = $t_temp['settings'] ?? [];

$message = '';

$activeTab = $_POST['active_tab'] ?? $_GET['tab'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $text_fields = [
            'blog_title', 'blog_description', 'posts_per_page', 
            'admin_email', 'sender_email', 'sender_name',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'
        ];
        $checkboxes = [
            'debug_mode', 'error_logging', 'maintenance_mode', 'dark_mode_enabled',
            'email_enabled', 'notify_new_comments', 'notify_comment_approved',
            'smtp_active'
        ];

        try {
            $pdo->beginTransaction();
            foreach ($text_fields as $key) {
                $val = $_POST[$key] ?? '';
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $val, $val]);
            }
            foreach ($checkboxes as $key) {
                $val = isset($_POST[$key]) ? '1' : '0';
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $val, $val]);
            }
            $pdo->commit();
            $message = '<div class="alert alert-success">' . ($sLang['msg_saved'] ?? 'Saved') . '</div>';
        } catch (Exception $e) { 
            $pdo->rollBack(); 
            $message = '<div class="alert alert-danger">' . ($sLang['msg_error'] ?? 'Error: ') . $e->getMessage() . '</div>'; 
        }
    }

    if (isset($_POST['backup_action'])) {
        try {
            $action = $_POST['backup_action'];
            $filePath = ($action === 'full') ? $backupService->createFullBackup() : (($action === 'json') ? $backupService->exportJson() : $backupService->exportCsv());
            if ($filePath && file_exists($filePath)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath); unlink($filePath); exit;
            }
        } catch (Exception $e) { 
            $message = '<div class="alert alert-danger">' . ($sLang['msg_download_error'] ?? 'Download Error: ') . $e->getMessage() . '</div>'; 
        }
    }

    if (isset($_POST['restore_backup'])) {
        try {
            if (!empty($_FILES['backup_file']['tmp_name'])) {
                if (method_exists($backupService, 'restoreBackup')) {
                    $backupService->restoreBackup($_FILES['backup_file']['tmp_name']);
                    $message = '<div class="alert alert-success">' . ($sLang['msg_restore_success'] ?? 'Restore successful') . '</div>';
                } else {
                    throw new Exception("Restore method not implemented in BackupService.");
                }
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">' . ($sLang['msg_error'] ?? 'Error: ') . $e->getMessage() . '</div>';
        }
    }
}

$settings = [];
foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
$backupsList = $backupService->getList();

require_once 'header.php';
?>

<style>
    .admin-content { background: #f0f2f5; padding: 20px; min-height: 100vh; padding-bottom: 150px; }
    
    .tab-nav { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 1px solid #e2e8f0; background: #fff; padding: 10px 15px 0; border-radius: 8px 8px 0 0; flex-wrap: wrap; border-top: 5px solid #3182ce; justify-content: space-around; }
    .tab-btn { padding: 12px 20px; border: none; background: none; cursor: pointer; font-weight: 600; color: #718096; border-bottom: 3px solid transparent; transition: 0.2s; font-size: 17px; }
    .tab-btn.active { color: #1877f2; border-bottom-color: #1877f2; }
    .settings-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 20px; border: 1px solid #e2e8f0; border-top: 5px solid #3182ce; }
    .settings-card h3 { margin-top: 0; margin-bottom: 25px; font-size: 20px; border-bottom: 1px solid #edf2f7; padding-bottom: 15px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 15px; color: #4a5568; }
    .input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
    .checkbox-group { display: flex; align-items: center; gap: 15px; cursor: pointer; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
    .stat-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px; }
    .stat-icon { font-size: 30px; background: #f7fafc; padding: 10px; border-radius: 10px; }
    .stat-val { font-size: 22px; font-weight: bold; color: #1877f2; }
    .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
    .action-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; text-align: center; }
    .action-card.highlight { border: 2px solid #1877f2; background: #f0f7ff; }

    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    .save-bar { position: fixed; bottom: 20px; right: 40px; z-index: 1000; background: rgba(255, 255, 255, 0.9); padding: 10px 20px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; backdrop-filter: blur(5px);}
</style>

<div class="admin-content">
    <div style="max-width: 1530px; margin: 0 auto;">
        <h1 style="margin-bottom: 25px;"><?= htmlspecialchars($sLang['header'] ?? 'System Settings') ?></h1>
        <?= $message ?>

        <nav class="tab-nav">
            <button type="button" class="tab-btn <?= $activeTab === 'general' ? 'active' : '' ?>" onclick="showTab('general', this)"><?= htmlspecialchars($sLang['tab_general'] ?? 'General') ?></button>
            <button type="button" class="tab-btn <?= $activeTab === 'theme' ? 'active' : '' ?>" onclick="showTab('theme', this)"><?= htmlspecialchars($sLang['tab_theme'] ?? 'Theme') ?></button>
            <button type="button" class="tab-btn <?= $activeTab === 'email' ? 'active' : '' ?>" onclick="showTab('email', this)"><?= htmlspecialchars($sLang['tab_email'] ?? 'Email') ?></button>
            <button type="button" class="tab-btn <?= $activeTab === 'backup' ? 'active' : '' ?>" onclick="showTab('backup', this)"><?= htmlspecialchars($sLang['tab_backup'] ?? 'Backup') ?></button>
            <button type="button" class="tab-btn <?= $activeTab === 'system' ? 'active' : '' ?>" onclick="showTab('system', this)"><?= htmlspecialchars($sLang['tab_system'] ?? 'System') ?></button>
            <button type="button" class="tab-btn <?= $activeTab === 'database' ? 'active' : '' ?>" onclick="showTab('database', this)"><?= htmlspecialchars($sLang['tab_database'] ?? 'Database') ?></button>
            <button type="button" class="tab-btn <?= $activeTab === 'info' ? 'active' : '' ?>" onclick="showTab('info', this)"><?= htmlspecialchars($sLang['tab_info'] ?? 'Info') ?></button>
        </nav>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="active_tab" id="active_tab_input" value="<?= htmlspecialchars($activeTab) ?>">

            <div id="general" class="tab-content <?= $activeTab === 'general' ? 'active' : '' ?>">
                <div class="settings-card">
                    <h3><?= htmlspecialchars($sLang['sec_blog_info'] ?? 'Blog Info') ?></h3>
                    <div class="form-row">
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_blog_title'] ?? 'Title') ?></label><input type="text" name="blog_title" value="<?= htmlspecialchars($settings['blog_title'] ?? '') ?>" class="input"></div>
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_your_name'] ?? 'Name') ?></label><input type="text" value="<?= htmlspecialchars($_SESSION['admin']['username'] ?? '') ?>" class="input" readonly></div>
                    </div>
                    <div class="form-group"><label><?= htmlspecialchars($sLang['label_blog_desc'] ?? 'Description') ?></label><textarea name="blog_description" rows="3" class="input"><?= htmlspecialchars($settings['blog_description'] ?? '') ?></textarea></div>
                    <div class="form-group"><label><?= htmlspecialchars($sLang['label_posts_per_page'] ?? 'Posts per page') ?></label><input type="number" name="posts_per_page" value="<?= htmlspecialchars($settings['posts_per_page'] ?? '10') ?>" class="input" style="width:150px;"></div>
                </div>
            </div>

            <div id="theme" class="tab-content <?= $activeTab === 'theme' ? 'active' : '' ?>">
                <div class="settings-card">
                    <h3><?= htmlspecialchars($sLang['sec_theme'] ?? 'Theme') ?></h3>
                    <div style="padding:20px; background:#f8fafc; border-radius:12px;">
                        <label class="checkbox-group">
                            <input type="checkbox" name="dark_mode_enabled" style="transform: scale(1.3);" <?= ($settings['dark_mode_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div><strong><?= htmlspecialchars($sLang['check_dark_mode'] ?? 'Allow Dark Mode') ?></strong><br><small><?= htmlspecialchars($sLang['desc_dark_mode'] ?? '') ?></small></div>
                        </label>
                    </div>
                </div>
            </div>

            <div id="email" class="tab-content <?= $activeTab === 'email' ? 'active' : '' ?>">
                <div class="settings-card">
                    <h3><?= htmlspecialchars($sLang['sec_email'] ?? 'Email') ?></h3>
                    <div style="margin-bottom:25px;">
                        <label class="checkbox-group">
                            <input type="checkbox" name="email_enabled" style="transform: scale(1.3);" <?= ($settings['email_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div><strong><?= htmlspecialchars($sLang['check_email_enable'] ?? 'Enable Email') ?></strong><br><small><?= htmlspecialchars($sLang['desc_email_enable'] ?? '') ?></small></div>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_admin_email'] ?? 'Admin Email') ?></label><input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" class="input"></div>
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_sender_email'] ?? 'Sender Email') ?></label><input type="email" name="sender_email" value="<?= htmlspecialchars($settings['sender_email'] ?? '') ?>" class="input"></div>
                    </div>
                    <div class="form-group"><label><?= htmlspecialchars($sLang['label_sender_name'] ?? 'Sender Name') ?></label><input type="text" name="sender_name" value="<?= htmlspecialchars($settings['sender_name'] ?? 'PiperBlog') ?>" class="input"></div>
                
                    <hr style="margin:30px 0; border:0; border-top:1px solid #e2e8f0;">
                    
                    <div style="margin-bottom:20px;">
                        <label class="checkbox-group">
                            <input type="checkbox" name="smtp_active" style="transform: scale(1.3);" <?= ($settings['smtp_active'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div><strong><?= htmlspecialchars($sLang['check_smtp'] ?? 'Use SMTP') ?></strong> <?= htmlspecialchars($sLang['desc_smtp'] ?? '') ?></div>
                        </label>
                    </div>

                    <div class="form-row">
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_smtp_host'] ?? 'Host') ?></label><input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" class="input" placeholder="smtp.example.com"></div>
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_smtp_port'] ?? 'Port') ?></label><input type="text" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>" class="input" placeholder="587"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_smtp_user'] ?? 'User') ?></label><input type="text" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>" class="input"></div>
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_smtp_pass'] ?? 'Pass') ?></label><input type="password" name="smtp_pass" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>" class="input"></div>
                    </div>

                    <div style="margin-top: 25px; padding: 15px; background: #e7f3ff; border: 1px solid #bad6fa; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <strong style="color: #1877f2;"><?= htmlspecialchars($sLang['box_test_title'] ?? 'Test') ?></strong><br>
                            <span style="font-size: 0.85em; color: #555;"><?= htmlspecialchars($sLang['box_test_text'] ?? '') ?></span>
                        </div>
                        <a href="test_mail.php" target="_blank" style="background: #1877f2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 5px rgba(24,119,242,0.2);">
                            <?= htmlspecialchars($sLang['btn_test_mail'] ?? 'Send Test') ?>
                        </a>
                    </div>
                </div>

                <div class="settings-card">
                    <h3><?= htmlspecialchars($sLang['sec_notifications'] ?? 'Notifications') ?></h3>
                    <div class="form-group"><label class="checkbox-group"><input type="checkbox" name="notify_new_comments" <?= ($settings['notify_new_comments'] ?? '0') === '1' ? 'checked' : '' ?>> <div><?= htmlspecialchars($sLang['check_notify_comments'] ?? '') ?></div></label></div>
                    <div class="form-group"><label class="checkbox-group"><input type="checkbox" name="notify_comment_approved" <?= ($settings['notify_comment_approved'] ?? '0') === '1' ? 'checked' : '' ?>> <div><?= htmlspecialchars($sLang['check_notify_approved'] ?? '') ?></div></label></div>
                </div>
            </div>

            <div id="backup" class="tab-content <?= $activeTab === 'backup' ? 'active' : '' ?>">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon">📦</div><div><div class="stat-val"><?= count($backupsList) ?></div><div style="font-size:12px;color:#666"><?= htmlspecialchars($sLang['stat_backups'] ?? 'Backups') ?></div></div></div>
                    <div class="stat-card"><div class="stat-icon">💾</div><?php $totalSize = array_sum(array_column($backupsList, 'size')) / 1024 / 1024; ?><div><div class="stat-val"><?= number_format($totalSize, 2) ?> MB</div><div style="font-size:12px;color:#666"><?= htmlspecialchars($sLang['stat_size'] ?? 'Size') ?></div></div></div>
                    <div class="stat-card"><div class="stat-icon">🗓️</div><div><div class="stat-val"><?= !empty($backupsList) ? date('d.m.y', $backupsList[0]['created']) : '-' ?></div><div style="font-size:12px;color:#666"><?= htmlspecialchars($sLang['stat_last'] ?? 'Latest') ?></div></div></div>
                </div>
                <div class="settings-card">
                    <h3><?= htmlspecialchars($sLang['sec_export'] ?? 'Export') ?></h3>
                    <div class="action-grid">
                        <div class="action-card"><h4>📋 JSON</h4><button type="submit" name="backup_action" value="json" class="btn btn-sm" style="width:100%"><?= htmlspecialchars($sLang['btn_download'] ?? 'Download') ?></button></div>
                        <div class="action-card"><h4>📊 CSV</h4><button type="submit" name="backup_action" value="csv" class="btn btn-sm" style="width:100%"><?= htmlspecialchars($sLang['btn_download'] ?? 'Download') ?></button></div>
                        <div class="action-card highlight"><h4><?= htmlspecialchars($sLang['label_full_backup'] ?? 'ZIP') ?></h4><button type="submit" name="backup_action" value="full" class="btn btn-primary btn-sm" style="width:100%"><?= htmlspecialchars($sLang['btn_create_backup'] ?? 'Create') ?></button></div>
                    </div>
                </div>
                <div class="settings-card" style="margin-top: 20px;">
                    <h3><?= htmlspecialchars($sLang['sec_restore'] ?? 'Restore') ?></h3>
                    <div style="margin-bottom: 15px; color: #e53e3e; font-size: 0.9rem;">
                        <?= htmlspecialchars($sLang['warn_restore'] ?? 'Warning: This will overwrite your database!') ?>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <input type="file" name="backup_file" class="input" accept=".zip">
                        <button type="submit" name="restore_backup" class="btn btn-danger">
                            <?= htmlspecialchars($sLang['btn_restore'] ?? 'Restore') ?>
                        </button>
                    </div>
                </div>
            </div>

            <div id="system" class="tab-content <?= $activeTab === 'system' ? 'active' : '' ?>">
                <div class="settings-card">
                    <h3><?= htmlspecialchars($sLang['sec_maintenance'] ?? 'Maintenance') ?></h3>
                    <div class="form-group"><label class="checkbox-group"><input type="checkbox" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? '0')==='1'?'checked':'' ?>> <?= htmlspecialchars($sLang['check_maintenance'] ?? 'Maintenance Mode') ?></label></div>
                    <div class="form-group"><label class="checkbox-group"><input type="checkbox" name="debug_mode" <?= ($settings['debug_mode'] ?? '0')==='1'?'checked':'' ?>> <?= htmlspecialchars($sLang['check_debug'] ?? 'Debug Mode') ?></label></div>
                </div>
            </div>

            <div id="database" class="tab-content <?= $activeTab === 'database' ? 'active' : '' ?>">
                <div class="settings-card">
                    <h3><?= htmlspecialchars($sLang['sec_db_info'] ?? 'DB Info') ?></h3>
                    <div class="form-row">
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_host'] ?? 'Host') ?></label><input type="text" value="<?= htmlspecialchars((string)$ini['database']['host']) ?>" class="input" readonly></div>
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_db'] ?? 'DB') ?></label><input type="text" value="<?= htmlspecialchars((string)$ini['database']['name']) ?>" class="input" readonly></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_user'] ?? 'User') ?></label><input type="text" value="<?= htmlspecialchars((string)$ini['database']['user']) ?>" class="input" readonly></div>
                        <div class="form-group"><label><?= htmlspecialchars($sLang['label_pass'] ?? 'Pass') ?></label><input type="text" value="<?= htmlspecialchars((string)$ini['database']['password']) ?>" class="input" readonly></div>
                    </div>
                </div>
            </div>

            <div id="info" class="tab-content <?= $activeTab === 'info' ? 'active' : '' ?>">
                <div class="settings-card">
                    <h3><?= htmlspecialchars($sLang['sec_system_info'] ?? 'System Info') ?></h3>
                    <table style="width:100%;">
                        <tr style="border-bottom:1px solid #eee;"><td style="padding:10px;font-weight:bold;"><?= htmlspecialchars($sLang['label_php'] ?? 'PHP') ?>:</td><td><?= PHP_VERSION ?></td></tr>
                        <tr><td style="padding:10px;font-weight:bold;"><?= htmlspecialchars($sLang['label_server'] ?? 'Server') ?>:</td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Apache' ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="save-bar">
                <button type="submit" name="save_settings" class="btn btn-primary" style="padding:10px 40px; font-size:16px;">
                    <?= htmlspecialchars($sLang['btn_save'] ?? 'Save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    
    document.getElementById('active_tab_input').value = tabId;
    
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
}

const urlParams = new URLSearchParams(window.location.search);
const t = urlParams.get('tab');
if(t) {
    const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick').includes(t));
    if(btn) {
        showTab(t, btn);
    }
}
</script>

<?php include 'footer.php'; ?>