<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

$uploadDir = __DIR__ . '/../public/uploads/';

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

// Sprachdateien laden
$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$fLang = $t_temp['files'] ?? [];

$message = '';
$files = is_dir($uploadDir) ? array_diff(scandir($uploadDir), array('.', '..')) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    if (!is_writable($uploadDir)) {
        $message = '<span style="color:red;">' . ($fLang['error_write'] ?? 'Error') . '</span>';
    } else {
        $uploadedCount = 0;
        $totalFiles = count($_FILES['files']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $name = time() . '_' . $i . '_' . basename($_FILES['files']['name'][$i]);
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $uploadDir . $name)) {
                    $uploadedCount++;
                }
            }
        }
        
        if ($uploadedCount > 0) {
            $msgTemplate = $fLang['success_upload'] ?? '%d uploaded';
            header("Location: files.php?msg=" . urlencode(sprintf($msgTemplate, $uploadedCount)));
            exit;
        } else {
            $message = '<span style="color:red;">' . ($fLang['error_upload'] ?? 'Error') . '</span>';
        }
    }
}

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

require_once 'header.php';
?>

<style>
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
        width: 95%; padding: 12px; margin: 20px 0;
        border: 2px solid #e2e8f0; border-radius: 8px;
        background: #f8fafc; font-family: monospace; font-size: 13px; text-align: center;
    }
    .modal-btns { display: flex; gap: 12px; }
    .modal-btn-copy { background: #3182ce; color: white; flex: 2; border: none; padding: 10px; border-radius: 8px; font-weight: bold; cursor: pointer; }
    .modal-btn-close { background: #edf2f7; color: #4a5568; flex: 1; border: none; padding: 10px; border-radius: 8px; cursor: pointer; }
</style>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1000px;">

            <header style="margin-bottom: 30px;">
                <h1 style="margin:0; font-size: 1.5rem; color: #1a202c;"><?= htmlspecialchars($t['files']['manage_title']) ?></h1>
                <?php if ($displayMsg): ?>
                    <div style="font-size:14px; margin-top: 10px; color: #2f855a; font-weight: bold;"><?= htmlspecialchars((string)$displayMsg) ?></div>
                <?php endif; ?>
            </header>

            <div class="card" style="padding: 25px; margin-bottom: 30px; background: #f0f9ff; border: 2px dashed #3182ce; border-radius: 12px;">
                <form method="post" enctype="multipart/form-data" style="display: flex; gap: 20px; align-items: center; justify-content: center; flex-wrap: wrap;">
                    
                    <div style="flex: 1; min-width: 250px; position: relative;">
                        <input type="file" name="files[]" id="fileInput" multiple required 
                               style="opacity: 0; position: absolute; z-index: -1; width: 0.1px; height: 0.1px;"
                               onchange="updateFileLabel(this)">
                        
                        <label for="fileInput" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <span style="background: #edf2f7; color: #2d3748; border: 1px solid #cbd5e0; padding: 8px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; white-space: nowrap;">
                                <?= htmlspecialchars($t['files']['input_label'] ?? 'Choose') ?>
                            </span>
                            <span id="fileNameDisplay" style="color: #718096; font-size: 14px; font-style: italic;">
                                <?= htmlspecialchars($t['files']['input_empty'] ?? 'No file selected') ?>
                            </span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">
                        <?= htmlspecialchars($t['files']['upload_btn']) ?>
                    </button>
                </form>
                <p style="text-align: center; font-size: 12px; color: #718096; margin-top: 10px;">
                    <?= $t['files']['upload_hint'] ?>
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
                <?php if (empty($files)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #a0aec0; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <?= htmlspecialchars($t['files']['no_files']) ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($files as $f): ?>
                    <?php if (is_dir($uploadDir . $f)) continue; ?>
                    <div class="card" style="padding: 15px; text-align: center; border-top: 4px solid #cbd5e0; transition: transform 0.2s;">
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
                                    onclick="showLinkModal('/uploads/<?= htmlspecialchars((string)$f) ?>')">
                                <?= htmlspecialchars($t['files']['copy_link']) ?>
                            </button>
                            <a href="?delete=<?= urlencode((string)$f) ?>" class="btn btn-sm btn-danger" 
                               onclick="return confirm('<?= htmlspecialchars($t['files']['delete_confirm']) ?>')" style="color: #e53e3e;">
                                🗑️
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div id="linkModal">
    <div class="modal-content">
        <h3 style="margin-top: 0;"><?= htmlspecialchars($t['files']['modal_title']) ?></h3>
        <input type="text" id="modalLinkInput" class="link-input" readonly>
        <div class="modal-btns">
            <button class="btn modal-btn-copy" onclick="copyModalLink()"><?= htmlspecialchars($t['files']['modal_copy']) ?></button>
            <button class="btn modal-btn-close" onclick="closeModal()"><?= htmlspecialchars($t['files']['modal_cancel']) ?></button>
        </div>
    </div>
</div>

<script>
// Texte für JS aus PHP holen
const txtCopied = "<?= htmlspecialchars($t['files']['modal_copied']) ?>";
const txtError = "<?= htmlspecialchars($t['files']['error_copy']) ?>";
const txtSelected = "<?= htmlspecialchars($t['files']['input_selected'] ?? '%d selected') ?>";
const txtEmpty = "<?= htmlspecialchars($t['files']['input_empty'] ?? 'No file') ?>";

function updateFileLabel(input) {
    const label = document.getElementById('fileNameDisplay');
    const count = input.files.length;
    if (count === 0) {
        label.innerText = txtEmpty;
        label.style.color = '#718096';
        label.style.fontWeight = 'normal';
    } else {
        label.innerText = txtSelected.replace('%d', count);
        label.style.color = '#2f855a';
        label.style.fontWeight = 'bold';
    }
}

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
    } catch (err) { alert(txtError); }
}

function handleCopySuccess(btn, originalText) {
    btn.innerText = txtCopied;
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