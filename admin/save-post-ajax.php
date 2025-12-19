<?php
declare(strict_types=1);
session_start();

// Sicherheit: Nur für eingeloggte Admins
if (empty($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Nicht autorisiert']);
    exit;
}

require_once __DIR__ . '/../src/App/Database.php';

// Konfiguration laden
$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true, INI_SCANNER_TYPED) ?: [];

try {
    $pdo = (new App\Database($ini['database'] ?? []))->pdo();
    
    // JSON-Daten vom JavaScript-Fetch empfangen
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if ($data && isset($data['id'])) {
        $id          = (int)$data['id'];
        $title       = $data['title'] ?? '';
        $excerpt     = $data['excerpt'] ?? '';
        $content     = $data['content'] ?? '';
        $hero_image  = $data['hero_image'] ?? ''; // NEU: Empfange den Bildnamen
        $category_id = !empty($data['category_id']) ? (int)$data['category_id'] : null;

        // SQL Update inklusive excerpt, category_id UND hero_image
        $sql = "UPDATE posts 
                SET title = ?, 
                    excerpt = ?, 
                    content = ?, 
                    category_id = ?, 
                    hero_image = ?, 
                    updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $title, 
            $excerpt, 
            $content, 
            $category_id, 
            $hero_image, 
            $id
        ]);

        header('Content-Type: application/json');
        if ($success) {
            echo json_encode(['status' => 'ok', 'message' => 'Erfolgreich gespeichert']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Datenbank-Update fehlgeschlagen']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Keine gültigen Daten empfangen']);
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}