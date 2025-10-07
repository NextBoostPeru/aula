<?php
// /aula/backend/auth/login.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/jwt.php';

try {
  $emailOrDni = trim($_POST['user'] ?? '');
  $password   = trim($_POST['password'] ?? '');

  if ($emailOrDni === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'Usuario y contraseña requeridos']); exit;
  }

  // Buscar por email o DNI
  $st = $pdo->prepare("SELECT id, name, email, dni, password_hash, role, status FROM users WHERE (email=? OR dni=?) LIMIT 1");
  $st->execute([$emailOrDni, $emailOrDni]);
  $u = $st->fetch();
  if (!$u || !(int)$u['status']) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'Credenciales inválidas']); exit;
  }

  if (!password_verify($password, $u['password_hash'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'Credenciales inválidas']); exit;
  }

  // Sedes si es secretaria
  $sedes = [];
  if ($u['role'] === 'secretaria') {
    $st2 = $pdo->prepare("SELECT s.id, s.nombre FROM secretaria_sede ss JOIN sede s ON s.id=ss.sede_id WHERE ss.user_id=? ORDER BY s.nombre");
    $st2->execute([$u['id']]);
    $sedes = $st2->fetchAll();
  }

  $payload = [
    'uid' => (int)$u['id'],
    'role'=> $u['role'] ?? '',
    'name'=> $u['name'] ?? '',
    'email'=> $u['email'] ?? '',
  ];
  $token = jwt_create($payload);

  echo json_encode([
    'ok'=>true,
    'token'=>$token,
    'user'=>[
      'id'=>(int)$u['id'],
      'name'=>$u['name'],
      'email'=>$u['email'],
      'dni'=>$u['dni'],
      'role'=>$u['role'],
      'sedes'=>$sedes
    ]
  ]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo iniciar sesión','err'=>$e->getMessage()]);
}
