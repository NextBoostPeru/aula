<?php
// backend/secretaria/notificar_usuario.php
session_start();
header('Content-Type: application/json');

require __DIR__ . '/../db.php';

if (!isset($_SESSION['user']['id']) || !in_array($_SESSION['user']['role'], ['secretaria','admin'], true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

$user_id = (int)($_POST['user_id'] ?? 0);
$title   = trim($_POST['title'] ?? '');
$desc    = trim($_POST['desc']  ?? '');
$type    = trim($_POST['type']  ?? 'general');

if ($user_id <= 0 || $title === '' || $desc === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'Parámetros inválidos (user_id, title, desc)']);
  exit;
}

$myId = (int)$_SESSION['user']['id'];

// 1) Verificar que la secretaria tenga acceso a alguna sede del usuario destino
//    (si es admin, se omite esta validación)
if ($_SESSION['user']['role'] === 'secretaria') {
  $sql = "
    SELECT 1
    FROM secretaria_sede ss
    JOIN aula a            ON a.sede_id = ss.sede_id
    JOIN aula_curso ac     ON ac.aula_id = a.id
    JOIN enrollment e      ON e.aula_curso_id = ac.id
    WHERE ss.user_id = :sec AND e.user_id = :usr
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':sec'=>$myId, ':usr'=>$user_id]);
  if (!$st->fetch()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Sin permiso para notificar a este usuario']);
    exit;
  }
}

// 2) Detectar columnas reales de la tabla notification
try {
  $cols = $pdo->query("SHOW COLUMNS FROM notification")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No existe la tabla notification']);
  exit;
}

$hasTitle = in_array('title', $cols, true);
$descCol  = in_array('description', $cols, true) ? 'description'
          : (in_array('body', $cols, true) ? 'body'
          : (in_array('message', $cols, true) ? 'message' : null));
$hasType  = in_array('type', $cols, true);
$hasIsRead= in_array('is_read', $cols, true);
$hasUser  = in_array('user_id', $cols, true);

if (!$hasUser || !$descCol) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'La tabla notification debe tener al menos user_id y una columna de texto (description/body/message)']);
  exit;
}

// 3) Insert dinámico según columnas disponibles
$fields = ['user_id'];
$params = [':user_id'=>$user_id];

if ($hasTitle) { $fields[] = 'title'; $params[':title'] = $title; }
$fields[] = $descCol;     $params[':desc']  = $desc;
if ($hasType)  { $fields[] = 'type';  $params[':type']  = $type; }
if ($hasIsRead){ $fields[] = 'is_read'; $params[':is_read'] = 0; }

$placeholders = [];
foreach ($fields as $f) {
  $placeholders[] = ':'.str_replace(['-'],['_'],$f); // nombres simples
}

// Armar SQL conservando los nombres reales (mapeamos params manualmente)
$sql = "INSERT INTO notification (".implode(',', $fields).") VALUES (";
$vals = [];
foreach ($fields as $f) {
  if ($f === 'user_id')     { $vals[]=':user_id'; }
  elseif ($f === 'title')   { $vals[]=':title'; }
  elseif ($f === $descCol)  { $vals[]=':desc'; }
  elseif ($f === 'type')    { $vals[]=':type'; }
  elseif ($f === 'is_read') { $vals[]=':is_read'; }
}
$sql .= implode(',', $vals) . ")";

try {
  $ins = $pdo->prepare($sql);
  $ins->execute($params);
  echo json_encode(['ok'=>true,'msg'=>'Notificación enviada','id'=>$pdo->lastInsertId()]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al guardar la notificación']);
}
