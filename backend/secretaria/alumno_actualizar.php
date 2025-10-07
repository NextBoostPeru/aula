<?php
// backend/secretaria/alumno_actualizar.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria') {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
  }

  $user_id = (int)($_POST['user_id'] ?? 0);
  $name    = trim($_POST['name'] ?? '');
  $dni     = trim($_POST['dni'] ?? '');
  $email   = trim($_POST['email'] ?? '');
  $phone   = trim($_POST['phone'] ?? '');

  if ($user_id<=0 || $name==='') {
    http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit;
  }

  // Validaciones básicas de unicidad (si envían)
  if ($dni!=='') {
    $q = $pdo->prepare("SELECT id FROM users WHERE dni=:d AND id<>:u LIMIT 1");
    $q->execute([':d'=>$dni, ':u'=>$user_id]);
    if ($q->fetch()) { http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'DNI ya registrado']); exit; }
  }
  if ($email!=='') {
    $q = $pdo->prepare("SELECT id FROM users WHERE email=:e AND id<>:u LIMIT 1");
    $q->execute([':e'=>$email, ':u'=>$user_id]);
    if ($q->fetch()) { http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'Email ya registrado']); exit; }
  }

  $st = $pdo->prepare("UPDATE users SET name=:n, dni=:d, email=:e, phone=:p WHERE id=:u");
  $ok = $st->execute([':n'=>$name, ':d'=>$dni, ':e'=>$email, ':p'=>$phone, ':u'=>$user_id]);
  if(!$ok) throw new Exception('No se pudo actualizar');

  echo json_encode(['ok'=>true,'msg'=>'Datos actualizados']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al actualizar','detail'=>$e->getMessage()]);
}
