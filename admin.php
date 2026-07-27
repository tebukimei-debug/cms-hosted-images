<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin();

if (!isAdmin()) {
    header('Location: /');
    exit;
}

$pdo = getDbConnection();

// Fetch all images
$stmt = $pdo->query("SELECT i.*, u.username as owner_name FROM images i LEFT JOIN users u ON i.user_id = u.id ORDER BY i.created_at DESC");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all albums
$stmt = $pdo->query("SELECT a.*, u.username as owner_name FROM albums a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC");
$albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #111827; color: #f3f4f6; }
    </style>
</head>
<body class="p-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-red-500">Panel de Administración</h1>
            <a href="/" class="text-gray-400 hover:text-white">Volver al Inicio</a>
        </div>

        <h2 class="text-2xl font-semibold mb-4 text-blue-400">Todos los Álbumes</h2>
        <div class="bg-gray-800 rounded-lg p-4 mb-8">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="pb-2">ID</th>
                        <th class="pb-2">Nombre</th>
                        <th class="pb-2">Propietario</th>
                        <th class="pb-2">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($albums as $album): ?>
                        <tr class="border-b border-gray-700/50">
                            <td class="py-2"><?= $album['id'] ?></td>
                            <td class="py-2"><?= htmlspecialchars($album['name']) ?></td>
                            <td class="py-2"><?= htmlspecialchars($album['owner_name'] ?? 'Anónimo') ?></td>
                            <td class="py-2">
                                <button onclick="renameAlbum(<?= $album['id'] ?>, '<?= htmlspecialchars(addslashes($album['name'])) ?>')" class="text-yellow-400 hover:text-yellow-300 mr-2">Renombrar</button>
                                <button onclick="deleteAlbum(<?= $album['id'] ?>)" class="text-red-400 hover:text-red-300">Borrar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="text-2xl font-semibold mb-4 text-green-400">Todas las Imágenes</h2>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <?php foreach ($images as $img): ?>
                <div class="bg-gray-800 rounded-lg overflow-hidden border border-gray-700 relative group">
                    <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" class="w-full h-32 object-cover">
                    <div class="p-2 text-xs text-gray-400 truncate">
                        Por: <?= htmlspecialchars($img['owner_name'] ?? 'Anónimo') ?>
                    </div>
                    <div class="absolute top-2 right-2 flex space-x-1 opacity-0 group-hover:opacity-100 transition">
                        <button onclick="deleteImage('<?= $img['unique_id'] ?>')" class="bg-red-600 text-white p-1 rounded hover:bg-red-500" title="Eliminar">🗑️</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        async function renameAlbum(id, oldName) {
            const newName = prompt("Nuevo nombre del álbum:", oldName);
            if (!newName || newName === oldName) return;

            const res = await fetch('/api_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'rename_album', id: id, new_name: newName })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error);
        }

        async function deleteAlbum(id) {
            if (!confirm("¿Seguro que quieres borrar este álbum?")) return;
            const res = await fetch('/api_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'delete_album', id: id })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error);
        }

        async function deleteImage(id) {
            if (!confirm("¿Seguro que quieres borrar esta imagen?")) return;
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
