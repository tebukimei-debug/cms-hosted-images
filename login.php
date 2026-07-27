<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: /');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Usuario y contraseña son requeridos.";
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            header('Location: /');
            exit;
        } else {
            $error = "Credenciales incorrectas.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Chevereto PHP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen flex items-center justify-center">
    <div class="bg-slate-800 p-8 rounded-xl shadow-xl w-full max-w-md">
        <h1 class="text-3xl font-bold mb-6 text-center text-blue-400">Iniciar Sesión</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-400 mb-2">Usuario</label>
                <input type="text" name="username" class="w-full bg-slate-700 border border-slate-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-400 mb-2">Contraseña</label>
                <input type="password" name="password" class="w-full bg-slate-700 border border-slate-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">Ingresar</button>
        </form>
        
        <div class="mt-4 text-center">
            <a href="/register.php" class="text-blue-400 hover:underline">¿No tienes cuenta? Regístrate</a>
        </div>
    </div>
</body>
</html>
