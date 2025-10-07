<?php
// backend/secretaria/notificar_aula.php
session_start();
header('Content-Type: application/json');

require __DIR__ . '/../db.php';

if (!isset($_SESSION['user']['id']) || !in_array($_SESSION['user']['role'], ['secretaria','admin'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

$aula_id = (int)($_POST['aula_id'] ?? 0);
$title   = trim($_POST['title'] ?? '');
$desc    = trim($_POST['desc']  ?? '');
$type    = trim($_POST['type']  ?? 'general');

if ($aula_id <= 0 || $title === '' || $desc === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'Parámetros inválidos (aula_id, title, desc)']);
  exit;
}

$myId = (int)$_SESSION['user']['id'];

// 1) Validar permiso de la secretaria sobre la sede de ese aula (admins omiten)
if ($_SESSION['user']['role'] === 'secretaria') {
  $sql = "
    SELECT 1
    FROM aula a
    JOIN secretaria_sede ss ON ss.sede_id = a.sede_id AND ss.user_id = :sec
    WHERE a.id = :a
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':sec'=>$myId, ':a'=>$aula_id]);
  if (!$st->fetch()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Sin permiso para notificar a este aula']);
    exit;
  }
}

// 2) Obtener alumnos (user_id) del aula (DISTINCT por si hay más de un curso)
$st = $pdo->prepare("
  SELECT DISTINCT e.user_id
  FROM aula_curso ac
  JOIN enrollment e ON e.aula_curso_id = ac.id
  WHERE ac.aula_id = :a
");
$st->execute([':a'=>$aula_id]);
$userIds = $st->fetchAll(PDO::FETCH_COLUMN);
if (!$userIds) {
  echo json_encode(['ok'=>true,'msg'=>'Aula sin alumnos','count'=>0]);
  exit;
}

// 3) Detectar columnas reales de la tabla notification (tolerante a esquemas)
try {
  $cols = $pdo->query("SHOW COLUMNS FROM notification")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No existe la tabla notification']);
  exit;
}

$hasTitle  = in_array('title', $cols, true);
$descCol   = in_array('description', $cols, true) ? 'description'
           : (in_array('body', $cols, true) ? 'body'
           : (in_array('message', $cols, true) ? 'message' : null));
$hasType   = in_array('type', $cols, true);
$hasIsRead = in_array('is_read', $cols, true);
$hasUser   = in_array('user_id', $cols, true);
$hasAulaId = in_array('aula_id', $cols, true); // opcional, si tu tabla lo tiene

if (!$hasUser || !$descCol) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'La tabla notification debe tener al menos user_id y una columna de texto (description/body/message)']);
  exit;
}

// 4) Insert masivo en transacción
$fields = ['user_id'];
if ($hasTitle)  $fields[] = 'title';
$fields[] = $descCol;
if ($hasType)   $fields[] = 'type';
if ($hasIsRead) $fields[] = 'is_read';
if ($hasAulaId) $fields[] = 'aula_id'; // útil para filtrar después

$placeholders = '(' . implode(',', array_map(function($f){
  // map a nombres de parámetros estables
  if ($f==='user_id')  return ':user_id';
  if ($f==='title')    return ':title';
  if ($f==='type')     return ':type';
  if ($f==='is_read')  return ':is_read';
  if ($f==='aula_id')  return ':aula_id';
  // columna de descripción detectada
  return ':desc';
}, $fields)) . ')';

$sql = "INSERT INTO notification (".implode(',', $fields).") VALUES $placeholders";

try {
  $pdo->beginTransaction();

  $ins = $pdo->prepare($sql);

  $inserted = 0;
  foreach ($userIds as $uid) {
    $params = [
      ':user_id' => (int)$uid,
      ':title'   => $title,
      ':desc'    => $desc,
      ':type'    => $type,
      ':is_read' => 0,
      ':aula_id' => $aula_id,
    ];

    // limpiar params no existentes según columnas
    if (!$hasTitle)  unset($params[':title']);
    if (!$hasType)   unset($params[':type']);
    if (!$hasIsRead) unset($params[':is_read']);
    if (!$hasAulaId) unset($params[':aula_id']);

    $ins->execute($params);
    $inserted += (int)$ins->rowCount();
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'msg'=>'Notificaciones enviadas','count'=>$inserted]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al enviar notificaciones']);
}
