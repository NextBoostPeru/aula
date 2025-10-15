<?php
// backend/secretaria/alumno_crear.php
session_start();
header('Content-Type: application/json');
require __DIR__.'/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'secretaria') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
  }

  $name  = trim((string)($_POST['name'] ?? ''));
  $dni   = trim((string)($_POST['dni'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $pass  = (string)($_POST['password'] ?? ''); // opcional, si vacío se genera

  if ($name === '' || $dni === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Nombre y DNI son obligatorios']);
    exit;
  }

  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Email inválido']);
    exit;
  }

  $dupSql = 'SELECT 1 FROM users WHERE dni = :dni';
  $dupParams = [':dni' => $dni];
  if ($email !== '') {
    $dupSql .= ' OR email = :email';
    $dupParams[':email'] = $email;
  }
  $dupSql .= ' LIMIT 1';
  $dup = $pdo->prepare($dupSql);
  $dup->execute($dupParams);
  if ($dup->fetch()) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'msg' => 'DNI o Email ya existe']);
    exit;
  }

  if ($pass === '') {
    $pass = substr(bin2hex(random_bytes(8)), 0, 8);
  }
  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $ins = $pdo->prepare('INSERT INTO users (name, dni, email, phone, password_hash, status, role)
                         VALUES (:name, :dni, :email, :phone, :hash, 1, :role)');
  $ins->execute([
    ':name'  => $name,
    ':dni'   => $dni,
    ':email' => $email !== '' ? $email : null,
    ':phone' => $phone !== '' ? $phone : null,
    ':hash'  => $hash,
    ':role'  => 'alumno',
  ]);

  echo json_encode([
    'ok'            => true,
    'user_id'       => (int)$pdo->lastInsertId(),
    'temp_password' => $pass,
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  error_log('[alumno_crear] '.$e->getMessage());
  echo json_encode(['ok' => false, 'msg' => 'Error al crear alumno']);
}
