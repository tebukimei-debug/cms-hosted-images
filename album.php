<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$uniqueId = $_GET['id'] ?? '';
if (!$uniqueId) {
    header('Location: /');
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM albums WHERE unique_id = ?");
$stmt->execute([$uniqueId]);
$album = $stmt->fetch();

if (!$album) {
    die("Álbum no encontrado.");
}

$userId = getActiveUserId();
$isOwner = ($userId === $album['user_id']);
$authOk = $isOwner;

if (!$isOwner) {
    if ($album['privacy'] === 'private') {
        die("Este álbum es privado.");
    } elseif ($album['privacy'] === 'password') {
        session_start();
        $sessionKey = 'auth_album_' . $album['id'];
        if (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] === true) {
            $authOk = true;
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['album_password'])) {
                if (password_verify($_POST['album_password'], $album['password_hash'])) {
                    $_SESSION[$sessionKey] = true;
                    $authOk = true;
                } else {
                    $error = "Contraseña incorrecta.";
                }
            }
        }
    } else {
        $authOk = true; // Public
    }
}

if ($authOk) {
    $stmt = $pdo->prepare("SELECT * FROM images WHERE album_id = ? ORDER BY created_at DESC");
    $stmt->execute([$album['id']]);
    $images = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($album['name']) ?> - Chevereto PHP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: white; }
    </style>
</head>
<body class="min-h-screen p-8">
    <div class="max-w-4xl mx-auto flex justify-between mb-8">
        <a href="<?= $isOwner ? '/albums.php' : '/' ?>" class="text-blue-400 hover:underline self-center">&larr; Volver</a>
    </div>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-4xl font-bold mb-2"><?= htmlspecialchars($album['name']) ?></h1>
        <p class="text-gray-400 mb-8">
            Privacidad: 
            <strong class="text-white uppercase text-xs tracking-wider">
                <?= $album['privacy'] ?>
            </strong>
        </p>

        <?php if (!$authOk): ?>
            <div class="bg-slate-800 p-8 rounded-xl max-w-md mx-auto text-center border border-slate-700">
                <h2 class="text-xl font-semibold mb-4">Este álbum está protegido</h2>
                <?php if (isset($error)): ?>
                    <div class="bg-red-500/20 text-red-300 p-2 rounded mb-4"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="password" name="album_password" placeholder="Ingresa la contraseña" class="w-full bg-slate-700 border border-slate-600 rounded px-4 py-2 text-white text-center mb-4" required>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition">Desbloquear</button>
                </form>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($images as $img): ?>
                    <a href="<?= htmlspecialchars($img['url']) ?>" target="_blank" class="bg-gray-800 rounded-lg overflow-hidden border border-gray-700 block hover:border-blue-500 transition">
                        <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" alt="Img" class="w-full h-48 object-cover">
                    </a>
                <?php endforeach; ?>
                <?php if (empty($images)): ?>
                    <p class="text-gray-400 col-span-4">Este álbum está vacío.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
