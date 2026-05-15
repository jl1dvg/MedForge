<?php
// config/database.php

static $pdo = null;

if ($pdo instanceof PDO) {
    return $pdo;
}

$host = getenv('DB_HOST') ?: '74.208.195.146';
$db = getenv('DB_NAME') ?: 'medforge';
$user = getenv('DB_USER') ?: 'jl1dvg';
$pass = getenv('DB_PASSWORD') ?: 'JorgeAMI2018';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';
$timezone = getenv('DB_TIMEZONE') ?: '-05:00';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $db, $charset);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '" . addslashes($timezone) . "'");
} catch (PDOException $e) {
    die('Error en la conexión a la base de datos: ' . $e->getMessage());
}

return $pdo;
