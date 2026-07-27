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
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS albums (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            unique_id VARCHAR(21) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            privacy VARCHAR(20) DEFAULT 'public',
            password_hash VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS images (
            id SERIAL PRIMARY KEY,
            unique_id VARCHAR(21) UNIQUE NOT NULL,
            filename VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            thumb_url TEXT,
            size INTEGER NOT NULL,
            mime_type VARCHAR(50) NOT NULL,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            album_id INTEGER REFERENCES albums(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        try {
            $pdo->exec("ALTER TABLE images ADD COLUMN IF NOT EXISTS thumb_url TEXT");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("ALTER TABLE images ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(id) ON DELETE SET NULL");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("ALTER TABLE images ADD COLUMN IF NOT EXISTS album_id INTEGER REFERENCES albums(id) ON DELETE SET NULL");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("ALTER TABLE images ADD COLUMN IF NOT EXISTS title VARCHAR(255)");
        } catch (PDOException $e) {}

        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}
