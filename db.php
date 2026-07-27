<?php
require_once __DIR__ . '/config.php';

function getDbConnection() {
    $dbUrl = DB_URL;
    if (!$dbUrl) {
        die("DATABASE_URL no configurada.");
    }
    
    // Parse postgres:// user:pass @ host:port / dbname
    $url = parse_url($dbUrl);
    
    $host = $url['host'];
    $port = $url['port'] ?? 5432;
    $db = ltrim($url['path'], '/');
    $user = $url['user'];
    $pass = $url['pass'];

    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}
