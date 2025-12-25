<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

// Pfad-Anpassung: Wir gehen sicher, dass der Pfad stimmt
$uploadDir = __DIR__ . '/../public/uploads/';

// Versuche Ordner zu erstellen, falls nicht da
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true); 
}

$message = '';
// Falls das Verzeichnis trotzdem nicht existiert/lesbar ist, leeres Array nutzen
$files = is_dir($uploadDir) ? array_diff(scandir($uploadDir), array('.', '..')) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!is_writable($uploadDir)) {
        $message = '<span style="color:red;">Fehler: Keine Schreibrechte in /public/uploads/</span>';
    } else {
        $name = time() . '_' . basename($_FILES['file']['name']);
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $name);
        header("Location: files.php"); exit;
    }
}

if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    if (file_exists($uploadDir . $file)) unlink($uploadDir . $file);
    header("Location: files.php"); exit;
}

include 'header.php';
?>
<header class="top-header">
    <h1>📁 Dateiverwaltung</h1>
    <div style="font-size:14px;"><?= $message ?></div>
</header>
<div class="content-area">
    <div class="card" style="padding: 20px; margin-bottom: 25px; background: #ebf8ff; border: 2px dashed #3182ce;">
        <form method="post" enctype="multipart/form-data" style="display: flex; gap: 15px; align-items: center;">
            <input type="file" name="file" required>
            <button type="submit" class="btn btn-primary">Hochladen</button>
        </form>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
        <?php foreach ($files as $f): ?>
            <div class="card" style="padding: 10px; text-align: center;">
                <div style="height: 100px; background: #f7fafc; margin-bottom: 10px; display: flex; align-items: center; justify-content: center;">
                    <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)): ?>
                        <img src="/uploads/<?= $f ?>" style="max-width: 100%; max-height: 100%;">
                    <?php else: ?>
                        <span style="font-size: 2rem;">📄</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 11px; margin-bottom: 10px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= $f ?></div>
                <div style="display: flex; gap: 5px;">
                    <button class="btn" style="flex: 1; font-size: 10px;" onclick="navigator.clipboard.writeText('/uploads/<?= $f ?>'); alert('Pfad kopiert!')">Link</button>
                    <a href="?delete=<?= $f ?>" class="btn btn-danger" style="padding: 5px 10px;" onclick="return confirm('Löschen?')">🗑️</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include 'footer.php'; ?>