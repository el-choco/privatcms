<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
require_once __DIR__ . '/../src/App/I18n.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

// --- AKTIONEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
    } elseif ($_POST['action'] === 'publish') {
        $pdo->prepare("UPDATE posts SET status = 'published' WHERE id = ?")->execute([$id]);
    } elseif ($_POST['action'] === 'unpublish') {
        $pdo->prepare("UPDATE posts SET status = 'draft' WHERE id = ?")->execute([$id]);
    }
    header("Location: posts.php"); exit;
}

// GEÄNDERT: SQL mit JOIN, um Kategorienamen zu holen
$sql = "SELECT p.*, c.name as category_name 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC";
$posts = $pdo->query($sql)->fetchAll();

include 'header.php'; 
?>

<header class="top-header">
    <h1>📝 Beiträge verwalten</h1>
    <a href="post-create.php" class="btn btn-primary">+ Neuer Beitrag</a>
</header>

<div class="content-area">
    <div class="card" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">Titel & Kategorie</th>
                    <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">Status</th>
                    <th style="padding: 15px; text-align: left; color: #718096; font-size: 12px; text-transform: uppercase;">Datum</th>
                    <th style="padding: 15px; text-align: right; color: #718096; font-size: 12px; text-transform: uppercase;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $p): ?>
                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 15px;">
                        <div style="font-weight: bold; font-size: 1.05rem; color: #2d3748; margin-bottom: 4px;">
                            <?= htmlspecialchars($p['title']) ?>
                        </div>
                        <?php if ($p['category_name']): ?>
                            <span style="display: inline-block; font-size: 10px; background: #ebf8ff; color: #2b6cb0; padding: 2px 8px; border-radius: 4px; border: 1px solid #bee3f8; text-transform: uppercase; font-weight: 700;">
                                📁 <?= htmlspecialchars($p['category_name']) ?>
                            </span>
                        <?php else: ?>
                            <span style="display: inline-block; font-size: 10px; background: #f7fafc; color: #a0aec0; padding: 2px 8px; border-radius: 4px; border: 1px solid #edf2f7; text-transform: uppercase; font-weight: 400;">
                                Keine Kategorie
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 15px;">
                        <span style="padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; 
                              background: <?= $p['status']==='published'?'#c6f6d5':'#feebc8' ?>; 
                              color: <?= $p['status']==='published'?'#22543d':'#744210' ?>;">
                            <?= $p['status'] === 'published' ? 'Veröffentlicht' : 'Entwurf' ?>
                        </span>
                    </td>
                    <td style="padding: 15px; color: #718096; font-size: 14px;">
                        <?= date('d.m.Y', strtotime($p['created_at'])) ?>
                    </td>
                    <td style="padding: 15px; text-align: right;">
                        <div style="display: flex; justify-content: flex-end; gap: 8px;">
                            <a href="post-edit.php?id=<?= $p['id'] ?>" class="btn" title="Bearbeiten" 
                               style="background: #fff; border: 1px solid #e2e8f0; font-size: 1.2rem; padding: 5px 10px; border-radius: 6px;">✏️</a>
                            
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="action" value="<?= $p['status']==='published'?'unpublish':'publish' ?>">
                                <button type="submit" class="btn" style="background: #fff; border: 1px solid #e2e8f0; font-size: 1.2rem; padding: 5px 10px; border-radius: 6px;" 
                                        title="<?= $p['status']==='published'?'In Entwurf verschieben':'Veröffentlichen' ?>">
                                    <?= $p['status'] === 'published' ? '📦' : '🚀' ?>
                                </button>
                            </form>

                            <form method="post" onsubmit="return confirm('Wirklich löschen?')" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn" style="background: #fff; border: 1px solid #fed7d7; color: #e53e3e; font-size: 1.2rem; padding: 5px 10px; border-radius: 6px;" title="Löschen">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>