<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { header('Location: /admin/login.php'); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? 'Neuer Beitrag';
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    $userId = $_SESSION['admin']['id'] ?? 1; 

    $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, status, user_id, created_at) VALUES (?, ?, '', 'draft', ?, NOW())");
    $stmt->execute([$title, $slug, $userId]);
    
    header("Location: /admin/post-edit.php?id=" . $pdo->lastInsertId());
    exit;
}

include 'header.php';
?>

<header class="top-header">
    <h1>Neuen Beitrag erstellen</h1>
</header>

<div class="content-area">
    <div class="card" style="max-width: 600px; padding: 30px;">
        <form method="post">
            <label style="display:block; margin-bottom: 8px; font-weight: bold; color: #4a5568;">Titel des Beitrags</label>
            <input type="text" name="title" required placeholder="z.B. Mein erster Urlaub" 
                   style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 20px; font-size: 1rem;">
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Erstellen & Editor öffnen</button>
                <a href="posts.php" class="btn">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>