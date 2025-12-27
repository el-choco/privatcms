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
$pcLang = $t['post_create'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? ($pcLang['default_title'] ?? 'New Post');
    
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $slug);
    $slug = trim(preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');

    $userId = (int)($_SESSION['admin']['id'] ?? 1); 

    $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, status, user_id, author_id, created_at) VALUES (?, ?, '', 'draft', ?, ?, NOW())");
    $stmt->execute([$title, $slug, $userId, $userId]);
    
    $newId = $pdo->lastInsertId();

    try {
        $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'create', ?, ?)");
        $log->execute([$userId, "Created post ID $newId", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {}
    
    header("Location: /admin/post-edit.php?id=" . $newId);
    exit;
}

require_once 'header.php';
?>

<div class="content-area">
    <div style="display: flex; justify-content: center; padding-top: 40px;">
        <div class="card" style="width: 100%; max-width: 600px; padding: 40px; border-top: 4px solid #3182ce; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
            
            <header style="margin-bottom: 30px; text-align: center;">
                <h1 style="margin:0; font-size: 1.8rem; color: #1a202c;">
                    <?= htmlspecialchars($pcLang['title'] ?? 'Create Post') ?>
                </h1>
            </header>

            <form method="post">
                <div style="margin-bottom: 25px;">
                    <label style="display:block; margin-bottom: 10px; font-weight: bold; color: #4a5568; font-size: 1.1rem;">
                        <?= htmlspecialchars($pcLang['label_title'] ?? 'Title') ?>
                    </label>
                    <input type="text" name="title" required 
                           placeholder="<?= htmlspecialchars($pcLang['placeholder_title'] ?? 'Enter title here') ?>" 
                           style="width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1.1rem; outline: none; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#3182ce'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
                
                <div style="display: flex; gap: 15px; flex-direction: column;">
                    <button type="submit" class="btn btn-primary" style="padding: 15px; font-size: 1.1rem; justify-content: center;">
                        <?= htmlspecialchars($pcLang['btn_create'] ?? 'Create') ?>
                    </button>
                    <a href="posts.php" class="btn" style="text-align: center; background: #edf2f7; color: #4a5568; padding: 15px; font-size: 1rem;">
                        <?= htmlspecialchars($pcLang['btn_cancel'] ?? 'Cancel') ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>