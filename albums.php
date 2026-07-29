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

$stmtGeneral = $pdo->prepare("SELECT COUNT(*) FROM images WHERE album_id IS NULL AND user_id = ?");
$stmtGeneral->execute([$userId]);
$generalCount = $stmtGeneral->fetchColumn();

$totalImages = $generalCount;
foreach ($albums as $a) $totalImages += $a['image_count'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Álbumes - Chevereto PHP</title>
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
        .row-hover { transition: all 0.2s ease; }
        .row-hover:hover { background: rgba(99, 102, 241, 0.05); border-color: rgba(99, 102, 241, 0.25); }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .tag-public { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .tag-private { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .tag-password { background: rgba(234, 179, 8, 0.15); color: #facc15; }
        input:focus, select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
    </style>
</head>
<body class="min-h-screen">
    
    <!-- Navbar -->
    <nav class="glass sticky top-0 z-40 mb-8">
        <div class="max-w-5xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="/" class="text-xl font-bold gradient-text tracking-tight">📷 UGirls</a>
            <div class="flex items-center gap-4">
                <a href="/" class="text-sm text-gray-300 hover:text-indigo-400 transition font-medium">🏠 Inicio</a>
                <span class="text-sm text-gray-500">|</span>
                <span class="text-sm text-gray-400">Hola, <b class="text-white"><?= htmlspecialchars(getActiveUsername()) ?></b></span>
                <a href="/logout.php" class="text-xs text-red-400 border border-red-500/30 px-3 py-1.5 rounded-lg hover:bg-red-500/10 transition">Salir</a>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-6 pb-16">
        
        <!-- Header & Stats -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
            <div>
                <h1 class="text-4xl font-extrabold gradient-text mb-1">Mis Carpetas</h1>
                <p class="text-gray-500 text-sm"><?= count($albums) + 1 ?> carpetas · <?= $totalImages ?> imágenes en total</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-300 p-4 rounded-xl mb-5 text-sm flex items-center gap-2 fade-in">
                <span>⚠️</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-500/10 border border-green-500/30 text-green-300 p-4 rounded-xl mb-5 text-sm flex items-center gap-2 fade-in">
                <span>✅</span> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Create Album Form -->
        <div class="glass rounded-2xl p-6 mb-10 fade-in">
            <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-indigo-500/15 flex items-center justify-center text-sm">📁</span>
                Crear Nueva Carpeta
            </h2>
            <form method="POST" action="" class="flex flex-col md:flex-row gap-4 items-end">
                <input type="hidden" name="action" value="create_album">
                <div class="flex-1">
                    <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1.5">Nombre</label>
                    <input type="text" name="name" class="w-full bg-slate-800/80 border border-slate-600/50 rounded-lg px-4 py-2.5 text-white text-sm" placeholder="Ej: Vacaciones 2026" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1.5">Privacidad</label>
                    <select name="privacy" id="privacySelect" class="w-full bg-slate-800/80 border border-slate-600/50 rounded-lg px-4 py-2.5 text-white text-sm">
                        <option value="public">Público</option>
                        <option value="private">Privado</option>
                        <option value="password">Con Contraseña</option>
                    </select>
                </div>
                <div id="passwordField" class="hidden">
                    <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1.5">Contraseña</label>
                    <input type="password" name="password" class="w-full bg-slate-800/80 border border-slate-600/50 rounded-lg px-4 py-2.5 text-white text-sm" placeholder="••••••">
                </div>
                <button type="submit" class="btn-primary text-white font-semibold py-2.5 px-6 rounded-lg text-sm whitespace-nowrap h-[42px]">+ Crear</button>
            </form>
        </div>

        <!-- Folder List -->
        <div class="space-y-2 fade-in">
            <!-- Table Header -->
            <div class="hidden sm:grid grid-cols-12 gap-4 px-5 py-2 text-[11px] text-gray-500 uppercase tracking-widest font-bold">
                <div class="col-span-5">Nombre</div>
                <div class="col-span-2 text-center">Imágenes</div>
                <div class="col-span-2 text-center">Privacidad</div>
                <div class="col-span-2 text-center">Creación</div>
                <div class="col-span-1"></div>
            </div>

            <!-- General Folder (Always first) -->
            <a href="/album.php?id=general" class="block glass-light rounded-xl px-5 py-4 row-hover group">
                <div class="grid grid-cols-12 gap-4 items-center">
                    <div class="col-span-12 sm:col-span-5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/15 flex items-center justify-center flex-shrink-0">
                            <span class="text-lg">📂</span>
                        </div>
                        <div>
                            <p class="font-bold text-indigo-300 group-hover:text-indigo-200 transition text-sm">General (Sin Carpeta)</p>
                            <p class="text-[11px] text-gray-500 sm:hidden"><?= $generalCount ?> imágenes</p>
                        </div>
                    </div>
                    <div class="hidden sm:flex col-span-2 justify-center">
                        <span class="text-sm font-semibold text-gray-300"><?= $generalCount ?></span>
                    </div>
                    <div class="hidden sm:flex col-span-2 justify-center">
                        <span class="tag tag-public">Público</span>
                    </div>
                    <div class="hidden sm:flex col-span-2 justify-center">
                        <span class="text-xs text-gray-500">—</span>
                    </div>
                    <div class="hidden sm:flex col-span-1 justify-end">
                        <svg class="w-4 h-4 text-gray-600 group-hover:text-indigo-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </div>
            </a>

            <!-- User Albums -->
            <?php foreach ($albums as $album): ?>
                <div class="glass-light rounded-xl px-5 py-4 row-hover group relative">
                    <div class="grid grid-cols-12 gap-4 items-center">
                        <a href="/album.php?id=<?= $album['unique_id'] ?>" class="col-span-12 sm:col-span-5 flex items-center gap-3 cursor-pointer">
                            <div class="w-10 h-10 rounded-xl bg-slate-700/50 flex items-center justify-center flex-shrink-0">
                                <span class="text-lg">📁</span>
                            </div>
                            <div>
                                <p class="font-bold text-white group-hover:text-indigo-300 transition text-sm truncate max-w-[280px]" title="<?= htmlspecialchars($album['name']) ?>"><?= htmlspecialchars($album['name']) ?></p>
                                <p class="text-[11px] text-gray-500 sm:hidden"><?= $album['image_count'] ?> imágenes · <?= date('d M Y', strtotime($album['created_at'])) ?></p>
                            </div>
                        </a>
                        <div class="hidden sm:flex col-span-2 justify-center">
                            <span class="text-sm font-semibold text-gray-300"><?= $album['image_count'] ?></span>
                        </div>
                        <div class="hidden sm:flex col-span-2 justify-center">
                            <?php
                                if ($album['privacy'] === 'public') echo '<span class="tag tag-public">Público</span>';
                                elseif ($album['privacy'] === 'private') echo '<span class="tag tag-private">Privado</span>';
                                else echo '<span class="tag tag-password">Contraseña</span>';
                            ?>
                        </div>
                        <div class="hidden sm:flex col-span-2 justify-center">
                            <span class="text-xs text-gray-500"><?= date('d M Y', strtotime($album['created_at'])) ?></span>
                        </div>
                        <div class="hidden sm:flex col-span-1 justify-end items-center gap-2">
                            <button onclick="event.stopPropagation(); renameAlbum(<?= $album['id'] ?>, '<?= htmlspecialchars(addslashes($album['name'])) ?>')" class="opacity-0 group-hover:opacity-100 text-yellow-400 hover:text-yellow-300 transition p-1" title="Renombrar">✏️</button>
                            <button onclick="event.stopPropagation(); deleteAlbum(<?= $album['id'] ?>)" class="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-300 transition p-1" title="Eliminar">🗑️</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($albums) && $generalCount === 0): ?>
                <div class="text-center py-16">
                    <div class="w-16 h-16 rounded-2xl bg-slate-800/50 flex items-center justify-center mx-auto mb-4">
                        <span class="text-3xl">📂</span>
                    </div>
                    <p class="text-gray-500 text-sm">Aún no tienes carpetas. ¡Crea tu primera carpeta arriba!</p>
                </div>
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

        async function renameAlbum(id, oldName) {
            const newName = prompt("Nuevo nombre de la carpeta:", oldName);
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
            if (!confirm("¿Seguro que quieres borrar esta carpeta? Las fotos NO se borrarán, pasarán a General.")) return;
            const res = await fetch('/api_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'delete_album', id: id })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error);
        }
    </script>
</body>
</html>
