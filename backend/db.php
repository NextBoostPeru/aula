<?php
date_default_timezone_set('America/Lima');
// backend/db.php
$DB_HOST = 'localhost';
$DB_NAME = 'sanignaciodeloyo_aulavirtual';
$DB_USER = 'sanignaciodeloyo_adminaula';
$DB_PASS = 'MU)}AEI=B)i#'; // ajusta según tu entorno

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $pdo->exec("SET time_zone = '-05:00'");

} catch (PDOException $e) {
    http_response_code(500);
    die('DB Connection Error');
}
