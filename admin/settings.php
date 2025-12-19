<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
$iniPath = __DIR__ . '/../config/config.ini';
$ini = parse_ini_file($iniPath, true, INI_SCANNER_TYPED) ?: [];

$tab = $_GET['tab'] ?? 'general';
include 'header.php';
?>
<header class="top-header">
    <h1>⚙️ Einstellungen</h1>
</header>
<div class="content-area">
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <a href="?tab=general" class="btn <?= $tab==='general'?'btn-primary':'' ?>">Allgemein</a>
        <a href="?tab=database" class="btn <?= $tab==='database'?'btn-primary':'' ?>">Datenbank</a>
        <a href="?tab=debug" class="btn <?= $tab==='debug'?'btn-primary':'' ?>">Debug/Logs</a>
    </div>

    <div class="card" style="padding: 25px; max-width: 700px;">
        <form method="post">
            <?php if ($tab === 'general'): ?>
                <label>Blog Name</label>
                <input type="text" name="app_name" value="<?= htmlspecialchars($ini['app']['name'] ?? '') ?>" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:5px;">
                <label>Standardsprache</label>
                <select name="lang" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                    <option value="de" <?= ($ini['app']['lang']??'')=='de'?'selected':'' ?>>Deutsch</option>
                    <option value="en" <?= ($ini['app']['lang']??'')=='en'?'selected':'' ?>>English</option>
                </select>

            <?php elseif ($tab === 'database'): ?>
                <label>DB Host</label>
                <input type="text" name="host" value="<?= htmlspecialchars($ini['database']['host'] ?? '') ?>" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:5px;">
                <label>DB Name</label>
                <input type="text" name="dbname" value="<?= htmlspecialchars($ini['database']['dbname'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">

            <?php elseif ($tab === 'debug'): ?>
                <label style="display:block; margin-bottom:10px;">
                    <input type="checkbox" name="debug" <?= !empty($ini['debug']['enabled'])?'checked':'' ?>> Debug Modus aktivieren
                </label>
                <label style="display:block; margin-bottom:10px;">
                    <input type="checkbox" name="logs" <?= !empty($ini['logs']['enabled'])?'checked':'' ?>> Fehler-Logs aktivieren
                </label>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary" style="margin-top:20px;">Einstellungen speichern</button>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>