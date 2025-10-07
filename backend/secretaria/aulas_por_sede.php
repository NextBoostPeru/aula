<?php
session_start(); header('Content-Type: application/json');
require __DIR__.'/../db.php';
if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria'){ http_response_code(403); echo json_encode(['ok'=>false]); exit; }
$uid=(int)$_SESSION['user']['id'];
$sede_id=(int)($_GET['sede_id']??0);
$chk=$pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
$chk->execute([':u'=>$uid,':s'=>$sede_id]);
if(!$chk->fetch()){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sede no asignada']); exit; }
$st=$pdo->prepare("SELECT a.id,a.nombre FROM aula a WHERE a.sede_id=:s ORDER BY a.nombre");
$st->execute([':s'=>$sede_id]);
echo json_encode(['ok'=>true,'aulas'=>$st->fetchAll()]);
