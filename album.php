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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0a0e1a; color: #e2e8f0; }
        .glass { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(99, 102, 241, 0.15); }
        .glass-light { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(8px); border: 1px solid rgba(148, 163, 184, 0.1); }
        .gradient-text { background: linear-gradient(135deg, #818cf8, #a78bfa, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25); }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.4); border-color: rgba(99, 102, 241, 0.3); }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .tag-public { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .tag-private { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .tag-password { background: rgba(234, 179, 8, 0.15); color: #facc15; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
    </style>
</head>
<body class="min-h-screen">

    <!-- Navbar -->
    <nav class="glass sticky top-0 z-40 mb-8">
        <div class="max-w-5xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="/" class="text-xl font-bold gradient-text tracking-tight">📷 UGirls</a>
            <div class="flex items-center gap-4">
                <a href="<?= $isOwner ? '/albums.php' : '/' ?>" class="text-sm text-gray-300 hover:text-indigo-400 transition font-medium">← Volver</a>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-6 pb-16">
        <!-- Album Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4 fade-in">
            <div>
                <h1 class="text-4xl font-extrabold gradient-text mb-1"><?= htmlspecialchars($album['name']) ?></h1>
                <div class="flex items-center gap-3 mt-2">
                    <?php
                        $p = $album['privacy'];
                        if ($p === 'public') echo '<span class="tag tag-public">Público</span>';
                        elseif ($p === 'private') echo '<span class="tag tag-private">Privado</span>';
                        else echo '<span class="tag tag-password">Contraseña</span>';
                    ?>
                    <?php if ($authOk): ?>
                        <span class="text-xs text-gray-500"><?= count($images) ?> imágenes</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($authOk && count($images) > 0): ?>
                <button onclick="document.getElementById('albumCodes').classList.toggle('hidden')" class="btn-primary text-white font-semibold py-2.5 px-6 rounded-xl flex items-center gap-2 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z" />
                    </svg>
                    Compartir Carpeta
                </button>
            <?php endif; ?>
        </div>

        <?php if ($authOk && count($images) > 0): ?>
            <!-- Share Codes -->
            <div id="albumCodes" class="hidden mb-10 glass rounded-2xl p-6 fade-in">
                <h2 class="text-lg font-bold mb-4 text-indigo-400 flex items-center gap-2">
                    <span>🔗</span> Códigos para compartir
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-2">BBCode (Foro)</label>
                        <textarea class="w-full h-28 bg-slate-900/60 border border-slate-700/50 text-gray-300 p-3 rounded-xl text-xs font-mono resize-none" readonly onclick="this.select()"><?= htmlspecialchars(trim($albumForumCode)) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-2">HTML Links</label>
                        <textarea class="w-full h-28 bg-slate-900/60 border border-slate-700/50 text-gray-300 p-3 rounded-xl text-xs font-mono resize-none" readonly onclick="this.select()"><?= htmlspecialchars(trim($albumHtmlCode)) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-2">HTML Grid (4 Col.)</label>
                        <textarea class="w-full h-28 bg-slate-900/60 border border-slate-700/50 text-gray-300 p-3 rounded-xl text-xs font-mono resize-none" readonly onclick="this.select()"><?= htmlspecialchars(trim($albumGridCode)) ?></textarea>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$authOk): ?>
            <div class="glass rounded-2xl p-10 max-w-md mx-auto text-center fade-in">
                <div class="w-16 h-16 rounded-2xl bg-yellow-500/10 flex items-center justify-center mx-auto mb-5">
                    <span class="text-3xl">🔒</span>
                </div>
                <h2 class="text-xl font-bold mb-4">Este álbum está protegido</h2>
                <?php if (isset($error)): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-300 p-3 rounded-xl mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="password" name="album_password" placeholder="Ingresa la contraseña" class="w-full bg-slate-800/80 border border-slate-600/50 rounded-xl px-4 py-3 text-white text-center mb-4" required>
                    <button type="submit" class="btn-primary text-white font-semibold py-3 px-8 rounded-xl w-full">Desbloquear</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Controls: Sort & Bulk -->
            <div class="glass rounded-2xl p-4 mb-6 flex flex-col sm:flex-row justify-between items-center gap-4 fade-in">
                <div class="flex items-center gap-3">
                    <label class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Ordenar:</label>
                    <select onchange="window.location.href='?id=<?= $album['unique_id'] ?>&sort='+this.value" class="bg-slate-800/80 border border-slate-600/50 rounded-lg px-3 py-2 text-white text-sm">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Más recientes</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Más antiguas</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Nombre (A-Z)</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Nombre (Z-A)</option>
                    </select>
                </div>
                
                <?php if ($isOwner && count($images) > 0): ?>
                <div class="flex items-center gap-3 flex-wrap">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                        <input type="checkbox" id="selectAllCb" class="form-checkbox h-4 w-4 text-indigo-600 rounded" onchange="toggleAll(this)">
                        <span class="text-xs font-medium">Seleccionar Todo</span>
                    </label>
                    <div id="bulkActionsBar" class="hidden flex items-center gap-2 border-l border-slate-600/50 pl-3">
                        <span id="selectedCountTxt" class="text-xs text-indigo-400 font-bold">0</span>
                        
                        <select id="bulkMoveSelect" class="bg-slate-800/80 border border-slate-600/50 rounded-lg px-2 py-1.5 text-white text-xs">
                            <option value="" disabled selected>Mover a...</option>
                            <option value="0">(General)</option>
                            <?php foreach ($userAlbums as $ua): ?>
                                <option value="<?= $ua['id'] ?>"><?= htmlspecialchars($ua['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="bulkMove()" class="btn-primary text-white text-xs py-1.5 px-3 rounded-lg">Mover</button>
                        <button onclick="bulkDelete()" class="btn-danger text-white text-xs py-1.5 px-3 rounded-lg">Eliminar</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Gallery Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 fade-in" id="gallery">
                <?php foreach ($images as $img): ?>
                    <div class="rounded-xl overflow-hidden border border-slate-700/40 relative group card-hover bg-slate-800/40">
                        <a href="/view.php?id=<?= $img['unique_id'] ?>" class="block aspect-[4/3] overflow-hidden">
                            <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" alt="Img" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                        </a>
                        
                        <?php if ($isOwner): ?>
                            <div class="absolute top-2 left-2 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                                <input type="checkbox" class="img-checkbox form-checkbox h-4 w-4 text-indigo-600 cursor-pointer rounded bg-black/40" value="<?= $img['unique_id'] ?>" onchange="updateBulkUI()">
                            </div>
                        <?php endif; ?>

                        <?php if ($img['title']): ?>
                            <div class="absolute bottom-0 w-full bg-gradient-to-t from-black/80 to-transparent p-3 pt-8">
                                <span class="text-xs text-gray-200 font-medium truncate block"><?= htmlspecialchars($img['title']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isAdmin() || (isLoggedIn() && $img['user_id'] == getActiveUserId())): ?>
                            <div class="absolute top-2 right-2 flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity bg-black/60 backdrop-blur-sm p-1.5 rounded-lg">
                                <button onclick="renameImage('<?= $img['unique_id'] ?>', '<?= htmlspecialchars(addslashes($img['title'] ?? '')) ?>')" class="text-yellow-400 hover:text-yellow-300 text-sm" title="Renombrar">✏️</button>
                                <button onclick="deleteImage('<?= $img['unique_id'] ?>')" class="text-red-400 hover:text-red-300 text-sm" title="Eliminar">🗑️</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($images)): ?>
                    <div class="col-span-4 text-center py-16">
                        <div class="w-16 h-16 rounded-2xl bg-slate-800/50 flex items-center justify-center mx-auto mb-4">
                            <span class="text-3xl">📂</span>
                        </div>
                        <p class="text-gray-500 text-sm">Esta carpeta está vacía.</p>
                    </div>
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

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.img-checkbox:checked')).map(cb => cb.value);
        }

        function toggleAll(source) {
            document.querySelectorAll('.img-checkbox').forEach(cb => cb.checked = source.checked);
            updateBulkUI();
        }

        function updateBulkUI() {
            const ids = getSelectedIds();
            const bulkBar = document.getElementById('bulkActionsBar');
            const countTxt = document.getElementById('selectedCountTxt');
            const selectAllCb = document.getElementById('selectAllCb');
            const allCbs = document.querySelectorAll('.img-checkbox');
            
            if (bulkBar) {
                if (ids.length > 0) {
                    bulkBar.classList.remove('hidden');
                    countTxt.innerText = ids.length + (ids.length === 1 ? ' seleccionada' : ' seleccionadas');
                } else {
                    bulkBar.classList.add('hidden');
                }
                if (selectAllCb) selectAllCb.checked = (ids.length === allCbs.length && allCbs.length > 0);
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
            if (targetAlbum === "") { alert("Selecciona una carpeta destino primero"); return; }
            
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
