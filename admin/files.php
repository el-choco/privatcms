<?php
declare(strict_types=1);
session_start();

// Sicherheit: Nur für eingeloggte Admins
if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

// Pfad-Konfiguration: Wir nutzen den Pfad, der durch Docker gemappt ist
$uploadDir = __DIR__ . '/../public/uploads/';

// Sicherstellen, dass das Verzeichnis existiert
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$message = '';
// Dateien einlesen (Punkte . und .. ignorieren)
$files = is_dir($uploadDir) ? array_diff(scandir($uploadDir), array('.', '..')) : [];

// Datei-Upload verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!is_writable($uploadDir)) {
        $message = '<span style="color:red;">Fehler: Keine Schreibrechte in /public/uploads/</span>';
    } else {
        $name = time() . '_' . basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $name)) {
            header("Location: files.php");
            exit;
        } else {
            $message = '<span style="color:red;">Fehler beim Verschieben der Datei.</span>';
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

include 'header.php';
?>

<style>
    /* Styling für das Link-Modal */
    #linkModal {
        display: none; 
        position: fixed; 
        z-index: 9999; 
        left: 0; top: 0; width: 100%; height: 100%; 
        background-color: rgba(0,0,0,0.6); 
        align-items: center; 
        justify-content: center;
        backdrop-filter: blur(2px);
    }
    .modal-content {
        background: white; 
        padding: 25px; 
        border-radius: 15px; 
        width: 90%; 
        max-width: 500px; 
        text-align: center;
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        animation: modalScale 0.2s ease-out;
    }
    @keyframes modalScale {
        from { transform: scale(0.8); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    .link-input {
        width: 100%; 
        padding: 12px; 
        margin: 20px 0;
        border: 2px solid #e2e8f0; 
        border-radius: 8px;
        background: #f8fafc; 
        font-family: 'Courier New', monospace;
        font-size: 13px;
        color: #2d3748;
        text-align: center;
    }
    .modal-btns {
        display: flex; 
        gap: 12px;
    }
    .modal-btn-copy {
        background: #3182ce; 
        color: white; 
        flex: 2;
        border: none;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
    }
    .modal-btn-close {
        background: #edf2f7; 
        color: #4a5568; 
        flex: 1;
        border: none;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
    }
</style>

<header class="top-header">
    <h1>📁 Dateiverwaltung</h1>
    <div style="font-size:14px; margin-top: 5px;"><?= $message ?></div>
</header>

<div class="content-area">
    <div class="card" style="padding: 25px; margin-bottom: 30px; background: #f0f9ff; border: 2px dashed #3182ce; border-radius: 12px;">
        <form method="post" enctype="multipart/form-data" style="display: flex; gap: 20px; align-items: center; justify-content: center;">
            <div style="flex: 1; max-width: 400px;">
                <input type="file" name="file" id="fileInput" required style="width: 100%;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">Datei hochladen</button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 25px;">
        <?php if (empty($files)): ?>
            <p class="muted" style="grid-column: 1/-1; text-align: center; padding: 40px;">Noch keine Dateien hochgeladen.</p>
        <?php endif; ?>

        <?php foreach ($files as $f): ?>
            <?php if (is_dir($uploadDir . $f)) continue; // Ordner überspringen ?>
            
            <div class="card" style="padding: 15px; text-align: center; transition: transform 0.2s;">
                <div style="height: 120px; background: #f1f5f9; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)): ?>
                        <img src="/uploads/<?= htmlspecialchars($f) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span style="font-size: 3rem;">📄</span>
                    <?php endif; ?>
                </div>
                
                <div style="font-size: 11px; font-weight: 600; margin-bottom: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #4a5568;" title="<?= htmlspecialchars($f) ?>">
                    <?= htmlspecialchars($f) ?>
                </div>
                
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-sm" style="flex: 1; background: #edf2f7; color: #2d3748; font-size: 11px; padding: 5px;" 
                            onclick="showLinkModal('/uploads/<?= htmlspecialchars($f) ?>')">Link kopieren</button>
                    <a href="?delete=<?= urlencode($f) ?>" class="btn btn-sm btn-danger" 
                       style="padding: 5px 12px; background: #feb2b2; color: #9b2c2c;" 
                       onclick="return confirm('Möchtest du diese Datei wirklich löschen?')">🗑️</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="linkModal">
    <div class="modal-content">
        <h3 style="margin-top: 0;">Vollständigen Link kopieren</h3>
        <p class="muted" style="font-size: 14px;">Aktueller Pfad zur Datei:</p>
        <input type="text" id="modalLinkInput" class="link-input" readonly>
        <div class="modal-btns">
            <button class="btn modal-btn-copy" onclick="copyModalLink()">Kopieren</button>
            <button class="btn modal-btn-close" onclick="closeModal()">Abbrechen</button>
        </div>
    </div>
</div>

<script>
/**
 * Zeigt das Modal mit dem VOLLSTÄNDIGEN Dateipfad an
 */
function showLinkModal(path) {
    const input = document.getElementById('modalLinkInput');
    
    // window.location.origin holt Protokoll + IP/Domain + Port (z.B. http://37.201.48.209:3333)
    const fullUrl = window.location.origin + path;
    
    input.value = fullUrl;
    const modal = document.getElementById('linkModal');
    modal.style.display = 'flex';
    input.select();
}

/**
 * Schließt das Modal
 */
function closeModal() {
    document.getElementById('linkModal').style.display = 'none';
}

/**
 * Kopiert den Text aus dem Modal-Input in die Zwischenablage
 */
function copyModalLink() {
    const input = document.getElementById('modalLinkInput');
    input.select();
    input.setSelectionRange(0, 99999); // Für Mobilgeräte

    navigator.clipboard.writeText(input.value).then(() => {
        const copyBtn = document.querySelector('.modal-btn-copy');
        const originalText = copyBtn.innerText;
        
        copyBtn.innerText = '✅ Kopiert!';
        copyBtn.style.background = '#2f855a';
        
        setTimeout(() => {
            copyBtn.innerText = originalText;
            copyBtn.style.background = '#3182ce';
            closeModal();
        }, 1000);
    }).catch(err => {
        alert('Fehler beim Kopieren: ' + err);
    });
}

// Schließen des Modals bei Klick auf den dunklen Hintergrund
window.onclick = function(event) {
    const modal = document.getElementById('linkModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

<?php include 'footer.php'; ?>