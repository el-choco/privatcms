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
$catLang = $t_temp['categories'] ?? [];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    $color = $_POST['color'] ?? '#3182ce';
    
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, color) VALUES (?, ?, ?)");
        $stmt->execute([$name, $slug, $color]);
        $message = $catLang['created'] ?? 'Created!';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    $color = $_POST['color'] ?? '#3182ce';

    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, color = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $color, $id]);
        $message = $catLang['updated'] ?? 'Updated!';
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("UPDATE posts SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    $message = $catLang['deleted'] ?? 'Deleted!';
    header("Location: categories.php"); exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

require_once 'header.php';
?>

<style>
    /* Modal Styles */
    #editModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); justify-content: center; align-items: center; }
    .modal-box { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
    .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-weight:700; font-size:1.2rem; color:#2d3748; }
    .form-group { margin-bottom: 15px; }
    .form-label { display: block; margin-bottom: 5px; font-weight: 600; color: #4a5568; font-size: 0.9rem; }
    .form-input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
    .color-picker { height: 42px; padding: 5px; cursor: pointer; }
    .cat-badge { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
</style>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 20px;">
        <div style="width: 100%; max-width: 1530px;">
            
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin:0; font-size: 2rem; color: #1a202c;"><?= htmlspecialchars($catLang['manage_title'] ?? 'Manage Categories') ?></h1>
                <a href="posts.php" class="btn" style="background: #edf2f7; color: #4a5568; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
                    <?= htmlspecialchars($catLang['back_to_posts'] ?? 'Back') ?>
                </a>
            </header>

            <?php if ($message): ?>
                <div style="background: #c6f6d5; color: #22543d; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; border-left: 5px solid #38a169;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="card" style="padding: 25px; margin-bottom: 30px; border-top: 4px solid #3182ce; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <h3 style="margin-top:0; font-size: 1rem; color: #718096; text-transform: uppercase; letter-spacing: 1px;">
                    <?= htmlspecialchars($catLang['create_title'] ?? 'Create New Category') ?>
                </h3>
                <form method="POST" style="display: flex; gap: 10px; align-items:center;">
                    <input type="color" name="color" value="#3182ce" class="form-input color-picker" style="width:60px; flex-shrink:0;" title="<?= htmlspecialchars($catLang['label_color'] ?? 'Color') ?>">
                    <input type="text" name="name" placeholder="<?= htmlspecialchars($catLang['placeholder_example'] ?? 'e.g. Technology') ?>" required 
                           class="form-input" style="flex: 1; outline-color: #3182ce;">
                    <button type="submit" name="add_category" class="btn btn-primary" style="padding: 0 30px; font-weight: bold; height:42px;">
                        <?= htmlspecialchars($catLang['button_create'] ?? 'Create') ?>
                    </button>
                </form>
            </div>

            <div class="card" style="padding: 0; overflow: hidden; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 12px; border-top: 5px solid #3182ce;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 18px; color: #718096; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 40%;">
                                <?= htmlspecialchars($catLang['label_name'] ?? 'Name') ?>
                            </th>
                            <th style="padding: 18px; color: #718096; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 40%;">
                                <?= htmlspecialchars($catLang['label_slug'] ?? 'Slug') ?>
                            </th>
                            <th style="padding: 18px; color: #718096; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align: right;">
                                <?= htmlspecialchars($catLang['label_actions'] ?? 'Actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr style="border-bottom: 1px solid #edf2f7; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfd'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 18px; display:flex; align-items:center;">
                                <span class="cat-badge" style="background-color: <?= htmlspecialchars($cat['color'] ?? '#3182ce') ?>;"></span>
                                <span style="font-weight: 700; color: #2d3748; font-size: 1.1rem;">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </span>
                            </td>
                            <td style="padding: 18px;">
                                <span style="color: #a0aec0; font-family: 'Fira Code', monospace; font-size: 0.9rem;">/<?= htmlspecialchars($cat['slug']) ?></span>
                            </td>
                            <td style="padding: 18px; text-align: right; white-space: nowrap;">
                                <button type="button" onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>', '<?= htmlspecialchars($cat['color'] ?? '#3182ce') ?>')" 
                                        style="background: none; border: none; color: #3182ce; font-weight: bold; cursor: pointer; margin-right: 15px;">
                                    <?= htmlspecialchars($catLang['button_edit'] ?? 'Edit') ?>
                                </button>
                                
                                <a href="?delete=<?= $cat['id'] ?>" 
                                   onclick="return confirm('<?= htmlspecialchars($catLang['confirm_delete'] ?? 'Delete?') ?>')"
                                   style="color: #e53e3e; text-decoration: none; font-size: 0.9rem; font-weight: bold;">
                                    <?= htmlspecialchars($catLang['button_delete'] ?? 'Delete') ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="3" style="padding: 30px; text-align: center; color: #a0aec0;">
                                    <?= htmlspecialchars($catLang['no_categories'] ?? 'No categories found.') ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="editModal">
    <div class="modal-box">
        <form method="POST">
            <div class="modal-header">
                <span><?= htmlspecialchars($catLang['modal_edit_title'] ?? 'Edit Category') ?></span>
                <button type="button" onclick="closeEditModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            
            <input type="hidden" name="id" id="edit-id">
            
            <div class="form-group">
                <label class="form-label"><?= htmlspecialchars($catLang['label_name'] ?? 'Name') ?></label>
                <input type="text" name="name" id="edit-name" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?= htmlspecialchars($catLang['label_color'] ?? 'Color') ?></label>
                <div style="display:flex; gap:10px;">
                    <input type="color" name="color" id="edit-color" class="form-input color-picker" style="width:100px;">
                    <input type="text" id="edit-color-text" class="form-input" readonly style="background:#f7fafc; color:#718096; font-family:monospace;">
                </div>
            </div>

            <div style="text-align:right; margin-top:20px;">
                <button type="button" onclick="closeEditModal()" class="btn" style="background:#edf2f7; color:#4a5568; margin-right:10px;">
                    <?= htmlspecialchars($catLang['button_cancel'] ?? 'Cancel') ?>
                </button>
                <button type="submit" name="update_category" class="btn btn-primary">
                    <?= htmlspecialchars($catLang['button_save'] ?? 'Save Changes') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, name, color) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-color').value = color;
        document.getElementById('edit-color-text').value = color;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    document.getElementById('edit-color').addEventListener('input', function(e) {
        document.getElementById('edit-color-text').value = e.target.value;
    });

    window.onclick = function(e) {
        if (e.target == document.getElementById('editModal')) {
            closeEditModal();
        }
    }
</script>

<?php include 'footer.php'; ?>