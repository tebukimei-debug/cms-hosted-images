<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$pdo = getDbConnection();
$stmt = $pdo->query("SELECT * FROM images ORDER BY created_at DESC LIMIT 20");
$images = $stmt->fetchAll();

$albums = [];
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT id, name FROM albums WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([getActiveUserId()]);
    $albums = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chevereto PHP Clone</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0a0e1a; color: #e2e8f0; }
        .glass { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(99, 102, 241, 0.15); }
        .glass-light { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(8px); border: 1px solid rgba(148, 163, 184, 0.1); }
        .gradient-text { background: linear-gradient(135deg, #818cf8, #a78bfa, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .dropzone-active { border-color: #818cf8 !important; background: rgba(99, 102, 241, 0.08) !important; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.25); }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.4); border-color: rgba(99, 102, 241, 0.3); }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .progress-bar { background: linear-gradient(90deg, #6366f1, #a78bfa); transition: width 0.4s ease; }
        .tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .tag-public { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .tag-private { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .tag-password { background: rgba(234, 179, 8, 0.15); color: #facc15; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 50; display: flex; align-items: center; justify-content: center; }
        .modal-box { background: #1e293b; border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 16px; padding: 28px; width: 90%; max-width: 420px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
    </style>
</head>
<body class="min-h-screen">
    
    <!-- Navbar -->
    <nav class="glass sticky top-0 z-40 mb-8">
        <div class="max-w-5xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="/" class="text-xl font-bold gradient-text tracking-tight">📷 UGirls</a>
            <div class="flex items-center gap-4">
                <?php if (isLoggedIn()): ?>
                    <a href="/albums.php" class="text-sm text-gray-300 hover:text-indigo-400 transition font-medium">📁 Mis Álbumes</a>
                    <?php if (isAdmin()): ?>
                        <a href="/admin.php" class="text-sm text-gray-300 hover:text-red-400 transition font-medium">⚙️ Admin</a>
                    <?php endif; ?>
                    <span class="text-sm text-gray-500">|</span>
                    <span class="text-sm text-gray-400">Hola, <b class="text-white"><?= htmlspecialchars(getActiveUsername()) ?></b></span>
                    <a href="/logout.php" class="text-xs text-red-400 border border-red-500/30 px-3 py-1.5 rounded-lg hover:bg-red-500/10 transition">Salir</a>
                <?php else: ?>
                    <a href="/login.php" class="text-sm text-gray-300 hover:text-white transition">Iniciar Sesión</a>
                    <a href="/register.php" class="btn-primary text-sm text-white font-semibold px-4 py-2 rounded-lg">Registrarse</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-6 pb-16">
        <div class="text-center mb-10">
            <h1 class="text-5xl font-extrabold gradient-text mb-3">Subir Imágenes</h1>
            <p class="text-gray-500 text-lg">Arrastra, suelta y comparte al instante</p>
        </div>

        <!-- Uploader Controls -->
        <div class="glass rounded-2xl p-5 mb-6 flex flex-col sm:flex-row justify-between items-center gap-4 fade-in">
            <div class="flex items-center gap-3 flex-wrap">
                <label class="text-sm text-gray-400 font-medium">Subir a:</label>
                <select id="albumSelect" class="bg-slate-800/80 border border-slate-600/50 rounded-lg px-4 py-2 text-white text-sm">
                    <option value="">(General)</option>
                    <?php foreach ($albums as $album): ?>
                        <option value="<?= $album['id'] ?>"><?= htmlspecialchars($album['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isLoggedIn()): ?>
                    <button onclick="openNewFolderModal()" class="text-indigo-400 hover:text-indigo-300 text-sm font-semibold border border-indigo-500/30 px-3 py-2 rounded-lg hover:bg-indigo-500/10 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Nueva Carpeta
                    </button>
                <?php endif; ?>
            </div>
            <?php if (!isLoggedIn()): ?>
                <span class="text-xs text-yellow-500/80">Inicia sesión para crear carpetas.</span>
            <?php endif; ?>
        </div>

        <!-- Dropzone -->
        <div id="dropzone" class="border-2 border-dashed border-slate-600/50 rounded-2xl p-16 text-center glass-light cursor-pointer mb-10 transition-all duration-300 hover:border-indigo-500/50 fade-in">
            <div class="flex flex-col items-center gap-4">
                <div class="w-20 h-20 rounded-2xl bg-indigo-500/10 flex items-center justify-center">
                    <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-lg text-gray-300 font-medium">Arrastra imágenes aquí</p>
                    <p class="text-sm text-gray-500 mt-1">o haz clic para seleccionar · JPG, PNG, WEBP, GIF</p>
                </div>
            </div>
            <input type="file" id="fileInput" class="hidden" accept="image/*" multiple>
        </div>

        <!-- Progress -->
        <div id="progress" class="hidden mb-8 fade-in">
            <div class="glass rounded-xl p-5">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-sm font-semibold text-indigo-400" id="progressText">Procesando...</span>
                    <span class="text-xs text-gray-500" id="progressCount">0/0</span>
                </div>
                <div class="w-full h-2 bg-slate-700/50 rounded-full overflow-hidden">
                    <div id="progressBar" class="progress-bar h-full rounded-full" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <!-- Upload Results -->
        <div id="uploadResults" class="hidden mb-12 fade-in">
            <div class="glass rounded-2xl p-8">
                <div class="flex items-center justify-center gap-3 mb-8">
                    <div class="w-10 h-10 rounded-full bg-green-500/15 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-green-400">¡Subida Exitosa!</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                    <div>
                        <label class="block text-gray-400 font-semibold mb-2 text-xs uppercase tracking-wider">BBCode (Foro)</label>
                        <textarea id="allForumLinks" class="w-full h-28 bg-slate-900/60 border border-slate-700/50 text-gray-300 p-3 rounded-xl text-xs font-mono resize-none" readonly onclick="this.select()"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-400 font-semibold mb-2 text-xs uppercase tracking-wider">HTML Links</label>
                        <textarea id="allHtmlLinks" class="w-full h-28 bg-slate-900/60 border border-slate-700/50 text-gray-300 p-3 rounded-xl text-xs font-mono resize-none" readonly onclick="this.select()"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-400 font-semibold mb-2 text-xs uppercase tracking-wider">HTML Grid (4 Col.)</label>
                        <textarea id="allHtmlGridLinks" class="w-full h-28 bg-slate-900/60 border border-slate-700/50 text-gray-300 p-3 rounded-xl text-xs font-mono resize-none" readonly onclick="this.select()"></textarea>
                    </div>
                </div>

                <div id="individualResults" class="space-y-4"></div>
                
                <div class="text-center mt-8">
                    <button onclick="location.reload()" class="btn-primary text-white font-semibold py-3 px-8 rounded-xl">Subir Más / Ver Galería</button>
                </div>
            </div>
        </div>

        <!-- Gallery -->
        <div id="galleryContainer" class="fade-in">
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                <span class="gradient-text">Últimas Imágenes</span>
                <span class="text-xs text-gray-500 font-normal mt-1">(<?= count($images) ?>)</span>
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4" id="gallery">
                <?php foreach ($images as $img): ?>
                    <div class="rounded-xl overflow-hidden border border-slate-700/40 relative group card-hover bg-slate-800/40">
                        <a href="/view.php?id=<?= $img['unique_id'] ?>" class="block aspect-[4/3] overflow-hidden">
                            <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" alt="Img" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                        </a>
                        
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
            </div>
        </div>
    </div>

    <!-- Modal: Nueva Carpeta -->
    <div id="newFolderModal" class="modal-overlay hidden" onclick="if(event.target===this) closeNewFolderModal()">
        <div class="modal-box fade-in">
            <h3 class="text-lg font-bold text-white mb-5 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-indigo-500/15 flex items-center justify-center text-indigo-400">📁</span>
                Crear Nueva Carpeta
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1.5">Nombre</label>
                    <input type="text" id="newFolderName" class="w-full bg-slate-800/80 border border-slate-600/50 rounded-lg px-4 py-2.5 text-white text-sm" placeholder="Mi Carpeta">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1.5">Privacidad</label>
                    <select id="newFolderPrivacy" class="w-full bg-slate-800/80 border border-slate-600/50 rounded-lg px-4 py-2.5 text-white text-sm" onchange="document.getElementById('newFolderPassField').classList.toggle('hidden', this.value !== 'password')">
                        <option value="public">Público</option>
                        <option value="private">Privado (Solo tú)</option>
                        <option value="password">Con Contraseña</option>
                    </select>
                </div>
                <div id="newFolderPassField" class="hidden">
                    <label class="block text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1.5">Contraseña</label>
                    <input type="password" id="newFolderPassword" class="w-full bg-slate-800/80 border border-slate-600/50 rounded-lg px-4 py-2.5 text-white text-sm" placeholder="••••••">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeNewFolderModal()" class="text-sm text-gray-400 hover:text-white px-4 py-2 rounded-lg transition">Cancelar</button>
                <button onclick="createFolder()" class="btn-primary text-white text-sm font-semibold px-5 py-2.5 rounded-lg">Crear Carpeta</button>
            </div>
        </div>
    </div>

    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const progress = document.getElementById('progress');
        const uploadResults = document.getElementById('uploadResults');
        const galleryContainer = document.getElementById('galleryContainer');
        const allForumLinks = document.getElementById('allForumLinks');
        const allHtmlLinks = document.getElementById('allHtmlLinks');
        const individualResults = document.getElementById('individualResults');

        dropzone.addEventListener('click', () => fileInput.click());
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dropzone-active'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dropzone-active'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dropzone-active');
            if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', (e) => { if (e.target.files.length) uploadFiles(e.target.files); });

        // -- Modal: Nueva Carpeta --
        function openNewFolderModal() {
            document.getElementById('newFolderModal').classList.remove('hidden');
            document.getElementById('newFolderName').value = '';
            document.getElementById('newFolderName').focus();
        }
        function closeNewFolderModal() {
            document.getElementById('newFolderModal').classList.add('hidden');
        }

        async function createFolder() {
            const name = document.getElementById('newFolderName').value.trim();
            const privacy = document.getElementById('newFolderPrivacy').value;
            const password = document.getElementById('newFolderPassword')?.value || '';
            if (!name) { alert('Escribe un nombre para la carpeta'); return; }

            const res = await fetch('/api_manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create_album', name, privacy, password })
            });
            const data = await res.json();
            if (data.success && data.album) {
                const sel = document.getElementById('albumSelect');
                const opt = document.createElement('option');
                opt.value = data.album.id;
                opt.text = data.album.name;
                opt.selected = true;
                sel.appendChild(opt);
                closeNewFolderModal();
            } else {
                alert(data.error || 'Error creando carpeta');
            }
        }

        // -- Upload --
        async function uploadFiles(files) {
            progress.classList.remove('hidden');
            const progressText = document.getElementById('progressText');
            const progressCount = document.getElementById('progressCount');
            const progressBar = document.getElementById('progressBar');
            
            const albumId = document.getElementById('albumSelect')?.value;
            let allResults = [];
            let hasError = false;
            const batchSize = 20;
            const totalFiles = files.length;
            
            for (let i = 0; i < totalFiles; i += batchSize) {
                const chunk = Array.from(files).slice(i, i + batchSize);
                const formData = new FormData();
                for (let j = 0; j < chunk.length; j++) formData.append('file[]', chunk[j]);
                if (albumId) formData.append('album_id', albumId);

                try {
                    const response = await fetch('/upload.php', { method: 'POST', body: formData });
                    const text = await response.text();
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.results) {
                            allResults = allResults.concat(data.results.filter(r => r.success));
                            const errors = data.results.filter(r => r.error);
                            if (errors.length > 0) {
                                alert("Algunas imágenes fallaron:\n" + errors.map(e => e.name + ': ' + e.error).join('\n'));
                                hasError = true;
                            }
                        } else {
                            alert('Error en lote: ' + (data.error || 'Desconocido'));
                            hasError = true;
                        }
                    } catch (e) {
                        console.error("Respuesta cruda:", text);
                        alert('Error del servidor. Revisa que la imagen no sea demasiado grande.');
                        hasError = true;
                    }
                } catch (err) {
                    alert('Error de red al conectar con el servidor.');
                    hasError = true;
                }
                
                const currentProgress = Math.min(i + batchSize, totalFiles);
                progressCount.innerText = currentProgress + '/' + totalFiles;
                progressBar.style.width = (currentProgress / totalFiles * 100) + '%';
                progressText.innerText = 'Procesando y optimizando...';
            }
            
            progress.classList.add('hidden');
            if (allResults.length > 0) showResults(allResults);
            else if (!hasError) alert('No se subió ninguna imagen.');
        }

        function showResults(uploads) {
            dropzone.classList.add('hidden');
            galleryContainer.classList.add('hidden');
            uploadResults.classList.remove('hidden');

            const origin = window.location.origin;
            let forumAll = [];
            let htmlAll = [];
            let htmlGridAll = '<div style="display: grid;grid-template-columns: repeat(auto-fit, minmax(24%, 1fr));gap: 16px;align-items: start;padding: 16px">';
            
            individualResults.innerHTML = '';

            uploads.forEach(u => {
                const thumbUrl = origin + u.thumb_url;
                const viewUrl = origin + '/view.php?id=' + u.id;
                
                const forumCode = `[url=${viewUrl}][img]${thumbUrl}[/img][/url]`;
                const htmlCode = `<a href="${viewUrl}"><img src="${thumbUrl}" alt="${u.name}" border="0"></a>`;
                const gridCode = `<a href="${viewUrl}"><img src="${thumbUrl}" alt="${u.name}" border="0"></a>`;
                
                forumAll.push(forumCode);
                htmlAll.push(htmlCode);
                htmlGridAll += gridCode;

                const indDiv = document.createElement('div');
                indDiv.className = "flex flex-col md:flex-row items-center glass-light p-4 rounded-xl gap-5 fade-in";
                indDiv.innerHTML = `
                    <div class="flex-shrink-0">
                        <a href="${viewUrl}" target="_blank">
                            <img src="${thumbUrl}" class="w-[120px] h-[90px] object-cover rounded-lg shadow-lg">
                        </a>
                        <p class="text-xs text-gray-500 mt-2 text-center break-all max-w-[120px]">${u.name}</p>
                    </div>
                    <div class="flex-grow w-full space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Show to friend</label>
                            <input type="text" class="w-full bg-slate-900/50 border border-slate-700/40 rounded-lg px-3 py-1.5 text-sm text-gray-300" value="${viewUrl}" readonly onclick="this.select()">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">BBCode</label>
                            <input type="text" class="w-full bg-slate-900/50 border border-slate-700/40 rounded-lg px-3 py-1.5 text-sm text-gray-300" value="${forumCode.replace(/"/g, '&quot;')}" readonly onclick="this.select()">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">HTML</label>
                            <input type="text" class="w-full bg-slate-900/50 border border-slate-700/40 rounded-lg px-3 py-1.5 text-sm text-gray-300" value="${htmlCode.replace(/"/g, '&quot;')}" readonly onclick="this.select()">
                        </div>
                    </div>
                `;
                individualResults.appendChild(indDiv);
            });

            htmlGridAll += '</div>';

            allForumLinks.value = forumAll.join(' ');
            allHtmlLinks.value = htmlAll.join(' ');
            document.getElementById('allHtmlGridLinks').value = htmlGridAll;
        }

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
