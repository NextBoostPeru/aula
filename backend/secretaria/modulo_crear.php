<?php
// backend/secretaria/modulo_crear.php
session_start(); header('Content-Type: application/json');
require __DIR__.'/../db.php';

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria'){
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

$aula_curso_id = (int)($_POST['aula_curso_id'] ?? 0);
$titulo        = trim($_POST['titulo'] ?? '');
$numero        = isset($_POST['numero']) && $_POST['numero']!=='' ? (int)$_POST['numero'] : null;
$duracion      = isset($_POST['duracion_dias']) && $_POST['duracion_dias']!=='' ? (int)$_POST['duracion_dias'] : 28;

if ($aula_curso_id<=0 || $titulo===''){
  http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Datos inválidos (aula_curso_id, titulo)']); exit;
}

// Traer curso + sede para validar permiso
$st=$pdo->prepare("
  SELECT ac.curso_id, a.sede_id
  FROM aula_curso ac
  JOIN aula a ON a.id=ac.aula_id
  WHERE ac.id=:ac LIMIT 1
");
$st->execute([':ac'=>$aula_curso_id]);
$ac=$st->fetch();
if(!$ac){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'aula_curso no existe']); exit; }

$uid=(int)$_SESSION['user']['id'];
$ok=$pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
$ok->execute([':u'=>$uid, ':s'=>$ac['sede_id']]);
if(!$ok->fetch()){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permiso para esta sede']); exit; }

$curso_id = (int)$ac['curso_id'];

// Detectar columnas reales de curso_modulo
try {
  $cols = $pdo->query("SHOW COLUMNS FROM curso_modulo")->fetchAll(PDO::FETCH_COLUMN);
} catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Falta tabla curso_modulo']); exit;
}
$hasDur = in_array('duracion_dias', $cols, true);

// Calcular numero si no viene
if ($numero===null){
  $mx=$pdo->prepare("SELECT COALESCE(MAX(numero),0) FROM curso_modulo WHERE curso_id=:c");
  $mx->execute([':c'=>$curso_id]);
  $numero = ((int)$mx->fetchColumn()) + 1;
}

// Evitar duplicado (curso_id + numero)
$dupe=$pdo->prepare("SELECT 1 FROM curso_modulo WHERE curso_id=:c AND numero=:n LIMIT 1");
$dupe->execute([':c'=>$curso_id, ':n'=>$numero]);
if($dupe->fetch()){
  http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'Ya existe un módulo con ese número para el curso']); exit;
}

// Insert
if($hasDur){
  $sql="INSERT INTO curso_modulo (curso_id, numero, titulo, duracion_dias) VALUES (:c,:n,:t,:d)";
  $params=[':c'=>$curso_id, ':n'=>$numero, ':t'=>$titulo, ':d'=>$duracion>0?$duracion:28];
} else {
  $sql="INSERT INTO curso_modulo (curso_id, numero, titulo) VALUES (:c,:n,:t)";
  $params=[':c'=>$curso_id, ':n'=>$numero, ':t'=>$titulo];
}

try{
  $ins=$pdo->prepare($sql);
  $ins->execute($params);
  echo json_encode(['ok'=>true,'msg'=>'Módulo creado','modulo_id'=>(int)$pdo->lastInsertId(),'numero'=>$numero]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Error al crear módulo']);
}
