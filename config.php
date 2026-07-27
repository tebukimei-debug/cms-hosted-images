<?php
require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

define('DB_URL', $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL'));
define('APP_URL', $_ENV['NEXT_PUBLIC_APP_URL'] ?? getenv('NEXT_PUBLIC_APP_URL') ?? '');
