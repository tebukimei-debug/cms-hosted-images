<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin();

$pdo = getDbConnection();
$userId = getActiveUserId();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_album') {
    $name = trim($_POST['name'] ?? '');
    $privacy = $_POST['privacy'] ?? 'public';
    $password = $_POST['password'] ?? '';

    if (empty($name)) {
        $error = "El nombre del álbum es obligatorio.";
    } else {
        $uniqueId = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
        $passwordHash = ($privacy === 'password' && !empty($password)) ? password_hash($password, PASSWORD_DEFAULT) : null;
        
        $stmt = $pdo->prepare("INSERT INTO albums (user_id, unique_id, name, privacy, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $uniqueId, $name, $privacy, $passwordHash]);
        $success = "Álbum creado exitosamente.";
    }
}

$stmt = $pdo->prepare("
    SELECT a.*, (SELECT COUNT(*) FROM images WHERE album_id = a.id) as image_count 
    FROM albums a 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$albums = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Álbumes - Chevereto PHP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: white; }
    </style>
</head>
<body class="min-h-screen p-8">
    
    <!-- Navbar -->
    <div class="max-w-4xl mx-auto flex justify-between mb-8">
        <a href="/" class="text-blue-400 hover:underline self-center">&larr; Volver a Inicio</a>
        <div>
            <span class="mr-4 text-gray-300 self-center">Hola, <b class="text-white"><?= htmlspecialchars(getActiveUsername()) ?></b></span>
            <a href="/logout.php" class="bg-red-500/20 text-red-400 border border-red-500 px-4 py-2 rounded hover:bg-red-500 hover:text-white transition">Cerrar Sesión</a>
        </div>
    </div>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">Mis Álbumes</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-300 p-3 rounded mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Crear Álbum -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 mb-10">
            <h2 class="text-xl font-semibold mb-4">Crear Nuevo Álbum</h2>
            <form method="POST" action="" class="flex flex-col md:flex-row gap-4 items-end">
                <input type="hidden" name="action" value="create_album">
                <div class="flex-1">
                    <label class="block text-gray-400 text-sm mb-1">Nombre</label>
                    <input type="text" name="name" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white" required>
                </div>
                <div>
                    <label class="block text-gray-400 text-sm mb-1">Privacidad</label>
                    <select name="privacy" id="privacySelect" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white">
                        <option value="public">Público</option>
                        <option value="private">Privado (Solo tú)</option>
                        <option value="password">Con Contraseña</option>
                    </select>
                </div>
                <div id="passwordField" class="hidden">
                    <label class="block text-gray-400 text-sm mb-1">Contraseña</label>
                    <input type="password" name="password" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition h-[42px]">Crear</button>
            </form>
        </div>

        <!-- Lista de Álbumes -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($albums as $album): ?>
                <div class="bg-slate-800 rounded-xl p-5 border border-slate-700">
                    <h3 class="text-xl font-bold mb-2 truncate" title="<?= htmlspecialchars($album['name']) ?>"><?= htmlspecialchars($album['name']) ?></h3>
                    <p class="text-gray-400 text-sm mb-4">
                        <?= $album['image_count'] ?> imágenes &bull; 
                        <?php
                            if ($album['privacy'] === 'public') echo '<span class="text-green-400">Público</span>';
                            elseif ($album['privacy'] === 'private') echo '<span class="text-red-400">Privado</span>';
                            else echo '<span class="text-yellow-400">Contraseña</span>';
                        ?>
                    </p>
                    <a href="/album.php?id=<?= $album['unique_id'] ?>" class="text-blue-400 hover:underline block text-center border border-blue-400/30 rounded py-2 hover:bg-blue-400/10 transition">Ver Álbum</a>
                </div>
            <?php endforeach; ?>
            <?php if (empty($albums)): ?>
                <p class="text-gray-400 col-span-3">No tienes álbumes todavía.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const privacySelect = document.getElementById('privacySelect');
        const passwordField = document.getElementById('passwordField');
        privacySelect.addEventListener('change', (e) => {
            if (e.target.value === 'password') {
                passwordField.classList.remove('hidden');
                passwordField.querySelector('input').required = true;
            } else {
                passwordField.classList.add('hidden');
                passwordField.querySelector('input').required = false;
            }
        });
    </script>
</body>
</html>
