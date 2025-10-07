<?php
session_start(); header('Content-Type: application/json');
require __DIR__.'/../db.php';
if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria'){
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}
$uid=(int)$_SESSION['user']['id'];
$st=$pdo->prepare("SELECT s.id, s.nombre FROM secretaria_sede ss JOIN sede s ON s.id=ss.sede_id WHERE ss.user_id=:u ORDER BY s.nombre");
$st->execute([':u'=>$uid]);
echo json_encode(['ok'=>true,'sedes'=>$st->fetchAll()]);
