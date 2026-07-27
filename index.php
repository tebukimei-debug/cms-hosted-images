<?php
require_once __DIR__ . '/db.php';

$pdo = getDbConnection();
$stmt = $pdo->query("SELECT * FROM images ORDER BY created_at DESC LIMIT 20");
$images = $stmt->fetchAll();
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
    <div class="max-w-4xl mx-auto">
        <h1 class="text-4xl font-bold text-center mb-8 bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-500">
            Subir Imágenes (PHP Puro)
        </h1>

        <!-- Uploader -->
        <div id="dropzone" class="border-2 border-dashed border-gray-600 rounded-xl p-12 text-center bg-gray-800/50 hover:bg-gray-800 transition cursor-pointer mb-12">
            <p class="text-xl text-gray-300">Arrastra una imagen aquí o haz clic para subir</p>
            <input type="file" id="fileInput" class="hidden" accept="image/*">
        </div>

        <div id="progress" class="hidden mb-8 text-center text-blue-400">Procesando y optimizando...</div>

        <!-- Galería -->
        <h2 class="text-2xl font-semibold mb-6">Últimas Imágenes</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="gallery">
            <?php foreach ($images as $img): ?>
                <a href="<?= htmlspecialchars($img['url']) ?>" target="_blank" class="bg-gray-800 rounded-lg overflow-hidden border border-gray-700 block hover:border-blue-500 transition">
                    <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" alt="Img" class="w-full h-48 object-cover">
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const progress = document.getElementById('progress');

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
                uploadFile(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                uploadFile(e.target.files[0]);
            }
        });

        async function uploadFile(file) {
            progress.classList.remove('hidden');
            const formData = new FormData();
            formData.append('file', file);

            try {
                const res = await fetch('/upload.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                alert('Error de red');
            } finally {
                progress.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
