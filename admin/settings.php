<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/BackupService.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();
$backupService = new App\BackupService($pdo, __DIR__ . '/..');

$message = '';
$activeTab = $_GET['tab'] ?? 'general';

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
            $message = '<div class="alert alert-success">✅ Einstellungen gespeichert!</div>';
        } catch (Exception $e) { $pdo->rollBack(); $message = '<div class="alert alert-danger">Fehler: '.$e->getMessage().'</div>'; }
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
        } catch (Exception $e) { $message = '<div class="alert alert-danger">❌ Download-Fehler: ' . $e->getMessage() . '</div>'; }
    }
}

$settings = [];
foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
$backupsList = $backupService->getList();

include 'header.php';
?>

<style>
    /* WICHTIG: Viel Platz unten lassen (150px), damit man unter den Button scrollen kann */
    .admin-content { background: #f0f2f5; padding: 20px; min-height: 100vh; padding-bottom: 150px; }
    
    .tab-nav { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 1px solid #e2e8f0; background: #fff; padding: 10px 15px 0; border-radius: 8px 8px 0 0; }
    .tab-btn { padding: 12px 20px; border: none; background: none; cursor: pointer; font-weight: 600; color: #718096; border-bottom: 3px solid transparent; transition: 0.2s; font-size: 14px; }
    .tab-btn.active { color: #1877f2; border-bottom-color: #1877f2; }
    .settings-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .settings-card h3 { margin-top: 0; margin-bottom: 25px; font-size: 18px; border-bottom: 1px solid #edf2f7; padding-bottom: 15px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px; color: #4a5568; }
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
    
    /* Schicke Leiste unten für den Speicher-Button */
    .save-bar {
        position: fixed; 
        bottom: 20px; 
        right: 40px; /* Etwas Abstand vom Rand */
        z-index: 1000;
        background: rgba(255, 255, 255, 0.9);
        padding: 10px 20px;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        border: 1px solid #e2e8f0;
        backdrop-filter: blur(5px);
    }
</style>

<div class="admin-content">
    <div style="max-width: 1100px; margin: 0 auto;">
        <h1 style="margin-bottom: 25px;">⚙️ Systemeinstellungen</h1>
        <?= $message ?>

        <nav class="tab-nav">
            <button type="button" class="tab-btn active" onclick="showTab('general', this)">📝 Allgemein</button>
            <button type="button" class="tab-btn" onclick="showTab('theme', this)">🎨 Aussehen</button>
            <button type="button" class="tab-btn" onclick="showTab('email', this)">📧 E-Mail</button>
            <button type="button" class="tab-btn" onclick="showTab('backup', this)">📦 Backup</button>
            <button type="button" class="tab-btn" onclick="showTab('system', this)">🛠️ System</button>
            <button type="button" class="tab-btn" onclick="showTab('database', this)">🗄️ Datenbank</button>
            <button type="button" class="tab-btn" onclick="showTab('info', this)">ℹ️ Info</button>
        </nav>

        <form method="POST">
            <div id="general" class="tab-content active">
                <div class="settings-card">
                    <h3>📝 Blog Informationen</h3>
                    <div class="form-row">
                        <div class="form-group"><label>Blog-Titel</label><input type="text" name="blog_title" value="<?= htmlspecialchars($settings['blog_title'] ?? '') ?>" class="input"></div>
                        <div class="form-group"><label>Dein Name</label><input type="text" value="Paco" class="input" readonly></div>
                    </div>
                    <div class="form-group"><label>Beschreibung</label><textarea name="blog_description" rows="3" class="input"><?= htmlspecialchars($settings['blog_description'] ?? '') ?></textarea></div>
                    <div class="form-group"><label>Beiträge pro Seite</label><input type="number" name="posts_per_page" value="<?= htmlspecialchars($settings['posts_per_page'] ?? '10') ?>" class="input" style="width:150px;"></div>
                </div>
            </div>

            <div id="theme" class="tab-content">
                <div class="settings-card">
                    <h3>🎨 Theme & Modus</h3>
                    <div style="padding:20px; background:#f8fafc; border-radius:12px;">
                        <label class="checkbox-group">
                            <input type="checkbox" name="dark_mode_enabled" style="transform: scale(1.3);" <?= ($settings['dark_mode_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div><strong>Dark Mode zulassen</strong><br><small>Erlaubt Besuchern das Umschalten im Header.</small></div>
                        </label>
                    </div>
                </div>
            </div>

            <div id="email" class="tab-content">
                <div class="settings-card">
                    <h3>📧 E-Mail Versand</h3>
                    <div style="margin-bottom:25px;">
                        <label class="checkbox-group">
                            <input type="checkbox" name="email_enabled" style="transform: scale(1.3);" <?= ($settings['email_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div><strong>E-Mail System aktivieren</strong><br><small>Hauptschalter für alle Benachrichtigungen.</small></div>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group"><label>Admin E-Mail (Empfänger)</label><input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" class="input"></div>
                        <div class="form-group"><label>Absender E-Mail</label><input type="email" name="sender_email" value="<?= htmlspecialchars($settings['sender_email'] ?? '') ?>" class="input"></div>
                    </div>
                    <div class="form-group"><label>Absender Name</label><input type="text" name="sender_name" value="<?= htmlspecialchars($settings['sender_name'] ?? 'PiperBlog') ?>" class="input"></div>
                
                    <hr style="margin:30px 0; border:0; border-top:1px solid #e2e8f0;">
                    
                    <div style="margin-bottom:20px;">
                        <label class="checkbox-group">
                            <input type="checkbox" name="smtp_active" style="transform: scale(1.3);" <?= ($settings['smtp_active'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div><strong>SMTP verwenden</strong> (Empfohlen für zuverlässigen Versand)</div>
                        </label>
                    </div>

                    <div class="form-row">
                        <div class="form-group"><label>SMTP Host</label><input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" class="input" placeholder="smtp.beispiel.de"></div>
                        <div class="form-group"><label>SMTP Port</label><input type="text" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>" class="input" placeholder="587 oder 465"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>SMTP Benutzer</label><input type="text" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>" class="input"></div>
                        <div class="form-group"><label>SMTP Passwort</label><input type="password" name="smtp_pass" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>" class="input"></div>
                    </div>

                    <div style="margin-top: 25px; padding: 15px; background: #e7f3ff; border: 1px solid #bad6fa; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <strong style="color: #1877f2;">Verbindungstest</strong><br>
                            <span style="font-size: 0.85em; color: #555;">Speichere die Einstellungen, bevor du testest!</span>
                        </div>
                        <a href="test_mail.php" target="_blank" style="background: #1877f2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 5px rgba(24,119,242,0.2);">🚀 Test-Mail senden</a>
                    </div>
                </div>

                <div class="settings-card">
                    <h3>🔔 Benachrichtigungen</h3>
                    <div class="form-group"><label class="checkbox-group"><input type="checkbox" name="notify_new_comments" <?= ($settings['notify_new_comments'] ?? '0') === '1' ? 'checked' : '' ?>> <div>Bei neuen Kommentaren benachrichtigen</div></label></div>
                    <div class="form-group"><label class="checkbox-group"><input type="checkbox" name="notify_comment_approved" <?= ($settings['notify_comment_approved'] ?? '0') === '1' ? 'checked' : '' ?>> <div>Benutzer bei Freigabe benachrichtigen</div></label></div>
                </div>
            </div>

            <div id="backup" class="tab-content">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon">📦</div><div><div class="stat-val"><?= count($backupsList) ?></div><div style="font-size:12px;color:#666">Backups</div></div></div>
                    <div class="stat-card"><div class="stat-icon">💾</div><?php $totalSize = array_sum(array_column($backupsList, 'size')) / 1024 / 1024; ?><div><div class="stat-val"><?= number_format($totalSize, 2) ?> MB</div><div style="font-size:12px;color:#666">Größe</div></div></div>
                    <div class="stat-card"><div class="stat-icon">🗓️</div><div><div class="stat-val"><?= !empty($backupsList) ? date('d.m.y', $backupsList[0]['created']) : '-' ?></div><div style="font-size:12px;color:#666">Letztes</div></div></div>
                </div>
                <div class="settings-card">
                    <h3>📤 Exportieren</h3>
                    <div class="action-grid">
                        <div class="action-card"><h4>📋 JSON</h4><button type="submit" name="backup_action" value="json" class="btn btn-sm" style="width:100%">Download</button></div>
                        <div class="action-card"><h4>📊 CSV</h4><button type="submit" name="backup_action" value="csv" class="btn btn-sm" style="width:100%">Download</button></div>
                        <div class="action-card highlight"><h4>📦 Full ZIP</h4><button type="submit" name="backup_action" value="full" class="btn btn-primary btn-sm" style="width:100%">Backup erstellen</button></div>
                    </div>
                </div>
            </div>

            <div id="system" class="tab-content">
                <div class="settings-card">
                    <h3>🛠️ Wartung & Debug</h3>
                    <div class="form-group"><label class="checkbox-group"><input type="checkbox" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? '0')==='1'?'checked':'' ?>> Wartungsmodus aktivieren</label></div>
                    <div class="form-group"><label class="checkbox-group"><input type="checkbox" name="debug_mode" <?= ($settings['debug_mode'] ?? '0')==='1'?'checked':'' ?>> Debug-Modus</label></div>
                </div>
            </div>

            <div id="database" class="tab-content">
                <div class="settings-card">
                    <h3>🗄️ Datenbank Infos</h3>
                    <div class="form-row">
                        <div class="form-group"><label>Host</label><input type="text" value="<?= htmlspecialchars((string)$ini['database']['host']) ?>" class="input" readonly></div>
                        <div class="form-group"><label>Datenbank</label><input type="text" value="<?= htmlspecialchars((string)$ini['database']['name']) ?>" class="input" readonly></div>
                    </div>
                </div>
            </div>

            <div id="info" class="tab-content">
                <div class="settings-card">
                    <h3>ℹ️ System</h3>
                    <table style="width:100%;">
                        <tr style="border-bottom:1px solid #eee;"><td style="padding:10px;font-weight:bold;">PHP:</td><td><?= PHP_VERSION ?></td></tr>
                        <tr><td style="padding:10px;font-weight:bold;">Server:</td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Apache' ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="save-bar">
                <button type="submit" name="save_settings" class="btn btn-primary" style="padding:10px 40px; font-size:16px;">💾 Speichern</button>
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
    
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
}
const urlParams = new URLSearchParams(window.location.search);
const t = urlParams.get('tab');
if(t) {
    const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick').includes(t));
    if(btn) showTab(t, btn);
}
</script>

<?php include 'footer.php'; ?>