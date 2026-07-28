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
    
    // El thumbnail siempre será .webp
    $thumbFilename = $uniqueId . '.webp';
    $thumbDestination = $thumbDir . $thumbFilename;
    
    $url = '/uploads/' . $filename;
    $thumbUrl = '/uploads/thumbs/' . $thumbFilename;

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

            // Determinar dimensiones objetivo según orientación
            if ($width > $height) {
                // Horizontal (Landscape)
                $thumbW = 200;
                $thumbH = 150;
            } else {
                // Vertical (Portrait) o Cuadrado
                $thumbW = 150;
                $thumbH = 200;
            }

            // Cálculos para recorte (Crop) centrado
            $targetRatio = $thumbW / $thumbH;
            $sourceRatio = $width / $height;

            if ($sourceRatio > $targetRatio) {
                // Imagen original es más ancha, recortar lados
                $cropH = $height;
                $cropW = (int)($height * $targetRatio);
                $cropX = (int)(($width - $cropW) / 2);
                $cropY = 0;
            } else {
                // Imagen original es más alta, recortar arriba/abajo
                $cropW = $width;
                $cropH = (int)($width / $targetRatio);
                $cropX = 0;
                $cropY = (int)(($height - $cropH) / 2);
            }

            $thumbImage = imagecreatetruecolor($thumbW, $thumbH);
            
            // Siempre habilitar transparencia porque se guarda como .webp
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbImage, 0, 0, $thumbW, $thumbH, $transparent);

            // Copiar y redimensionar con recorte (crop)
            imagecopyresampled($thumbImage, $sourceImage, 0, 0, $cropX, $cropY, $thumbW, $thumbH, $cropW, $cropH);

            // Guardar siempre como webp con calidad 80
            imagewebp($thumbImage, $thumbDestination, 80);

            imagedestroy($sourceImage);
            imagedestroy($thumbImage);
        }

        if (!file_exists($destination)) {
            throw new Exception("Error al guardar la imagen en el disco. Verifica permisos o espacio.");
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
