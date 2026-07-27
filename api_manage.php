<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$id = $data['id'] ?? '';

if (!$action || !$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action or id']);
    exit;
}

$pdo = getDbConnection();
$userId = getActiveUserId();
$isAdmin = isAdmin();

try {
    if ($action === 'delete_image') {
        // Verificar propiedad o admin
        $stmt = $pdo->prepare("SELECT filename, user_id FROM images WHERE unique_id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            throw new Exception("Imagen no encontrada");
        }

        if (!$isAdmin && $image['user_id'] != $userId) {
            throw new Exception("No tienes permiso para borrar esta imagen");
        }

        // Borrar archivos
        $uploadDir = __DIR__ . '/uploads/';
        $thumbDir = __DIR__ . '/uploads/thumbs/';
        @unlink($uploadDir . $image['filename']);
        @unlink($thumbDir . $image['filename']);

        // Borrar de DB
        $stmt = $pdo->prepare("DELETE FROM images WHERE unique_id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'rename_image') {
        $newName = trim($data['new_name'] ?? '');
        if (!$newName) throw new Exception("Nombre inválido");

        $stmt = $pdo->prepare("SELECT filename, user_id FROM images WHERE unique_id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) throw new Exception("Imagen no encontrada");
        if (!$isAdmin && $image['user_id'] != $userId) throw new Exception("No tienes permiso");

        $stmt = $pdo->prepare("UPDATE images SET title = ? WHERE unique_id = ?");
        $stmt->execute([$newName, $id]);
        
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete_album') {
        $stmt = $pdo->prepare("SELECT id, user_id FROM albums WHERE id = ?");
        $stmt->execute([$id]);
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album) {
            throw new Exception("Álbum no encontrado");
        }

        if (!$isAdmin && $album['user_id'] != $userId) {
            throw new Exception("No tienes permiso para borrar este álbum");
        }

        // Al borrar el álbum, las imágenes no se borran (ON DELETE SET NULL)
        $stmt = $pdo->prepare("DELETE FROM albums WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'rename_album') {
        $newName = trim($data['new_name'] ?? '');
        if (!$newName) throw new Exception("Nombre inválido");

        $stmt = $pdo->prepare("SELECT id, user_id FROM albums WHERE id = ?");
        $stmt->execute([$id]);
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album) throw new Exception("Álbum no encontrado");
        if (!$isAdmin && $album['user_id'] != $userId) throw new Exception("No tienes permiso");

        $stmt = $pdo->prepare("UPDATE albums SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $id]);

        echo json_encode(['success' => true]);

    } else {
        throw new Exception("Acción desconocida");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
