<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$uniqueId = $_GET['id'] ?? '';
if (!$uniqueId) {
    header('Location: /');
    exit;
}

$pdo = getDbConnection();

if ($uniqueId === 'general') {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
    $userId = getActiveUserId();
    $album = [
        'id' => null,
        'unique_id' => 'general',
        'name' => 'General (Sin Álbum)',
        'privacy' => 'public',
        'user_id' => $userId
    ];
    $isOwner = true;
    $authOk = true;
} else {
    $stmt = $pdo->prepare("SELECT * FROM albums WHERE unique_id = ?");
    $stmt->execute([$uniqueId]);
    $album = $stmt->fetch();

    if (!$album) {
        die("Álbum no encontrado.");
    }

    $userId = getActiveUserId();
    $isOwner = ($userId === $album['user_id']);
    $authOk = $isOwner;
}

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
    // 1. Lógica de ordenamiento (Sorting)
    $sort = $_GET['sort'] ?? 'newest';
    $orderBy = "created_at DESC";
    
    switch ($sort) {
        case 'oldest': $orderBy = "created_at ASC"; break;
        case 'name_asc': $orderBy = "COALESCE(NULLIF(title, ''), filename) ASC"; break;
        case 'name_desc': $orderBy = "COALESCE(NULLIF(title, ''), filename) DESC"; break;
    }

    if ($album['id'] === null) {
        $stmt = $pdo->prepare("SELECT * FROM images WHERE album_id IS NULL AND user_id = ? ORDER BY $orderBy");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM images WHERE album_id = ? ORDER BY $orderBy");
        $stmt->execute([$album['id']]);
    }
    $images = $stmt->fetchAll();

    // 2. Cargar lista de álbumes para mover (Solo si es dueño)
    $userAlbums = [];
    if ($isOwner) {
        if ($album['id'] === null) {
            $stmtAlbums = $pdo->prepare("SELECT id, name FROM albums WHERE user_id = ? ORDER BY name ASC");
            $stmtAlbums->execute([$userId]);
        } else {
            $stmtAlbums = $pdo->prepare("SELECT id, name FROM albums WHERE user_id = ? AND id != ? ORDER BY name ASC");
            $stmtAlbums->execute([$userId, $album['id']]);
        }
        $userAlbums = $stmtAlbums->fetchAll();
    }

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $domain = $protocol . '://' . $_SERVER['HTTP_HOST'];

    $albumForumCode = '';
    $albumHtmlCode = '';
    $albumGridCode = '<div style="display: grid;grid-template-columns: repeat(auto-fit, minmax(24%, 1fr));gap: 16px;align-items: start;padding: 16px">';

    foreach ($images as $img) {
        $thumbUrl = $domain . ($img['thumb_url'] ?? $img['url']);
        $viewUrl = $domain . '/view.php?id=' . $img['unique_id'];
        $name = htmlspecialchars($img['title'] ?: $img['filename'], ENT_QUOTES);

        // Sin saltos de línea para evitar problemas, src=thumb, href=viewer
        $albumForumCode .= "[url={$viewUrl}][img]{$thumbUrl}[/img][/url]";
        $albumHtmlCode .= "<a href=\"{$viewUrl}\"><img src=\"{$thumbUrl}\" alt=\"{$name}\" border=\"0\"></a>";
        $albumGridCode .= "<a href=\"{$viewUrl}\"><img src=\"{$thumbUrl}\" alt=\"{$name}\" border=\"0\"></a>";
    }
    
    if (count($images) > 0) {
        $albumGridCode .= '</div>';
    } else {
        $albumGridCode = '';
    }
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
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
            <div>
                <h1 class="text-4xl font-bold mb-2"><?= htmlspecialchars($album['name']) ?></h1>
                <p class="text-gray-400">
                    Privacidad: 
                    <strong class="text-white uppercase text-xs tracking-wider">
                        <?= $album['privacy'] ?>
                    </strong>
                </p>
            </div>
            <?php if ($authOk && count($images) > 0): ?>
                <button onclick="document.getElementById('albumCodes').classList.toggle('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition shadow-lg flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z" />
                    </svg>
                    Compartir Álbum
                </button>
            <?php endif; ?>
        </div>

        <?php if ($authOk && count($images) > 0): ?>
            <!-- Panel de Códigos del Álbum -->
            <div id="albumCodes" class="hidden mb-12 bg-slate-800 border border-slate-700 p-6 rounded-xl">
                <h2 class="text-xl font-bold mb-4 text-indigo-400">Códigos para compartir todas las imágenes</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-gray-300 font-semibold mb-2 text-sm">Forum links (BBCode)</label>
                        <textarea class="w-full h-32 bg-slate-900 border border-slate-700 text-gray-300 p-2 rounded text-xs font-mono focus:outline-none focus:border-indigo-500" readonly onclick="this.select()"><?= htmlspecialchars(trim($albumForumCode)) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-300 font-semibold mb-2 text-sm">HTML links</label>
                        <textarea class="w-full h-32 bg-slate-900 border border-slate-700 text-gray-300 p-2 rounded text-xs font-mono focus:outline-none focus:border-indigo-500" readonly onclick="this.select()"><?= htmlspecialchars(trim($albumHtmlCode)) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-300 font-semibold mb-2 text-sm">HTML code grid (4 Columnas)</label>
                        <textarea class="w-full h-32 bg-slate-900 border border-slate-700 text-gray-300 p-2 rounded text-xs font-mono focus:outline-none focus:border-indigo-500" readonly onclick="this.select()"><?= htmlspecialchars(trim($albumGridCode)) ?></textarea>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
            <!-- Controles de Álbum: Ordenamiento y Selección Masiva -->
            <div class="flex flex-col sm:flex-row justify-between items-center mb-4 bg-slate-800 p-4 rounded-lg border border-slate-700 gap-4">
                <div class="flex items-center gap-4">
                    <div class="text-sm">
                        <label class="text-gray-400 mr-2">Ordenar:</label>
                        <select onchange="window.location.href='?id=<?= $album['unique_id'] ?>&sort='+this.value" class="bg-slate-900 border border-slate-600 rounded px-3 py-1 text-white text-sm outline-none focus:border-indigo-500">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Más recientes</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Más antiguas</option>
                            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Nombre (A-Z)</option>
                            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Nombre (Z-A)</option>
                        </select>
                    </div>
                </div>
                
                <?php if ($isOwner && count($images) > 0): ?>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                        <input type="checkbox" id="selectAllCb" class="form-checkbox h-4 w-4 text-indigo-600 bg-slate-900 border-slate-600 rounded" onchange="toggleAll(this)">
                        Seleccionar Todo
                    </label>
                    <div id="bulkActionsBar" class="hidden flex items-center gap-2 border-l border-slate-600 pl-4">
                        <span id="selectedCountTxt" class="text-xs text-indigo-400 font-bold mr-2">0 seleccionadas</span>
                        
                        <div class="relative">
                            <select id="bulkMoveSelect" class="bg-slate-900 border border-slate-600 rounded px-2 py-1 text-white text-xs outline-none">
                                <option value="" disabled selected>Mover a...</option>
                                <option value="0">(Sin Álbum / General)</option>
                                <?php foreach ($userAlbums as $ua): ?>
                                    <option value="<?= $ua['id'] ?>"><?= htmlspecialchars($ua['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button onclick="bulkMove()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-1 px-2 rounded ml-1 transition">Mover</button>
                        </div>

                        <button onclick="bulkDelete()" class="bg-red-600 hover:bg-red-700 text-white text-xs py-1 px-3 rounded ml-2 transition">Eliminar</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="gallery">
                <?php foreach ($images as $img): ?>
                    <div class="bg-gray-800 rounded-lg overflow-hidden border border-gray-700 relative group">
                        <a href="/view.php?id=<?= $img['unique_id'] ?>" class="block">
                            <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" alt="Img" class="w-full h-48 object-cover">
                        </a>
                        
                        <?php if ($isOwner): ?>
                            <div class="absolute top-2 left-2 z-10 bg-black/50 p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity">
                                <input type="checkbox" class="img-checkbox form-checkbox h-4 w-4 text-indigo-600 cursor-pointer" value="<?= $img['unique_id'] ?>" onchange="updateBulkUI()">
                            </div>
                        <?php endif; ?>

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
        // Funciones individuales
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

        // Funciones masivas (Bulk)
        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('.img-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('.img-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
            updateBulkUI();
        }

        function updateBulkUI() {
            const ids = getSelectedIds();
            const bulkBar = document.getElementById('bulkActionsBar');
            const countTxt = document.getElementById('selectedCountTxt');
            const selectAllCb = document.getElementById('selectAllCb');
            const allCheckboxes = document.querySelectorAll('.img-checkbox');
            
            if (bulkBar) {
                if (ids.length > 0) {
                    bulkBar.classList.remove('hidden');
                    countTxt.innerText = ids.length + (ids.length === 1 ? ' seleccionada' : ' seleccionadas');
                } else {
                    bulkBar.classList.add('hidden');
                }
                
                if (selectAllCb) {
                    selectAllCb.checked = (ids.length === allCheckboxes.length && allCheckboxes.length > 0);
                }
            }
        }

        async function bulkDelete() {
            const ids = getSelectedIds();
            if (ids.length === 0) return;
            if (!confirm(`¿Seguro que quieres borrar ${ids.length} imágenes permanentemente?`)) return;
            
            const res = await fetch('/api_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'bulk_delete_images', ids: ids })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Error borrando imágenes');
        }

        async function bulkMove() {
            const ids = getSelectedIds();
            if (ids.length === 0) return;
            
            const select = document.getElementById('bulkMoveSelect');
            const targetAlbum = select.value;
            if (targetAlbum === "") {
                alert("Selecciona un álbum destino primero");
                return;
            }
            
            const res = await fetch('/api_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'bulk_move_images', ids: ids, new_album_id: targetAlbum === "0" ? null : targetAlbum })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Error moviendo imágenes');
        }
    </script>
</body>
</html>
