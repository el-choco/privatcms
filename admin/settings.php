<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$message = '';

// --- SPEICHER-LOGIK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Definierte Felder (Text und Select)
    $text_fields = ['blog_title', 'blog_description', 'posts_per_page'];
    // Definierte Checkboxen (müssen speziell behandelt werden)
    $checkboxes = ['debug_mode', 'error_logging', 'maintenance_mode'];

    try {
        $pdo->beginTransaction();
        
        // Textfelder speichern
        foreach ($text_fields as $key) {
            $val = $_POST[$key] ?? '';
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$key, $val, $val]);
        }

        // Checkboxen speichern (Wichtig: Wenn nicht im POST, dann '0')
        foreach ($checkboxes as $key) {
            $val = isset($_POST[$key]) ? '1' : '0';
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$key, $val, $val]);
        }

        $pdo->commit();
        $message = '<div style="color:green; padding:10px; background:#f0fff4; border-radius:8px; margin-bottom:20px;">✅ Einstellungen erfolgreich gespeichert!</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div style="color:red;">Fehler beim Speichern: ' . $e->getMessage() . '</div>';
    }
}

// --- EINSTELLUNGEN LADEN ---
$settings = [];
$rows = $pdo->query("SELECT * FROM settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include 'header.php';
?>

<style>
    .settings-container { max-width: 800px; margin: 0 auto; }
    
    /* Tab Styling */
    .tab-nav { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    .tab-btn { 
        padding: 10px 20px; border: none; background: none; cursor: pointer; 
        font-weight: 600; color: #718096; border-radius: 6px; transition: all 0.2s;
    }
    .tab-btn.active { background: #3182ce; color: white; }
    .tab-btn:hover:not(.active) { background: #edf2f7; color: #2d3748; }

    .tab-content { display: none; animation: fadeIn 0.3s ease-in; }
    .tab-content.active { display: block; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 8px; color: #4a5568; font-size: 14px; }
    .form-group input[type="text"], .form-group input[type="number"], .form-group textarea {
        width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box;
    }
    .checkbox-group { display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 12px; border-radius: 8px; cursor: pointer; }
    .checkbox-group:hover { background: #edf2f7; }
</style>

<header class="top-header">
    <h1>⚙️ Einstellungen</h1>
</header>

<div class="content-area settings-container">
    <?= $message ?>

    <form method="POST">
        <nav class="tab-nav">
            <button type="button" class="tab-btn active" onclick="showTab('general')">Allgemein</button>
            <button type="button" class="tab-btn" onclick="showTab('system')">System & Debug</button>
            <button type="button" class="tab-btn" onclick="showTab('database')">Datenbank</button>
        </nav>

        <div class="card" style="padding: 30px;">
            
            <div id="general" class="tab-content active">
                <div class="form-group">
                    <label>Blog Titel</label>
                    <input type="text" name="blog_title" value="<?= htmlspecialchars($settings['blog_title'] ?? 'PiperBlog') ?>">
                </div>
                <div class="form-group">
                    <label>Blog Beschreibung</label>
                    <textarea name="blog_description" rows="3"><?= htmlspecialchars($settings['blog_description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Beiträge pro Seite (Frontend)</label>
                    <input type="number" name="posts_per_page" value="<?= htmlspecialchars($settings['posts_per_page'] ?? '10') ?>">
                </div>
            </div>

            <div id="system" class="tab-content">
                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <div>
                            <strong>Wartungsmodus aktivieren</strong><br>
                            <small>Besucher sehen nur eine Wartungsseite.</small>
                        </div>
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" name="debug_mode" <?= ($settings['debug_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <div>
                            <strong>Debug Modus aktivieren</strong><br>
                            <small>Zeigt detaillierte Fehlermeldungen (nur für Entwicklung!).</small>
                        </div>
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" name="error_logging" <?= ($settings['error_logging'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <div>
                            <strong>Fehler-Logs schreiben</strong><br>
                            <small>Speichert Fehler in `/logs/error.log`.</small>
                        </div>
                    </label>
                </div>
            </div>

            <div id="database" class="tab-content">
                <div style="background: #fff5f5; padding: 15px; border-radius: 8px; border: 1px solid #feb2b2; margin-bottom: 20px;">
                    <p style="margin:0; color: #c53030; font-size: 14px;"><strong>Hinweis:</strong> Die Datenbank-Zugangsdaten werden aus der <code>config.ini</code> gelesen und können hier nur eingesehen werden.</p>
                </div>
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" value="<?= htmlspecialchars((string)$ini['database']['host']) ?>" readonly style="background: #f7fafc; color: #a0aec0;">
                </div>
                <div class="form-group">
                    <label>Datenbank Name</label>
                    <input type="text" value="<?= htmlspecialchars((string)$ini['database']['name']) ?>" readonly style="background: #f7fafc; color: #a0aec0;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 20px;">Einstellungen speichern</button>
        </div>
    </form>
</div>

<script>
function showTab(tabId) {
    // Buttons umschalten
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if(btn.getAttribute('onclick').includes(tabId)) btn.classList.add('active');
    });

    // Content umschalten
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');
}
</script>

<?php include 'footer.php'; ?>