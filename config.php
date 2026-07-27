<?php
require_once __DIR__ . '/vendor/autoload.php';

// Cargar .env si existe (útil en local)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Configuración
define('DB_URL', $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL'));
define('S3_ENDPOINT', $_ENV['S3_ENDPOINT'] ?? getenv('S3_ENDPOINT'));
define('S3_ACCESS_KEY', $_ENV['S3_ACCESS_KEY'] ?? getenv('S3_ACCESS_KEY'));
define('S3_SECRET_KEY', $_ENV['S3_SECRET_KEY'] ?? getenv('S3_SECRET_KEY'));
define('S3_BUCKET', $_ENV['S3_BUCKET'] ?? getenv('S3_BUCKET'));
define('S3_REGION', $_ENV['S3_REGION'] ?? getenv('S3_REGION') ?? 'us-east-1');
define('APP_URL', $_ENV['NEXT_PUBLIC_APP_URL'] ?? getenv('NEXT_PUBLIC_APP_URL') ?? '');
