<?php
declare(strict_types=1);
session_start();

// Sicherheit: Nur für eingeloggte Admins
if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

// Pfad-Konfiguration: Nutzt den Docker-Mount
$uploadDir = __DIR__ . '/../public/uploads/';

// Sicherstellen, dass das Verzeichnis existiert
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$message = '';
// Dateien einlesen
$files = is_dir($uploadDir) ? array_diff(scandir($uploadDir), array('.', '..')) : [];

// --- MEHRFACH-UPLOAD VERARBEITEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    if (!is_writable($uploadDir)) {
        $message = '<span style="color:red;">Fehler: Keine Schreibrechte in /public/uploads/</span>';
    } else {
        $uploadedCount = 0;
        $totalFiles = count($_FILES['files']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                // Eindeutigen Namen generieren
                $name = time() . '_' . $i . '_' . basename($_FILES['files']['name'][$i]);
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $uploadDir . $name)) {
                    $uploadedCount++;
                }
            }
        }
        
        if ($uploadedCount > 0) {
            header("Location: files.php?msg=" . urlencode("$uploadedCount Datei(en) erfolgreich hochgeladen."));
            exit;
        } else {
            $message = '<span style="color:red;">Fehler beim Hochladen der Dateien.</span>';
        }
    }
}

// Datei löschen
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $filePath = $uploadDir . $file;
    if (file_exists($filePath) && is_file($filePath)) {
        unlink($filePath);
    }
    header("Location: files.php");
    exit;
}

$displayMsg = $_GET['msg'] ?? $message;

include 'header.php';
?>

<style>
    /* Modal Styling */
    #linkModal {
        display: none; position: fixed; z-index: 9999; 
        left: 0; top: 0; width: 100%; height: 100%; 
        background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center;
        backdrop-filter: blur(2px);
    }
    .modal-content {
        background: white; padding: 25px; border-radius: 15px; 
        width: 90%; max-width: 500px; text-align: center;
        box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: modalScale 0.2s ease-out;
    }
    @keyframes modalScale { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .link-input {
        width: 100%; padding: 12px; margin: 20px 0;
        border: 2px solid #e2e8f0; border-radius: 8px;
        background: #f8fafc; font-family: monospace; font-size: 13px; text-align: center;
    }
    .modal-btns { display: flex; gap: 12px; }
    .modal-btn-copy { background: #3182ce; color: white; flex: 2; border: none; padding: 10px; border-radius: 8px; font-weight: bold; cursor: pointer; }
    .modal-btn-close { background: #edf2f7; color: #4a5568; flex: 1; border: none; padding: 10px; border-radius: 8px; cursor: pointer; }
</style>

<header class="top-header">
    <h1>📁 Dateiverwaltung</h1>
    <div style="font-size:14px; margin-top: 5px;"><?= htmlspecialchars((string)$displayMsg) ?></div>
</header>

<div class="content-area">
    <div class="card" style="padding: 25px; margin-bottom: 30px; background: #f0f9ff; border: 2px dashed #3182ce; border-radius: 12px;">
        <form method="post" enctype="multipart/form-data" style="display: flex; gap: 20px; align-items: center; justify-content: center;">
            <div style="flex: 1; max-width: 400px;">
                <input type="file" name="files[]" id="fileInput" multiple required style="width: 100%;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">Dateien hochladen</button>
        </form>
        <p style="text-align: center; font-size: 12px; color: #718096; margin-top: 10px;">
            Tipp: Halte <b>Strg</b> (oder Cmd) gedrückt, um mehrere Bilder auszuwählen.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 25px;">
        <?php if (empty($files)): ?>
            <p class="muted" style="grid-column: 1/-1; text-align: center; padding: 40px;">Noch keine Dateien hochgeladen.</p>
        <?php endif; ?>

        <?php foreach ($files as $f): ?>
            <?php if (is_dir($uploadDir . $f)) continue; ?>
            <div class="card" style="padding: 15px; text-align: center;">
                <div style="height: 120px; background: #f1f5f9; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', (string)$f)): ?>
                        <img src="/uploads/<?= htmlspecialchars((string)$f) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span style="font-size: 3rem;">📄</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 11px; font-weight: 600; margin-bottom: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars((string)$f) ?>">
                    <?= htmlspecialchars((string)$f) ?>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-sm" style="flex: 1; background: #edf2f7; font-size: 11px;" 
                            onclick="showLinkModal('/uploads/<?= htmlspecialchars((string)$f) ?>')">Link kopieren</button>
                    <a href="?delete=<?= urlencode((string)$f) ?>" class="btn btn-sm btn-danger" 
                       onclick="return confirm('Datei löschen?')">🗑️</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="linkModal">
    <div class="modal-content">
        <h3 style="margin-top: 0;">Link kopieren</h3>
        <input type="text" id="modalLinkInput" class="link-input" readonly>
        <div class="modal-btns">
            <button class="btn modal-btn-copy" onclick="copyModalLink()">Kopieren</button>
            <button class="btn modal-btn-close" onclick="closeModal()">Abbrechen</button>
        </div>
    </div>
</div>

<script>
function showLinkModal(path) {
    const input = document.getElementById('modalLinkInput');
    input.value = window.location.origin + path;
    document.getElementById('linkModal').style.display = 'flex';
    input.select();
}

function closeModal() { document.getElementById('linkModal').style.display = 'none'; }

function copyModalLink() {
    const input = document.getElementById('modalLinkInput');
    const copyBtn = document.querySelector('.modal-btn-copy');
    const originalText = copyBtn.innerText;
    input.select();
    input.setSelectionRange(0, 99999);

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(() => {
            handleCopySuccess(copyBtn, originalText);
        }).catch(() => fallbackCopy(input, copyBtn, originalText));
    } else {
        fallbackCopy(input, copyBtn, originalText);
    }
}

function fallbackCopy(input, btn, originalText) {
    try {
        if (document.execCommand('copy')) {
            handleCopySuccess(btn, originalText);
        }
    } catch (err) { alert('Fehler beim Kopieren.'); }
}

function handleCopySuccess(btn, originalText) {
    btn.innerText = '✅ Kopiert!';
    btn.style.background = '#2f855a';
    setTimeout(() => {
        btn.innerText = originalText;
        btn.style.background = '#3182ce';
        closeModal();
    }, 1200);
}

window.onclick = function(event) {
    if (event.target == document.getElementById('linkModal')) closeModal();
}
</script>

<?php include 'footer.php'; ?>