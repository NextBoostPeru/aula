<?php
// /aula/backend/lib/db.php
require_once __DIR__ . '/env.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ALLOWED_ORIGINS, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Vary: Origin'); // para que los proxies cacheen por origen
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Cone

$dsn = "mysql:host=localhost;dbname=sanignaciodeloyo_aulavirtual;charset=utf8mb4";
$user = "sanignaciodeloyo_adminaula";
$pass = "MU)}AEI=B)i#";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  // TZ server/SQL en Lima
  $pdo->exec("SET time_zone='-05:00'");
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'DB error','err'=>$e->getMessage()]);
  exit;
}
