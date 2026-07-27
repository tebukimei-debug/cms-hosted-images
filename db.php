<?php
require_once __DIR__ . '/config.php';

function getDbConnection() {
    $dbUrl = DB_URL;
    if (!$dbUrl) {
        die("DATABASE_URL no configurada.");
    }
    
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
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS images (
            id SERIAL PRIMARY KEY,
            unique_id VARCHAR(21) UNIQUE NOT NULL,
            filename VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            thumb_url TEXT,
            size INTEGER NOT NULL,
            mime_type VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}
