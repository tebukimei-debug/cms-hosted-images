<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$uniqueId = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = $uniqueId . '.' . $extension;

// Carpeta local
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destination = $uploadDir . $filename;
$url = APP_URL . '/uploads/' . $filename;

try {
    // Mover archivo localmente
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Error al guardar el archivo en el disco.");
    }

    // Save to Postgres
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO images (unique_id, filename, url, size, mime_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$uniqueId, $filename, $url, $file['size'], $file['type']]);

    echo json_encode([
        'success' => true,
        'url' => $url,
        'id' => $uniqueId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
