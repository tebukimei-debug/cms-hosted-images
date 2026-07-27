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

$uploadDir = __DIR__ . '/uploads/';
$thumbDir = __DIR__ . '/uploads/thumbs/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0755, true);
}

$destination = $uploadDir . $filename;
$thumbDestination = $thumbDir . $filename;

$url = APP_URL . '/uploads/' . $filename;
$thumbUrl = APP_URL . '/uploads/thumbs/' . $filename;

try {
    $sourceImage = null;
    if ($extension === 'jpg' || $extension === 'jpeg') {
        $sourceImage = @imagecreatefromjpeg($file['tmp_name']);
    } elseif ($extension === 'png') {
        $sourceImage = @imagecreatefrompng($file['tmp_name']);
    } elseif ($extension === 'webp') {
        $sourceImage = @imagecreatefromwebp($file['tmp_name']);
    } elseif ($extension === 'gif') {
        $sourceImage = @imagecreatefromgif($file['tmp_name']);
    }

    if (!$sourceImage) {
        // Fallback: move file if GD fails or not supported image
        move_uploaded_file($file['tmp_name'], $destination);
        $thumbUrl = $url; // no thumb
    } else {
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);

        // Guardar original (sin EXIF)
        if ($extension === 'jpg' || $extension === 'jpeg') {
            imagejpeg($sourceImage, $destination, 90);
        } elseif ($extension === 'png') {
            imagepng($sourceImage, $destination, 8);
        } elseif ($extension === 'webp') {
            imagewebp($sourceImage, $destination, 90);
        } elseif ($extension === 'gif') {
            imagegif($sourceImage, $destination);
        }

        // Crear miniatura
        $maxThumbSize = 400;
        $ratio = min($maxThumbSize / $width, $maxThumbSize / $height);
        $newWidth = max(1, (int)($width * $ratio));
        $newHeight = max(1, (int)($height * $ratio));

        $thumbImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($extension === 'png' || $extension === 'webp') {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Guardar miniatura
        if ($extension === 'jpg' || $extension === 'jpeg') {
            imagejpeg($thumbImage, $thumbDestination, 80);
        } elseif ($extension === 'png') {
            imagepng($thumbImage, $thumbDestination, 8);
        } elseif ($extension === 'webp') {
            imagewebp($thumbImage, $thumbDestination, 80);
        } elseif ($extension === 'gif') {
            imagegif($thumbImage, $thumbDestination);
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO images (unique_id, filename, url, thumb_url, size, mime_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$uniqueId, $filename, $url, $thumbUrl, $file['size'], $file['type']]);

    echo json_encode([
        'success' => true,
        'url' => $url,
        'thumb_url' => $thumbUrl,
        'id' => $uniqueId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
