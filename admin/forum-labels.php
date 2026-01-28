<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t_temp = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$l = $t_temp['forum_labels'] ?? []; 
$cmn = $t_temp['common'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $title = trim($_POST['title'] ?? '');
    $css = trim($_POST['css_class'] ?? '');

    if ($title && $css) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE forum_labels SET title = ?, css_class = ? WHERE id = ?");
            $stmt->execute([$title, $css, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO forum_labels (title, css_class) VALUES (?, ?)");
            $stmt->execute([$title, $css]);
        }
    }
    header("Location: forum-labels.php");
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM forum_labels WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    header("Location: forum-labels.php");
    exit;
}

$labels = $pdo->query("SELECT * FROM forum_labels ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php'; 
?>

<style>
    .badge { display: inline-block; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #fff; line-height:1; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    
    .badge-red    { background-color: #e53e3e; } 
    .badge-orange { background-color: #dd6b20; }
    .badge-yellow { background-color: #d69e2e; }            
    .badge-green  { background-color: #38a169; } 
    .badge-teal   { background-color: #319795; } 
    .badge-blue   { background-color: #3182ce; }
    .badge-cyan   { background-color: #00b5d8; } 
    .badge-indigo { background-color: #5a67d8; } 
    .badge-purple { background-color: #805ad5; } 
    .badge-pink   { background-color: #d53f8c; } 
    .badge-gray   { background-color: #718096; }
    .badge-dark   { background-color: #2d3748; }
    
    .color-option { cursor: pointer; display: inline-block; width: 24px; height: 24px; border-radius: 50%; margin-right: 5px; border: 2px solid transparent; opacity: 0.8; transition: 0.2s; margin-bottom: 5px; }
    .color-option:hover, .color-option.active { opacity: 1; transform: scale(1.15); border-color: #718096; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
</style>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1530px;">

            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="margin:0; font-size: 2rem; color: #1a202c;">
                    <?= htmlspecialchars($l['title'] ?? 'Forum Labels') ?>
                </h1>
                <button class="btn btn-primary" onclick="openModal()" style="border-radius:8px;">
                    <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($l['create_new'] ?? 'New Label') ?>
                </button>
            </header>

            <div class="card" style="border-top: 4px solid #3182ce; border-radius:8px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($l['col_title'] ?? 'Title') ?>
                            </th>
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($l['col_preview'] ?? 'Preview') ?>
                            </th>
                            <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($l['col_class'] ?? 'CSS Class') ?>
                            </th>
                            <th style="padding: 15px; text-align: right; color: #718096; font-size: 12px; text-transform: uppercase;">
                                <?= htmlspecialchars($cmn['actions'] ?? 'Actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($labels)): ?>
                            <tr>
                                <td colspan="4" style="padding: 40px; text-align: center; color: #a0aec0;">
                                    <?= htmlspecialchars($l['no_labels'] ?? 'No labels found.') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($labels as $lbl): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 15px; font-weight: 600; color: #2d3748;">
                                    <?= htmlspecialchars($lbl['title']) ?>
                                </td>
                                <td style="padding: 15px;">
                                    <span class="badge <?= htmlspecialchars($lbl['css_class']) ?>">
                                        <?= htmlspecialchars($lbl['title']) ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; font-family: monospace; color: #718096;">
                                    <?= htmlspecialchars($lbl['css_class']) ?>
                                </td>
                                <td style="padding: 15px; text-align: right;">
                                    <?php $json = htmlspecialchars(json_encode($lbl), ENT_QUOTES, 'UTF-8'); ?>
                                    <button class="btn" onclick='openModal(<?= $json ?>)' style="background: #fff; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius:8px; margin-right:5px;" title="<?= htmlspecialchars($cmn['edit'] ?? 'Edit') ?>">
                                        ✏️
                                    </button>
                                    <a href="?delete=<?= $lbl['id'] ?>" class="btn" onclick="return confirm('<?= htmlspecialchars($l['delete_confirm'] ?? 'Delete?') ?>')" style="background: #fff; border: 1px solid #fed7d7; color: #e53e3e; padding: 5px 10px; border-radius:8px;" title="<?= htmlspecialchars($cmn['delete'] ?? 'Delete') ?>">
                                        🗑️
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="labelModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:400px; padding:30px; border-radius:12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <h3 id="modalTitle" style="margin-top:0; margin-bottom:20px;"><?= htmlspecialchars($l['modal_title_new'] ?? 'New Label') ?></h3>
        
        <form method="POST">
            <input type="hidden" name="id" id="inpId">
            
            <div style="margin-bottom:15px;">
                <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.9rem;"><?= htmlspecialchars($l['label_title'] ?? 'Title') ?></label>
                <input type="text" name="title" id="inpTitle" class="input" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px;" placeholder="<?= htmlspecialchars($l['placeholder_title'] ?? 'e.g. Help') ?>">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-weight:600; margin-bottom:10px; font-size:0.9rem;"><?= htmlspecialchars($l['label_class'] ?? 'Color') ?></label>
                <input type="text" name="css_class" id="inpClass" class="input" required style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:10px;" placeholder="<?= htmlspecialchars($l['placeholder_class'] ?? 'badge-blue') ?>">
                
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <span class="color-option badge-red" onclick="setColor('badge-red')" title="Red"></span>
                    <span class="color-option badge-orange" onclick="setColor('badge-orange')" title="Orange"></span>
                    <span class="color-option badge-yellow" onclick="setColor('badge-yellow')" title="Yellow"></span>
                    <span class="color-option badge-green" onclick="setColor('badge-green')" title="Green"></span>
                    <span class="color-option badge-teal" onclick="setColor('badge-teal')" title="Teal"></span>
                    <span class="color-option badge-blue" onclick="setColor('badge-blue')" title="Blue"></span>
                    <span class="color-option badge-cyan" onclick="setColor('badge-cyan')" title="Cyan"></span>
                    <span class="color-option badge-indigo" onclick="setColor('badge-indigo')" title="Indigo"></span>
                    <span class="color-option badge-purple" onclick="setColor('badge-purple')" title="Purple"></span>
                    <span class="color-option badge-pink" onclick="setColor('badge-pink')" title="Pink"></span>
                    <span class="color-option badge-gray" onclick="setColor('badge-gray')" title="Gray"></span>
                    <span class="color-option badge-dark" onclick="setColor('badge-dark')" title="Dark"></span>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn" onclick="document.getElementById('labelModal').style.display='none'" style="background:#edf2f7; border-radius:8px; padding:8px 16px;">
                    <?= htmlspecialchars($cmn['cancel'] ?? 'Cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary" style="border-radius:8px; padding:8px 16px;">
                    <?= htmlspecialchars($cmn['save'] ?? 'Save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(data = null) {
    const modal = document.getElementById('labelModal');
    const title = document.getElementById('modalTitle');
    const inpId = document.getElementById('inpId');
    const inpTitle = document.getElementById('inpTitle');
    const inpClass = document.getElementById('inpClass');

    modal.style.display = 'flex';

    if (data) {
        title.innerText = "<?= htmlspecialchars($l['modal_title_edit'] ?? 'Edit Label') ?>";
        inpId.value = data.id;
        inpTitle.value = data.title;
        inpClass.value = data.css_class;
    } else {
        title.innerText = "<?= htmlspecialchars($l['modal_title_new'] ?? 'New Label') ?>";
        inpId.value = "";
        inpTitle.value = "";
        inpClass.value = "badge-blue";
    }
}

function setColor(cls) {
    document.getElementById('inpClass').value = cls;
}

window.onclick = function(e) {
    const modal = document.getElementById('labelModal');
    if (e.target == modal) modal.style.display = 'none';
}
</script>

<?php include 'footer.php'; ?>