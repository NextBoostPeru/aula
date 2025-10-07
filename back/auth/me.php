<?php
// /aula/backend/auth/me.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/jwt.php';

function getBearer(){
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (stripos($hdr,'Bearer ') === 0) return trim(substr($hdr, 7));
  return null;
}

try {
  $token = getBearer();
  if (!$token) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Sin token']); exit; }

  $payload = jwt_verify($token);
  if (!$payload) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Token inválido/expirado']); exit; }

  $st = $pdo->prepare("SELECT id,name,email,dni,role,status FROM users WHERE id=? LIMIT 1");
  $st->execute([$payload['uid']]);
  $u = $st->fetch();
  if (!$u || !(int)$u['status']) {
    http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Usuario inválido']); exit;
  }

  $sedes=[];
  if ($u['role']==='secretaria'){
    $st2 = $pdo->prepare("SELECT s.id, s.nombre FROM secretaria_sede ss JOIN sede s ON s.id=ss.sede_id WHERE ss.user_id=? ORDER BY s.nombre");
    $st2->execute([$u['id']]);
    $sedes = $st2->fetchAll();
  }

  echo json_encode(['ok'=>true,'user'=>[
    'id'=>(int)$u['id'],
    'name'=>$u['name'],
    'email'=>$u['email'],
    'dni'=>$u['dni'],
    'role'=>$u['role'],
    'sedes'=>$sedes
  ]]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error','err'=>$e->getMessage()]);
}
