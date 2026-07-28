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

    } elseif ($action === 'bulk_delete_images') {
        $ids = $data['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) throw new Exception("No se proporcionaron imágenes");

        $uploadDir = __DIR__ . '/uploads/';
        $thumbDir = __DIR__ . '/uploads/thumbs/';

        $deletedCount = 0;
        foreach ($ids as $imgId) {
            $stmt = $pdo->prepare("SELECT filename, user_id FROM images WHERE unique_id = ?");
            $stmt->execute([$imgId]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image && ($isAdmin || $image['user_id'] == $userId)) {
                @unlink($uploadDir . $image['filename']);
                @unlink($thumbDir . preg_replace('/\.[a-zA-Z0-9]+$/', '.webp', $image['filename'])); // Thumb is now webp, or attempt both
                @unlink($thumbDir . $image['filename']); // Legacy thumb
                
                $delStmt = $pdo->prepare("DELETE FROM images WHERE unique_id = ?");
                $delStmt->execute([$imgId]);
                $deletedCount++;
            }
        }

        echo json_encode(['success' => true, 'deleted' => $deletedCount]);

    } elseif ($action === 'bulk_move_images') {
        $ids = $data['ids'] ?? [];
        $newAlbumId = $data['new_album_id'] ?? null;
        if (!is_array($ids) || empty($ids)) throw new Exception("No se proporcionaron imágenes");

        // Verify album ownership if not moving to root
        if ($newAlbumId !== null && $newAlbumId !== '') {
            $stmt = $pdo->prepare("SELECT user_id FROM albums WHERE id = ?");
            $stmt->execute([$newAlbumId]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$album || (!$isAdmin && $album['user_id'] != $userId)) {
                throw new Exception("Álbum destino inválido o sin permisos");
            }
        } else {
            $newAlbumId = null;
        }

        $movedCount = 0;
        foreach ($ids as $imgId) {
            $stmt = $pdo->prepare("SELECT user_id FROM images WHERE unique_id = ?");
            $stmt->execute([$imgId]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image && ($isAdmin || $image['user_id'] == $userId)) {
                $updStmt = $pdo->prepare("UPDATE images SET album_id = ? WHERE unique_id = ?");
                $updStmt->execute([$newAlbumId, $imgId]);
                $movedCount++;
            }
        }

        echo json_encode(['success' => true, 'moved' => $movedCount]);

    } else {
        throw new Exception("Acción desconocida");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
