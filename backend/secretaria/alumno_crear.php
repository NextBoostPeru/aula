<?php
// backend/secretaria/alumno_crear.php
session_start(); header('Content-Type: application/json');
require __DIR__.'/../db.php';

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria'){
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

$name   = trim($_POST['name']??'');
$dni    = trim($_POST['dni']??'');
$email  = trim($_POST['email']??'');
$phone  = trim($_POST['phone']??'');
$pass   = (string)($_POST['password']??''); // opcional: si vacío, generamos

if($name==='' || $dni===''){ http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Nombre y DNI son obligatorios']); exit; }

// unicidad simple
$dup=$pdo->prepare("SELECT 1 FROM users WHERE dni=:dni OR email=:email LIMIT 1");
$dup->execute([':dni'=>$dni, ':email'=>$email]);
if($dup->fetch()){ http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'DNI o Email ya existe']); exit; }

if($pass===''){
  // genera password aleatoria corta
  $pass = substr(bin2hex(random_bytes(8)),0,8);
}
$hash = password_hash($pass, PASSWORD_DEFAULT);

$ins=$pdo->prepare("INSERT INTO users (name, dni, email, phone, password_hash, status, role)
                    VALUES (:n,:d,:e,:p,:h,1,'alumno')");
$ins->execute([':n'=>$name, ':d'=>$dni, ':e'=>$email?:null, ':p'=>$phone?:null, ':h'=>$hash]);

echo json_encode(['ok'=>true,'user_id'=>(int)$pdo->lastInsertId(), 'temp_password'=>$pass]);
