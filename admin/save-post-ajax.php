<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin'])) { http_response_code(403); exit; }

require_once __DIR__ . '/../src/App/Database.php';
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];
$pdo = (new App\Database($ini['database'] ?? []))->pdo();

$currentLang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . '/../config/lang/' . $currentLang . '.ini';
$iniLang = file_exists($langFile) ? parse_ini_file($langFile, true) : [];
$t = $iniLang['common'] ?? [];

$currentUser = $_SESSION['admin'];
$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($data['id'])) {
    try {
        $id = (int)$data['id'];

        $stmt = $pdo->prepare("SELECT author_id FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if (!$post) {
            echo json_encode(['status' => 'error', 'error' => $t['error_post_not_found'] ?? 'Post not found']);
            exit;
        }

        $isAdmin = ($currentUser['role'] ?? 'viewer') === 'admin';
        $isOwner = (int)($post['author_id'] ?? 0) === (int)$currentUser['id'];

        if (!$isAdmin && !$isOwner) {
            echo json_encode(['status' => 'error', 'error' => $t['error_permission_denied'] ?? 'Permission denied']);
            exit;
        }

        $slug = trim($data['slug'] ?? '');
        if ($slug === '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
        }

        $sql = "UPDATE posts SET 
                title = ?, 
                slug = ?,
                excerpt = ?, 
                content = ?, 
                hero_image = ?, 
                download_file = ?, 
                category_id = ?, 
                status = ?, 
                is_sticky = ?,
                updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $slug,
            $data['excerpt'] ?? '',
            $data['content'],
            $data['hero_image'] ?: null,
            $data['download_file'] ?: null,
            $data['category_id'] ?: null,
            $data['status'],
            (int)$data['is_sticky'],
            $id
        ]);

        $tagsInput = trim($data['tags'] ?? '');
        $pdo->prepare("DELETE FROM post_tags WHERE post_id = ?")->execute([$id]);

        if ($tagsInput !== '') {
            $tagsArray = array_map('trim', explode(',', $tagsInput));
            $tagsArray = array_unique(array_filter($tagsArray)); 

            foreach ($tagsArray as $tagName) {
                $slugTag = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $tagName)));
                if ($slugTag === '') continue;

                $stmt = $pdo->prepare("SELECT id FROM tags WHERE slug = ?");
                $stmt->execute([$slugTag]);
                $tagId = $stmt->fetchColumn();

                if (!$tagId) {
                    $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)")->execute([$tagName, $slugTag]);
                    $tagId = $pdo->lastInsertId();
                }

                try {
                    $pdo->prepare("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)")->execute([$id, $tagId]);
                } catch (Exception $e) { }
            }
        }

        try {
            $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'update', ?, ?)");
            $logStmt->execute([
                $currentUser['id'],
                "Updated post metadata ID {$id} via AJAX",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) { }

        echo json_encode(['status' => 'ok']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
}
?>