<?php
// backend/secretaria/alumno_detalle.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria') {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
  }
  $user_id = (int)($_GET['user_id'] ?? 0);
  if ($user_id<=0) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'user_id requerido']); exit; }

  $st = $pdo->prepare("SELECT id, name, email, dni, phone FROM users WHERE id=:u LIMIT 1");
  $st->execute([':u'=>$user_id]);
  $u = $st->fetch();
  if(!$u){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Usuario no encontrado']); exit; }

  echo json_encode(['ok'=>true,'user'=>$u]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error','detail'=>$e->getMessage()]);
}
