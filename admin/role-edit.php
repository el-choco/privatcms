<?php
declare(strict_types=1);
session_start();

$userRole = $_SESSION['admin']['role'] ?? 'viewer';
if (empty($_SESSION['admin']) || ($userRole !== 'admin' && $userRole !== 1)) { header('Location: /admin/'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

// Sprache laden für Permission-Übersetzungen
$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$permLang = $iniLang['permissions'] ?? []; 
$rolesLang = $iniLang['roles'] ?? [];

$id = (int)($_GET['id'] ?? 0);
$role = null;
$rolePerms = [];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetch();
    if (!$role) { header('Location: roles.php'); exit; }

    $stmtP = $pdo->prepare("SELECT permission_id FROM permission_role WHERE role_id = ?");
    $stmtP->execute([$id]);
    $rolePerms = $stmtP->fetchAll(PDO::FETCH_COLUMN);
}

$allPerms = $pdo->query("SELECT * FROM permissions ORDER BY id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); 
    $label = trim($_POST['label'] ?? '');
    $color = $_POST['color'] ?? '#718096';
    $desc = $_POST['description'] ?? '';
    $selectedPerms = $_POST['perms'] ?? [];

    if ($role && $role['is_system'] == 1) {
        $name = $role['name']; 
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE roles SET name=?, label=?, color=?, description=? WHERE id=?");
        $stmt->execute([$name, $label, $color, $desc, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO roles (name, label, color, description, is_system) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$name, $label, $color, $desc]);
        $id = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("DELETE FROM permission_role WHERE role_id = ?")->execute([$id]);
    if (!empty($selectedPerms)) {
        $insert = $pdo->prepare("INSERT INTO permission_role (role_id, permission_id) VALUES (?, ?)");
        foreach ($selectedPerms as $pid) {
            $insert->execute([$id, (int)$pid]);
        }
    }

    header('Location: roles.php'); exit;
}

require_once 'header.php';
?>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 40px; padding-bottom: 40px;">
        <div class="card" style="width: 100%; max-width: 800px; padding: 40px; border-top: 4px solid #805ad5;">
            <h1 style="margin: 0 0 30px 0; text-align: center; color: #1a202c;">
                <?= $id > 0 ? ($rolesLang['edit_title'] ?? 'Rolle bearbeiten') : ($rolesLang['create_title'] ?? 'Neue Rolle erstellen') ?>
            </h1>
            
            <form method="post">
                <div style="display:flex; gap: 20px; margin-bottom: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Interner Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($role['name'] ?? '') ?>" <?= ($role['is_system']??0) ? 'readonly style="background:#edf2f7; cursor:not-allowed;"' : '' ?> required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;">
                        <small style="color:#718096;">System ID (z.B. 'admin')</small>
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Anzeigename</label>
                        <input type="text" name="label" value="<?= htmlspecialchars($role['label'] ?? '') ?>" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;">
                    </div>
                </div>

                <div style="display:flex; gap: 20px; margin-bottom: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Farbe</label>
                        <div style="display:flex; gap:10px;">
                            <input type="color" name="color" value="<?= htmlspecialchars($role['color'] ?? '#718096') ?>" style="height:40px; width:60px; border:none; padding:0;">
                            <input type="text" value="<?= htmlspecialchars($role['color'] ?? '#718096') ?>" readonly style="flex:1; padding:10px; border:1px solid #cbd5e0; border-radius:6px; background:#f7fafc;">
                        </div>
                    </div>
                    <div style="flex: 2;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Beschreibung</label>
                        <input type="text" name="description" value="<?= htmlspecialchars($role['description'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;">
                    </div>
                </div>

                <hr style="margin: 30px 0; border:0; border-top:1px solid #e2e8f0;">

                <h3 style="margin-bottom: 15px; color: #2d3748;">Berechtigungen</h3>
                <div style="background: #f7fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                        <?php foreach($allPerms as $perm): ?>
                            <?php 
                                // Hier prüfen wir auf die Übersetzung
                                $displayLabel = $permLang[$perm['slug']] ?? $perm['description']; 
                            ?>
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 8px; border-radius:6px; transition:0.2s;" onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='transparent'">
                                <input type="checkbox" name="perms[]" value="<?= $perm['id'] ?>" <?= in_array($perm['id'], $rolePerms) ? 'checked' : '' ?> style="transform: scale(1.2); margin-top:4px;">
                                <span>
                                    <div style="font-weight: bold; font-size: 0.9rem; color:#2d3748;"><?= htmlspecialchars($displayLabel) ?></div>
                                    <div style="font-size: 0.75rem; color: #a0aec0; font-family:monospace;"><?= htmlspecialchars($perm['slug']) ?></div>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: 30px; text-align: right;">
                    <a href="roles.php" style="margin-right: 15px; color: #718096; text-decoration: none;">Abbrechen</a>
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>