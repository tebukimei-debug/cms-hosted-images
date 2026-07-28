<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$id = $_GET['id'] ?? '';
if (!$id) {
    header('Location: /');
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM images WHERE unique_id = ?");
$stmt->execute([$id]);
$img = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$img) {
    die("Imagen no encontrada.");
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
$fullUrl = $domain . $img['url'];
$thumbUrl = $domain . ($img['thumb_url'] ?? $img['url']);

$name = $img['title'] ?: $img['filename'];

$forumCode = "[url={$fullUrl}][img]{$thumbUrl}[/img][/url]";
$htmlCode = "<a href=\"{$fullUrl}\"><img src=\"{$thumbUrl}\" alt=\"{$name}\" border=\"0\"></a>";

$isOwner = (isLoggedIn() && getActiveUserId() == $img['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viendo imagen - <?= htmlspecialchars($name) ?></title>
    
    <!-- OpenGraph / Embed Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($name) ?>" />
    <meta property="og:type" content="image" />
    <meta property="og:url" content="<?= htmlspecialchars($viewUrl) ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($fullUrl) ?>" />
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($name) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($fullUrl) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: white; }
    </style>
</head>
<body class="min-h-screen p-8 flex flex-col items-center">
    
    <div class="w-full max-w-6xl flex justify-between mb-8">
        <a href="javascript:history.back()" class="text-blue-400 hover:underline">&larr; Volver</a>
        <?php if ($isOwner || isAdmin()): ?>
            <div class="flex space-x-4">
                <button onclick="renameImage('<?= $img['unique_id'] ?>', '<?= htmlspecialchars(addslashes($img['title'] ?? '')) ?>')" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded transition">Renombrar</button>
                <button onclick="deleteImage('<?= $img['unique_id'] ?>')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition">Eliminar</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Imagen Grande -->
    <div class="mb-10 w-full max-w-6xl flex justify-center bg-gray-900 rounded-xl p-4 border border-gray-700">
        <img src="<?= htmlspecialchars($img['url']) ?>" alt="<?= htmlspecialchars($name) ?>" class="max-w-full max-h-[70vh] object-contain rounded">
    </div>

    <!-- Códigos -->
    <div class="w-full max-w-4xl bg-gray-800 p-8 rounded-xl border border-gray-700">
        <h2 class="text-2xl font-bold mb-6 text-center text-blue-400">Códigos para Compartir</h2>
        
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-300 mb-2 uppercase tracking-wider">Direct Link:</label>
                <input type="text" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 text-gray-100 font-mono text-sm focus:border-blue-500 focus:outline-none" value="<?= htmlspecialchars($fullUrl) ?>" readonly onclick="this.select()">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-300 mb-2 uppercase tracking-wider">Forum thumbnail (BBCode):</label>
                <input type="text" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 text-gray-100 font-mono text-sm focus:border-blue-500 focus:outline-none" value="<?= htmlspecialchars($forumCode) ?>" readonly onclick="this.select()">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-300 mb-2 uppercase tracking-wider">HTML thumbnail:</label>
                <input type="text" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 text-gray-100 font-mono text-sm focus:border-blue-500 focus:outline-none" value="<?= htmlspecialchars($htmlCode) ?>" readonly onclick="this.select()">
            </div>
        </div>
    </div>

    <?php if ($isOwner || isAdmin()): ?>
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
            if (data.success) {
                window.location.href = '/';
            } else {
                alert(data.error);
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>
