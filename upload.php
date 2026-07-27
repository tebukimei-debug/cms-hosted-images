<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Prevenir que Warnings/Notices rompan el JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'No files uploaded']);
    exit;
}

$pdo = getDbConnection();
$userId = getActiveUserId();
$albumId = $_POST['album_id'] ?? null;

if ($albumId && $userId) {
    $stmtCheck = $pdo->prepare("SELECT id FROM albums WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$albumId, $userId]);
    if (!$stmtCheck->fetch()) {
        $albumId = null;
    }
} else {
    $albumId = null;
}

$uploadDir = __DIR__ . '/uploads/';
$thumbDir = __DIR__ . '/uploads/thumbs/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

$files = $_FILES['file'];
$isMultiple = is_array($files['name']);
$count = $isMultiple ? count($files['name']) : 1;

$results = [];

for ($i = 0; $i < $count; $i++) {
    $tmpName = $isMultiple ? $files['tmp_name'][$i] : $files['tmp_name'];
    $name = $isMultiple ? $files['name'][$i] : $files['name'];
    $size = $isMultiple ? $files['size'][$i] : $files['size'];
    $type = $isMultiple ? $files['type'][$i] : $files['type'];
    $error = $isMultiple ? $files['error'][$i] : $files['error'];

    if ($error !== UPLOAD_ERR_OK) {
        $results[] = ['name' => $name, 'error' => "Upload error code: $error"];
        continue;
    }

    $uniqueId = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $filename = $uniqueId . '.' . $extension;
    $destination = $uploadDir . $filename;
    $thumbDestination = $thumbDir . $filename;
    $url = APP_URL . '/uploads/' . $filename;
    $thumbUrl = APP_URL . '/uploads/thumbs/' . $filename;

    try {
        $sourceImage = null;
        if ($extension === 'jpg' || $extension === 'jpeg') {
            $sourceImage = @imagecreatefromjpeg($tmpName);
        } elseif ($extension === 'png') {
            $sourceImage = @imagecreatefrompng($tmpName);
        } elseif ($extension === 'webp') {
            $sourceImage = @imagecreatefromwebp($tmpName);
        } elseif ($extension === 'gif') {
            $sourceImage = @imagecreatefromgif($tmpName);
        }

        if (!$sourceImage) {
            move_uploaded_file($tmpName, $destination);
            $thumbUrl = $url;
        } else {
            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);

            if ($extension === 'jpg' || $extension === 'jpeg') imagejpeg($sourceImage, $destination, 90);
            elseif ($extension === 'png') imagepng($sourceImage, $destination, 8);
            elseif ($extension === 'webp') imagewebp($sourceImage, $destination, 90);
            elseif ($extension === 'gif') imagegif($sourceImage, $destination);

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

            if ($extension === 'jpg' || $extension === 'jpeg') imagejpeg($thumbImage, $thumbDestination, 80);
            elseif ($extension === 'png') imagepng($thumbImage, $thumbDestination, 8);
            elseif ($extension === 'webp') imagewebp($thumbImage, $thumbDestination, 80);
            elseif ($extension === 'gif') imagegif($thumbImage, $thumbDestination);

            imagedestroy($sourceImage);
            imagedestroy($thumbImage);
        }

        $stmt = $pdo->prepare("INSERT INTO images (unique_id, filename, url, thumb_url, size, mime_type, user_id, album_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$uniqueId, $filename, $url, $thumbUrl, $size, $type, $userId, $albumId]);

        $results[] = [
            'success' => true,
            'name' => $name,
            'url' => $url,
            'thumb_url' => $thumbUrl,
            'id' => $uniqueId
        ];
    } catch (Exception $e) {
        $results[] = ['name' => $name, 'error' => $e->getMessage()];
    }
}

$debug = ob_get_clean();
echo json_encode([
    'success' => true,
    'results' => $results,
    'debug' => $debug
]);
