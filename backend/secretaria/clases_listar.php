<?php
// backend/secretaria/clases_listar.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria') {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
  }

  $aula_id   = (int)($_GET['aula_id'] ?? 0);
  $modulo_id = (int)($_GET['modulo_id'] ?? 0);
  if ($aula_id<=0 || $modulo_id<=0) {
    http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Parámetros inválidos']); exit;
  }

  // Permiso por sede
  $q = $pdo->prepare("SELECT sede_id FROM aula WHERE id=:a LIMIT 1");
  $q->execute([':a'=>$aula_id]);
  $sede_id = $q->fetchColumn();
  if(!$sede_id){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Aula no existe']); exit; }
  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$sede_id]);
  if(!$chk->fetch()){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permiso para esta sede']); exit; }

  $st = $pdo->prepare("SELECT class_nro, class_date
                       FROM modulo_clase
                       WHERE aula_id=:a AND modulo_id=:m
                       ORDER BY class_nro ASC");
  $st->execute([':a'=>$aula_id, ':m'=>$modulo_id]);
  $items = $st->fetchAll();

  echo json_encode(['ok'=>true,'items'=>$items]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al listar','detail'=>$e->getMessage()]);
}
