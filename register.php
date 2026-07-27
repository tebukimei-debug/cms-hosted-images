<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: /');
    exit;
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Usuario y contraseña son requeridos.";
    } elseif (strlen($username) < 3) {
        $error = "El usuario debe tener al menos 3 caracteres.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = "Ese nombre de usuario ya está en uso.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$username, $hash]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - Chevereto PHP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen flex items-center justify-center">
    <div class="bg-slate-800 p-8 rounded-xl shadow-xl w-full max-w-md">
        <h1 class="text-3xl font-bold mb-6 text-center text-blue-400">Crear Cuenta</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-300 p-3 rounded mb-4 text-center">
                ¡Cuenta creada exitosamente! <br><br>
                <a href="/login.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-block">Iniciar Sesión</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-gray-400 mb-2">Usuario</label>
                    <input type="text" name="username" class="w-full bg-slate-700 border border-slate-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-400 mb-2">Contraseña</label>
                    <input type="password" name="password" class="w-full bg-slate-700 border border-slate-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">Registrarse</button>
            </form>
        <?php endif; ?>
        
        <div class="mt-4 text-center">
            <a href="/login.php" class="text-gray-400 hover:text-blue-400 hover:underline">Volver al login</a>
        </div>
    </div>
</body>
</html>
