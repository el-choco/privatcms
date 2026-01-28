<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$t = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$mLang = $t['menu'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $label = trim($_POST['label']);
        $link = trim($_POST['link']);
        $type = $_POST['type'];
        $icon = trim($_POST['icon'] ?? '');
        
        $stmt = $pdo->query("SELECT MAX(position) FROM menu_items");
        $pos = (int)$stmt->fetchColumn() + 1;

        $stmt = $pdo->prepare("INSERT INTO menu_items (label, link, type, position, icon) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$label, $link, $type, $pos, $icon]);
    }
    elseif (isset($_POST['save_order'])) {
        $items = json_decode($_POST['order_data'], true);
        if (is_array($items)) {
            foreach ($items as $index => $id) {
                $stmt = $pdo->prepare("UPDATE menu_items SET position = ? WHERE id = ?");
                $stmt->execute([$index, (int)$id]);
            }
        }
    }
    elseif (isset($_POST['delete_item'])) {
        $id = (int)$_POST['item_id'];
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: menus.php");
    exit;
}

$pages = $pdo->query("SELECT * FROM pages WHERE status='published' ORDER BY title ASC")->fetchAll();
$menuItems = $pdo->query("SELECT * FROM menu_items ORDER BY position ASC")->fetchAll();

include 'header.php';
?>

<div class="admin-content">
    <h1 style="margin-bottom: 25px;"><?= htmlspecialchars($mLang['title'] ?? 'Menu') ?></h1>
    
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px; max-width: 1600px;">
        
        <div style="display:flex; flex-direction:column; gap:20px;">
            <div class="card" style="border-top: 5px solid #3182ce;">
                <h3><?= htmlspecialchars($mLang['add_page'] ?? 'Pages') ?></h3>
                <form method="POST">
                    <input type="hidden" name="add_item" value="1">
                    <input type="hidden" name="type" value="page">
                    <div class="form-group">
                        <label class="form-label"><?= htmlspecialchars($mLang['label_text'] ?? 'Page') ?></label>
                        <select name="link" class="form-control" onchange="this.form.label.value = this.options[this.selectedIndex].text">
                            <?php foreach($pages as $p): ?>
                                <option value="/p/<?= htmlspecialchars($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="label" value="<?= htmlspecialchars($pages[0]['title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon (FontAwesome)</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" name="icon" class="form-control" placeholder="fa-solid fa-house" list="fa-icons" oninput="document.getElementById('icon-preview-page').className = this.value">
                            <div style="width: 30px; text-align: center;"><i id="icon-preview-page" class=""></i></div>
                        </div>
                    </div>
                    <button type="submit" class="btn"><?= htmlspecialchars($mLang['btn_add'] ?? 'Add') ?></button>
                </form>
            </div>

            <div class="card" style="border-top: 5px solid #3182ce;">
                <h3><?= htmlspecialchars($mLang['add_custom'] ?? 'Custom Link') ?></h3>
                <form method="POST">
                    <input type="hidden" name="add_item" value="1">
                    <input type="hidden" name="type" value="custom">
                    <div class="form-group">
                        <label class="form-label"><?= htmlspecialchars($mLang['label_text'] ?? 'Label') ?></label>
                        <input type="text" name="label" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= htmlspecialchars($mLang['label_url'] ?? 'URL') ?></label>
                        <input type="text" name="link" class="form-control" placeholder="https://..." required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon (FontAwesome)</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" name="icon" class="form-control" placeholder="fa-solid fa-link" list="fa-icons" oninput="document.getElementById('icon-preview-custom').className = this.value">
                            <div style="width: 30px; text-align: center;"><i id="icon-preview-custom" class=""></i></div>
                        </div>
                    </div>
                    <button type="submit" class="btn"><?= htmlspecialchars($mLang['btn_add'] ?? 'Add') ?></button>
                </form>
            </div>
        </div>

        <div class="card" style="border-top: 5px solid #3182ce;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0;"><?= htmlspecialchars($mLang['structure'] ?? 'Structure') ?></h3>
                <form method="POST" id="saveOrderForm">
                    <input type="hidden" name="save_order" value="1">
                    <input type="hidden" name="order_data" id="orderData">
                    <button type="button" onclick="saveOrder()" class="btn btn-success" style="width:auto; padding:8px 20px;">
                        <i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars($mLang['save_structure'] ?? 'Save') ?>
                    </button>
                </form>
            </div>

            <ul class="menu-list" id="menuList" style="list-style:none; padding:0; margin:0;">
                <?php if(empty($menuItems)): ?>
                    <li style="text-align:center; color:#a0aec0; padding:40px; border:2px dashed #e2e8f0; border-radius:6px;">
                        <?= htmlspecialchars($mLang['no_items'] ?? 'Empty') ?>
                    </li>
                <?php else: ?>
                    <?php foreach($menuItems as $item): ?>
                        <li class="menu-item" data-id="<?= $item['id'] ?>" style="background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:8px; padding:12px; border-radius:6px; display:flex; justify-content:space-between; align-items:center; cursor:grab;">
                            <div style="display:flex; align-items:center;">
                                <i class="fa-solid fa-grip-vertical" style="color:#a0aec0; margin-right:15px;"></i>
                                <?php if(!empty($item['icon'])): ?>
                                    <i class="<?= htmlspecialchars($item['icon']) ?>" style="margin-right: 15px; color: #4a5568; width: 20px; text-align: center;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:600; color:#2d3748;"><?= htmlspecialchars($item['label']) ?></div>
                                <div style="font-size:0.8rem; color:#718096; margin-top:2px;">
                                    <span style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-size:0.7rem; color:#4a5568; text-transform:uppercase; margin-right:5px; font-weight:bold;"><?= strtoupper($item['type']) ?></span>
                                    <?= htmlspecialchars($item['link']) ?>
                                </div>
                            </div>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="delete_item" value="1">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button type="submit" onclick="return confirm('Delete?');" style="background:transparent; color:#fc8181; border:none; cursor:pointer; font-size:1rem; padding:5px;"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

    </div>
</div>

<datalist id="fa-icons">
    <option value="fa-solid fa-house">
    <option value="fa-solid fa-user">
    <option value="fa-solid fa-gear">
    <option value="fa-solid fa-envelope">
    <option value="fa-solid fa-magnifying-glass">
    <option value="fa-solid fa-right-to-bracket">
    <option value="fa-solid fa-bars">
    <option value="fa-brands fa-facebook">
    <option value="fa-brands fa-instagram">
    <option value="fa-brands fa-twitter">
</datalist>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    const el = document.getElementById('menuList');
    if (el) {
        const sortable = Sortable.create(el, {
            handle: '.menu-item',
            animation: 150
        });

        function saveOrder() {
            const order = sortable.toArray();
            document.getElementById('orderData').value = JSON.stringify(order);
            document.getElementById('saveOrderForm').submit();
        }
    }
</script>
</body>
</html>