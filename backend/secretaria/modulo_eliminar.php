<?php
// backend/secretaria/modulo_eliminar.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'secretaria') {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
  }

  $id = (int)($_POST['modulo_id'] ?? 0);
  if ($id <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

  // validar permiso
  $q = $pdo->prepare("
    SELECT a.sede_id 
    FROM curso_modulo m
    JOIN curso c ON c.id = m.curso_id
    JOIN aula_curso ac ON ac.curso_id = c.id
    JOIN aula a ON a.id = ac.aula_id
    WHERE m.id = :m LIMIT 1
  ");
  $q->execute([':m'=>$id]);
  $sede_id = $q->fetchColumn();
  if (!$sede_id) { http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Módulo no existe']); exit; }

  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$sede_id]);
  if (!$chk->fetch()) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permiso']); exit; }

  // eliminar (esto cascada borra las fechas programadas si tienes FK en modulo_clase)
  $pdo->prepare("DELETE FROM curso_modulo WHERE id=:id")->execute([':id'=>$id]);

  echo json_encode(['ok'=>true,'msg'=>'Módulo eliminado']);
} catch(Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al eliminar','detail'=>$e->getMessage()]);
}
