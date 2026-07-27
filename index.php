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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: white; }
    </style>
</head>
<body class="min-h-screen p-8">
    
    <!-- Navbar -->
    <div class="max-w-4xl mx-auto flex justify-end mb-4">
        <?php if (isLoggedIn()): ?>
            <a href="/albums.php" class="text-blue-400 mr-6 self-center hover:underline">Mis Álbumes</a>
            <span class="mr-4 text-gray-300 self-center">Hola, <b class="text-white"><?= htmlspecialchars(getActiveUsername()) ?></b></span>
            <a href="/logout.php" class="bg-red-500/20 text-red-400 border border-red-500 px-4 py-2 rounded hover:bg-red-500 hover:text-white transition">Cerrar Sesión</a>
        <?php else: ?>
            <a href="/login.php" class="text-blue-400 mr-4 self-center hover:underline">Iniciar Sesión</a>
            <a href="/register.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">Registrarse</a>
        <?php endif; ?>
    </div>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-4xl font-bold text-center mb-8 bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-500">
            Subir Imágenes (PHP Puro)
        </h1>

        <!-- Uploader -->
        <div class="mb-6 flex justify-between items-center bg-slate-800 p-4 rounded-xl border border-slate-700">
            <div>
                <label class="text-gray-400 mr-2 text-sm">Subir a:</label>
                <select id="albumSelect" class="bg-slate-700 border border-slate-600 rounded px-3 py-1 text-white text-sm outline-none">
                    <option value="">(Sin Álbum / General)</option>
                    <?php foreach ($albums as $album): ?>
                        <option value="<?= $album['id'] ?>"><?= htmlspecialchars($album['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <?php if (!isLoggedIn()): ?>
                    <span class="text-xs text-yellow-500">Inicia sesión para crear álbumes.</span>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <a href="/admin.php" class="bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-1 rounded ml-2 transition">Panel Admin</a>
                <?php endif; ?>
            </div>
        </div>

        <div id="dropzone" class="border-2 border-dashed border-gray-600 rounded-xl p-12 text-center bg-gray-800/50 hover:bg-gray-800 transition cursor-pointer mb-12 relative">
            <p class="text-xl text-gray-300">Arrastra imágenes aquí o haz clic para subir varias</p>
            <input type="file" id="fileInput" class="hidden" accept="image/*" multiple>
        </div>

        <div id="progress" class="hidden mb-8 text-center text-blue-400 font-semibold">Procesando y optimizando...</div>

        <!-- Resultados de Subida (Oculto por defecto) -->
        <div id="uploadResults" class="hidden mb-12 bg-slate-800 border border-slate-700 p-6 rounded-xl">
            <h2 class="text-2xl font-bold mb-6 text-center text-green-400">¡Subida Exitosa!</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <label class="block text-gray-300 font-semibold mb-2">Forum links (Todos)</label>
                    <textarea id="allForumLinks" class="w-full h-32 bg-slate-900 border border-slate-700 text-gray-300 p-2 rounded text-sm font-mono focus:outline-none focus:border-blue-500" readonly onclick="this.select()"></textarea>
                </div>
                <div>
                    <label class="block text-gray-300 font-semibold mb-2">HTML links (Todos)</label>
                    <textarea id="allHtmlLinks" class="w-full h-32 bg-slate-900 border border-slate-700 text-gray-300 p-2 rounded text-sm font-mono focus:outline-none focus:border-blue-500" readonly onclick="this.select()"></textarea>
                </div>
            </div>

            <div id="individualResults" class="space-y-8"></div>
            
            <div class="text-center mt-8">
                <button onclick="location.reload()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition">Subir Más / Ver Galería</button>
            </div>
        </div>

        <!-- Galería (Se ocultará al mostrar resultados) -->
        <div id="galleryContainer">
            <h2 class="text-2xl font-semibold mb-6">Últimas Imágenes</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="gallery">
                <?php foreach ($images as $img): ?>
                    <div class="bg-gray-800 rounded-lg overflow-hidden border border-gray-700 relative group">
                        <a href="<?= htmlspecialchars($img['url']) ?>" target="_blank" class="block">
                            <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" alt="Img" class="w-full h-48 object-cover">
                        </a>
                        
                        <?php if ($img['title']): ?>
                            <div class="absolute bottom-0 w-full bg-black/70 p-2 text-xs truncate text-white">
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

        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('border-blue-500');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('border-blue-500');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-blue-500');
            if (e.dataTransfer.files.length) {
                uploadFiles(e.dataTransfer.files);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                uploadFiles(e.target.files);
            }
        });

        async function uploadFiles(files) {
            progress.classList.remove('hidden');
            const formData = new FormData();
            
            for (let i = 0; i < files.length; i++) {
                formData.append('file[]', files[i]);
            }
            
            const albumId = document.getElementById('albumSelect')?.value;
            if (albumId) {
                formData.append('album_id', albumId);
            }

            try {
                const res = await fetch('/upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await res.text();
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const successfulUploads = data.results.filter(r => r.success);
                        let errors = data.results.filter(r => r.error);
                        
                        if (errors.length > 0) {
                            alert("Algunas imágenes fallaron:\n" + errors.map(e => e.name + ': ' + e.error).join('\n'));
                        }
                        
                        if (successfulUploads.length > 0) {
                            showResults(successfulUploads);
                        } else {
                            location.reload();
                        }
                    } else {
                        alert('Error: ' + data.error);
                    }
                } catch (e) {
                    console.error("Respuesta cruda del servidor:", text);
                    alert('Error en el servidor. Revisa la consola o asegúrate de que la imagen no sea demasiado grande.');
                }
            } catch (err) {
                alert('Error de red al intentar conectar con el servidor.');
            } finally {
                progress.classList.add('hidden');
            }
        }

        function showResults(uploads) {
            dropzone.classList.add('hidden');
            galleryContainer.classList.add('hidden');
            uploadResults.classList.remove('hidden');

            const origin = window.location.origin;
            let forumAll = [];
            let htmlAll = [];
            individualResults.innerHTML = '';

            uploads.forEach(u => {
                const fullUrl = origin + u.url;
                const thumbUrl = origin + u.thumb_url;
                
                const forumCode = `[url=${fullUrl}][img]${thumbUrl}[/img][/url]`;
                const htmlCode = `<a href="${fullUrl}"><img src="${thumbUrl}" alt="${u.name}" border="0"></a>`;
                
                forumAll.push(forumCode);
                htmlAll.push(htmlCode);

                const indDiv = document.createElement('div');
                indDiv.className = "flex flex-col md:flex-row items-center bg-slate-900 p-4 rounded-lg border border-slate-700 gap-6";
                indDiv.innerHTML = `
                    <div class="flex-shrink-0">
                        <a href="${fullUrl}" target="_blank">
                            <img src="${thumbUrl}" class="max-w-[150px] max-h-[150px] object-cover rounded shadow">
                        </a>
                        <p class="text-xs text-gray-400 mt-2 text-center break-all w-[150px]">${u.name}</p>
                    </div>
                    <div class="flex-grow w-full space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide">Show to friend:</label>
                            <input type="text" class="w-full bg-slate-800 border border-slate-600 rounded px-2 py-1 text-sm text-gray-200" value="${fullUrl}" readonly onclick="this.select()">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide">Forum thumbnail:</label>
                            <input type="text" class="w-full bg-slate-800 border border-slate-600 rounded px-2 py-1 text-sm text-gray-200" value="${forumCode.replace(/"/g, '&quot;')}" readonly onclick="this.select()">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide">HTML thumbnail:</label>
                            <input type="text" class="w-full bg-slate-800 border border-slate-600 rounded px-2 py-1 text-sm text-gray-200" value="${htmlCode.replace(/"/g, '&quot;')}" readonly onclick="this.select()">
                        </div>
                    </div>
                `;
                individualResults.appendChild(indDiv);
            });

            allForumLinks.value = forumAll.join('\n');
            allHtmlLinks.value = htmlAll.join('\n');
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
