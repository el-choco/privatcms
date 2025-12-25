<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$message = '';

// 1. NEUE KATEGORIE ERSTELLEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        $stmt->execute([$name, $slug]);
        $message = "Kategorie erstellt!";
    }
}

// 2. KATEGORIE AKTUALISIEREN (Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $id]);
        $message = "Kategorie aktualisiert!";
    }
}

// 3. KATEGORIE LÖSCHEN
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("UPDATE posts SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    $message = "Kategorie gelöscht!";
    header("Location: categories.php"); exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
include 'header.php';
?>

<div class="content-area" style="width: 100%; max-width: 100%; padding: 20px; box-sizing: border-box;">
    
    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="margin:0; font-size: 1.5rem; color: #1a202c;">Kategorien verwalten</h1>
        <a href="posts.php" class="btn" style="background: #edf2f7; color: #4a5568; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">Zurück zu den Posts</a>
    </header>

    <?php if ($message): ?>
        <div style="background: #c6f6d5; color: #22543d; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; border-left: 5px solid #38a169;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 25px; margin-bottom: 30px; border-top: 4px solid #3182ce; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <h3 style="margin-top:0; font-size: 1rem; color: #718096; text-transform: uppercase; letter-spacing: 1px;">Neue Kategorie anlegen</h3>
        <form method="POST" style="display: flex; gap: 10px;">
            <input type="text" name="name" placeholder="z.B. Docker, Tutorials, Hardware..." required 
                   style="flex: 1; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1rem; outline-color: #3182ce;">
            <button type="submit" name="add_category" class="btn btn-primary" style="padding: 0 30px; font-weight: bold;">Hinzufügen</button>
        </form>
    </div>

    <div class="card" style="padding: 0; overflow: hidden; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 12px;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 18px; color: #718096; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 40%;">Name</th>
                    <th style="padding: 18px; color: #718096; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 40%;">Slug (URL)</th>
                    <th style="padding: 18px; color: #718096; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align: right;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr style="border-bottom: 1px solid #edf2f7; transition: background 0.2s;" onmouseover="this.style.background='#fcfcfd'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 18px;">
                        <form method="POST" id="form-<?= $cat['id'] ?>" style="display: flex; align-items: center; gap: 10px;">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>" 
                                   id="input-<?= $cat['id'] ?>" readonly
                                   style="border: none; background: transparent; font-weight: 700; color: #2d3748; font-size: 1.1rem; width: 100%; padding: 5px; border-radius: 4px;">
                    </td>
                    <td style="padding: 18px;">
                        <span style="color: #a0aec0; font-family: 'Fira Code', monospace; font-size: 0.9rem;">/<?= htmlspecialchars($cat['slug']) ?></span>
                    </td>
                    <td style="padding: 18px; text-align: right; white-space: nowrap;">
                        <button type="button" id="edit-btn-<?= $cat['id'] ?>" onclick="enableEdit(<?= $cat['id'] ?>)" 
                                style="background: none; border: none; color: #3182ce; font-weight: bold; cursor: pointer; margin-right: 15px;">Bearbeiten</button>
                        
                        <button type="submit" name="update_category" id="save-btn-<?= $cat['id'] ?>" style="display: none; background: #38a169; border: none; color: white; padding: 5px 12px; border-radius: 4px; font-weight: bold; cursor: pointer; margin-right: 15px;">Speichern</button>

                        <a href="?delete=<?= $cat['id'] ?>" 
                           onclick="return confirm('Kategorie wirklich löschen?')"
                           style="color: #e53e3e; text-decoration: none; font-size: 0.9rem; font-weight: bold;">Löschen</a>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function enableEdit(id) {
    const input = document.getElementById('input-' + id);
    const editBtn = document.getElementById('edit-btn-' + id);
    const saveBtn = document.getElementById('save-btn-' + id);

    // Input schreibbar machen
    input.readOnly = false;
    input.style.background = "#fff";
    input.style.border = "1px solid #3182ce";
    input.style.outline = "none";
    input.focus();

    // Buttons tauschen
    editBtn.style.display = "none";
    saveBtn.style.display = "inline-block";
}
</script>

<?php include 'footer.php'; ?>