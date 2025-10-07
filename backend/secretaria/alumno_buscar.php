<?php
// backend/secretaria/alumno_buscar.php
session_start(); header('Content-Type: application/json');
require __DIR__.'/../db.php';

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria'){
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}
$q=trim($_GET['q']??'');
if($q===''){ http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'q requerido']); exit; }

$isEmail = filter_var($q, FILTER_VALIDATE_EMAIL) !== false;
$sql = $isEmail
  ? "SELECT id, name, email, dni, phone FROM users WHERE email=:q LIMIT 1"
  : "SELECT id, name, email, dni, phone FROM users WHERE dni=:q LIMIT 1";
$st=$pdo->prepare($sql);
$st->execute([':q'=>$q]);
$u=$st->fetch();
echo json_encode(['ok'=>true,'user'=>$u?:null]);
