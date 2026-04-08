<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS saas_foodtech");
    echo "Database 'saas_foodtech' created successfully!\n";
} catch (PDOException $e) {
    echo "Error creating database: " . $e->getMessage() . "\n";
}
