<?php
// Don't set headers here - let the calling script handle it
// This file should only establish the database connection

$host = 'localhost';
$dbname = 'dogfoodshop';
$username = 'root';
$password = '';

try {
    // Create PDO connection with additional options to prevent "MySQL server has gone away" error
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false, // Don't use persistent connections
            PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    
    // Set additional MySQL-specific settings to prevent connection issues
    $pdo->exec("SET SESSION wait_timeout = 28800");
    $pdo->exec("SET SESSION interactive_timeout = 28800");
    
} catch (PDOException $e) {
    // Don't output anything here - let the calling script handle errors
    // Just throw the exception so it can be caught by the calling script
    throw $e;
}

// Function to check and reconnect if connection is lost
function ensureConnection($pdo) {
    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        // Connection lost, recreate it
        global $host, $dbname, $username, $password;
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        );
        $pdo->exec("SET SESSION wait_timeout = 28800");
        $pdo->exec("SET SESSION interactive_timeout = 28800");
    }
    return $pdo;
}
?>

