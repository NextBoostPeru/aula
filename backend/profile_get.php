<?php
// backend/profile_get.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$uid = (int)$_SESSION['user']['id'];

try {
  $st = $pdo->prepare("SELECT id, name, email, dni, phone, avatar_url FROM users WHERE id = :id LIMIT 1");
  $st->execute([':id'=>$uid]);
  $u = $st->fetch();

  if (!$u) {
    http_response_code(404);
    echo json_encode(['ok'=>false, 'msg'=>'Usuario no encontrado']);
    exit;
  }

  echo json_encode([
    'ok'=>true,
    'user'=>[
      'id'=>(int)$u['id'],
      'name'=>$u['name'],
      'email'=>$u['email'],
      'dni'=>$u['dni'],
      'phone'=>$u['phone'],
      'avatar_url'=>$u['avatar_url']
    ]
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
