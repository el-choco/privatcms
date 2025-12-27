<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$uploadDir = __DIR__ . '/../public/uploads/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$fLang = $t_temp['files'] ?? [];

$message = '';
$files = is_dir($uploadDir) ? array_diff(scandir($uploadDir), array('.', '..')) : [];

usort($files, function($a, $b) use ($uploadDir) {
    return filemtime($uploadDir . $b) - filemtime($uploadDir . $a);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    if (!is_writable($uploadDir)) {
        $message = '<div class="alert error">' . ($fLang['error_write'] ?? 'Upload directory not writable') . '</div>';
    } else {
        $uploadedCount = 0;
        $totalFiles = count($_FILES['files']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['files']['name'][$i], PATHINFO_EXTENSION);
                $name = time() . '_' . $i . '_' . pathinfo($_FILES['files']['name'][$i], PATHINFO_FILENAME);
                $name = preg_replace('/[^a-z0-9_-]/i', '_', $name) . '.' . $ext;
                
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $uploadDir . $name)) {
                    $uploadedCount++;
                }
            }
        }
        
        if ($uploadedCount > 0) {
            $msgTemplate = $fLang['success_upload'] ?? '%d files uploaded successfully';
            header("Location: files.php?msg=" . urlencode(sprintf($msgTemplate, $uploadedCount)));
            exit;
        } else {
            $message = '<div class="alert error">' . ($fLang['error_upload'] ?? 'Upload failed') . '</div>';
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

$displayMsg = $_GET['msg'] ?? null;
if ($displayMsg) {
    $message = '<div class="alert success">' . htmlspecialchars((string)$displayMsg) . '</div>';
}

require_once 'header.php';
?>

<style>
    .file-manager-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    
    /* Upload Zone */
    .upload-area { 
        background: #f8fafc; border: 2px dashed #cbd5e0; border-radius: 12px; padding: 30px; 
        text-align: center; transition: all 0.3s; cursor: pointer; margin-bottom: 30px; display: flex; flex-direction: column-reverse;; }
    .upload-area:hover, .upload-area.dragover { border-color: #3182ce; background: #ebf8ff; }
    .upload-icon { font-size: 3rem; color: #a0aec0; margin-bottom: 10px; }
    
    .files-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
    .file-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; 
        transition: transform 0.2s, box-shadow 0.2s; position: relative;
        display: flex; flex-direction: column;
    }
    .file-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: #bee3f8; }
    
    .file-preview { 
        height: 140px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; 
        overflow: hidden; position: relative; transition: opacity 0.2s;
    }
    .file-preview img { width: 100%; height: 100%; object-fit: cover; }
    .file-preview:hover { opacity: 0.9; }
    .file-icon { font-size: 3.5rem; color: #718096; }
    
    .file-info { padding: 12px; flex-grow: 1; display: flex; flex-direction: column; }
    .file-name { font-size: 0.85rem; font-weight: 600; color: #2d3748; margin-bottom: 5px; word-break: break-all; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .file-meta { font-size: 0.75rem; color: #718096; margin-bottom: 10px; }
    
    .file-actions { display: flex; gap: 5px; margin-top: auto; }
    .btn-file { flex: 1; font-size: 0.8rem; padding: 6px; }
    
    /* Modal Styles */
    #linkModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); justify-content: center; align-items: center; }
    .modal-box { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center; }
    .modal-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 15px; color: #2d3748; }
    .link-input { width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; background: #f7fafc; font-family: monospace; text-align: center; margin-bottom: 20px; font-size: 0.9rem; box-sizing: border-box; }
    
    /* Lightbox Styles */
    #lightbox { display: none; position: fixed; z-index: 11000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
    .lightbox-content { position: relative; max-width: 90%; max-height: 90%; display: flex; justify-content: center; align-items: center; }
    .lightbox-content img { max-width: 100%; max-height: 90vh; border-radius: 4px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); }
    .lightbox-close { position: absolute; top: -40px; right: 0; color: white; font-size: 2rem; cursor: pointer; transition: 0.2s; }
    .lightbox-close:hover { color: #fc8181; }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .alert.success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
    .alert.error { background: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
</style>

<div class="admin-content" style=" margin-left: 50px; margin-right: 50px;">
    <div class="file-manager-header">
        <h1 style="margin:0;"><?= htmlspecialchars($fLang['manage_title'] ?? 'File Manager') ?></h1>
        <span style="background:#e2e8f0; padding:5px 10px; border-radius:20px; font-size:0.8rem; font-weight:bold; color:#4a5568;">
            <?= count($files) ?> Items
        </span>
    </div>

    <?= $message ?>

    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <label class="upload-area" id="dropZone">
            <input type="file" name="files[]" id="fileInput" multiple required style="display:none" onchange="this.form.submit()">
            <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
            <div style="font-weight:600; font-size:1.1rem; color:#2d3748; margin-bottom:5px;">
                <?= htmlspecialchars($fLang['upload_btn'] ?? 'Click or Drag files here to upload') ?>
            </div>
            <div style="color:#718096; font-size:0.9rem;">
                JPG, PNG, GIF, PDF supported
            </div>
        </label>
    </form>

    <div class="files-grid">
        <?php if (empty($files)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:50px; color:#a0aec0; border:2px dashed #e2e8f0; border-radius:12px;">
                <i class="fa-regular fa-folder-open" style="font-size:3rem; margin-bottom:15px; display:block;"></i>
                <?= htmlspecialchars($fLang['no_files'] ?? 'No files uploaded yet') ?>
            </div>
        <?php else: ?>
            <?php foreach ($files as $f): 
                $filePath = $uploadDir . $f;
                $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f);
                $size = is_file($filePath) ? round(filesize($filePath) / 1024, 1) . ' KB' : '';
                $date = is_file($filePath) ? date('d.m.y', filemtime($filePath)) : '';
            ?>
                <?php if (is_dir($filePath)) continue; ?>
                
                <div class="file-card">
                    <div class="file-preview" <?php if ($isImage): ?>onclick="openLightbox('/uploads/<?= htmlspecialchars($f) ?>')" style="cursor:zoom-in" title="Vorschau"<?php endif; ?>>
                        <?php if ($isImage): ?>
                            <img src="/uploads/<?= htmlspecialchars($f) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="file-icon">
                                <?php if(preg_match('/\.pdf$/i', $f)): ?><i class="fa-solid fa-file-pdf" style="color:#e53e3e;"></i>
                                <?php elseif(preg_match('/\.zip$/i', $f)): ?><i class="fa-solid fa-file-zipper" style="color:#d69e2e;"></i>
                                <?php else: ?><i class="fa-solid fa-file" style="color:#718096;"></i><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="file-info">
                        <div class="file-name" title="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></div>
                        <div class="file-meta"><?= $size ?> • <?= $date ?></div>
                        
                        <div class="file-actions">
                            <button class="btn btn-primary btn-file" onclick="showLinkModal('/uploads/<?= htmlspecialchars($f) ?>')">
                                <i class="fa-solid fa-link"></i> Link
                            </button>
                            <a href="?delete=<?= urlencode($f) ?>" class="btn btn-danger btn-file" onclick="return confirm('<?= htmlspecialchars($fLang['delete_confirm'] ?? 'Delete this file?') ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="linkModal">
    <div class="modal-box">
        <div class="modal-title"><?= htmlspecialchars($fLang['modal_title'] ?? 'File Link') ?></div>
        <input type="text" id="modalLinkInput" class="link-input" readonly>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-primary" style="flex:1;" onclick="copyModalLink()"><?= htmlspecialchars($fLang['modal_copy'] ?? 'Copy') ?></button>
            <button class="btn" style="flex:1; background:#edf2f7; color:#4a5568;" onclick="closeModal()"><?= htmlspecialchars($fLang['modal_cancel'] ?? 'Close') ?></button>
        </div>
    </div>
</div>

<div id="lightbox" onclick="closeLightbox()">
    <div class="lightbox-content" onclick="event.stopPropagation()">
        <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
        <img id="lightboxImg" src="">
    </div>
</div>

<script>
    const dropZone = document.getElementById('dropZone');
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');

    // Drag & Drop Logic
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        fileInput.files = e.dataTransfer.files;
        uploadForm.submit();
    });

    // Modal Logic
    function showLinkModal(path) {
        const input = document.getElementById('modalLinkInput');
        input.value = window.location.origin + path;
        document.getElementById('linkModal').style.display = 'flex';
        input.select();
    }
    function closeModal() { document.getElementById('linkModal').style.display = 'none'; }
    
    function copyModalLink() {
        const input = document.getElementById('modalLinkInput');
        input.select();
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(handleCopySuccess);
        } else {
            // Fallback for non-secure context
            try { document.execCommand('copy'); handleCopySuccess(); } catch (e) {}
        }
    }

    function handleCopySuccess() {
        const btn = document.querySelector('#linkModal .btn-primary');
        const originalText = btn.innerText;
        btn.innerText = "<?= htmlspecialchars($fLang['modal_copied'] ?? 'Copied!') ?>";
        btn.style.background = "#38a169";
        setTimeout(() => {
            btn.innerText = originalText;
            btn.style.background = "#3182ce";
            closeModal();
        }, 1000);
    }
    
    function openLightbox(url) {
        const lb = document.getElementById('lightbox');
        const img = document.getElementById('lightboxImg');
        img.src = url;
        lb.style.display = 'flex';
    }
    
    function closeLightbox() {
        const lb = document.getElementById('lightbox');
        const img = document.getElementById('lightboxImg');
        lb.style.display = 'none';
        img.src = '';
    }

    window.onclick = function(e) {
        if(e.target == document.getElementById('linkModal')) closeModal();
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === "Escape") {
            closeModal();
            closeLightbox();
        }
    });
</script>

<?php include 'footer.php'; ?>