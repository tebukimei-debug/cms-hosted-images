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
                    <div class="bg-gray-800 rounded-lg overflow-hidden border border-gray-700 relative group">
                        <a href="<?= htmlspecialchars($img['url']) ?>" target="_blank" class="block">
                            <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" alt="Img" class="w-full h-48 object-cover">
                        </a>
                        
                        <?php if ($img['title']): ?>
                            <div class="absolute bottom-0 w-full bg-black/70 p-2 text-xs truncate">
                                <?= htmlspecialchars($img['title']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isAdmin() || (isLoggedIn() && $img['user_id'] == getActiveUserId())): ?>
                            <div class="absolute top-2 right-2 flex space-x-1 opacity-0 group-hover:opacity-100 transition bg-black/50 p-1 rounded">
                                <button onclick="renameImage('<?= $img['unique_id'] ?>', '<?= htmlspecialchars(addslashes($img['title'] ?? '')) ?>')" class="text-yellow-400 hover:text-yellow-300" title="Renombrar">✏️</button>
                                <button onclick="deleteImage('<?= $img['unique_id'] ?>')" class="text-red-400 hover:text-red-300" title="Eliminar">🗑️</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($images)): ?>
                    <p class="text-gray-400 col-span-4">Este álbum está vacío.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        async function renameImage(id, currentTitle) {
            const newTitle = prompt("Nuevo título para la imagen:", currentTitle);
            if (newTitle === null || newTitle === currentTitle) return;
            const res = await fetch('/api_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'rename_image', id: id, new_name: newTitle })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error);
        }

        async function deleteImage(id) {
            if (!confirm("¿Seguro que quieres borrar esta imagen permanentemente?")) return;
            const res = await fetch('/api_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'delete_image', id: id })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error);
        }
    </script>
</body>
</html>
