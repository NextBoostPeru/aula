<?php
// backend/secretaria/aula_cursos.php
session_start(); header('Content-Type: application/json');
require __DIR__.'/../db.php';

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria'){
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}
$uid=(int)$_SESSION['user']['id'];
$aula_id=(int)($_GET['aula_id']??0);
if($aula_id<=0){ http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'aula_id inválido']); exit; }

// validar sede asignada
$chk=$pdo->prepare("SELECT a.sede_id FROM aula a WHERE a.id=:a");
$chk->execute([':a'=>$aula_id]);
$sede=$chk->fetchColumn();
if(!$sede){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Aula no existe']); exit; }
$ok=$pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s");
$ok->execute([':u'=>$uid, ':s'=>$sede]);
if(!$ok->fetch()){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permiso']); exit; }

// listar cursos dictados en el aula
$st=$pdo->prepare("
  SELECT ac.id AS aula_curso_id, c.id AS curso_id, c.titulo
  FROM aula_curso ac
  JOIN curso c ON c.id=ac.curso_id
  WHERE ac.aula_id=:a
  ORDER BY c.titulo
");
$st->execute([':a'=>$aula_id]);
echo json_encode(['ok'=>true,'items'=>$st->fetchAll()]);
