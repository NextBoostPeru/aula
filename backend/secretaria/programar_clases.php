<?php
// backend/secretaria/programar_clases.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria') {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
  }

  $aula_id   = (int)($_POST['aula_id'] ?? 0);
  $modulo_id = (int)($_POST['modulo_id'] ?? 0);
  $start     = trim($_POST['start_date'] ?? '');

  if ($aula_id<=0 || $modulo_id<=0 || $start==='') {
    http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit;
  }

  // Validar fecha
  $dt = DateTime::createFromFormat('Y-m-d', $start);
  if (!$dt || $dt->format('Y-m-d') !== $start) {
    http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Fecha inválida']); exit;
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

  // Validar que el módulo pertenezca al curso del aula
  $v = $pdo->prepare("
    SELECT 1
    FROM aula_curso ac
    JOIN curso_modulo m ON m.curso_id = ac.curso_id
    WHERE ac.aula_id = :a AND m.id = :m
    LIMIT 1
  ");
  $v->execute([':a'=>$aula_id, ':m'=>$modulo_id]);
  if(!$v->fetch()){ http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'El módulo no pertenece al curso dictado en esta aula']); exit; }

  // Upsert de 4 clases: inicio + 7d * (n-1)
  $ins = $pdo->prepare("
    INSERT INTO modulo_clase (aula_id, modulo_id, class_nro, class_date, updated_at)
    VALUES (:a, :m, :n, :d, NOW())
    ON DUPLICATE KEY UPDATE class_date=VALUES(class_date), updated_at=NOW()
  ");

  $items = [];
  for ($n=1; $n<=4; $n++) {
    $date = (clone $dt)->modify('+' . (7*($n-1)) . ' day')->format('Y-m-d');
    $ins->execute([':a'=>$aula_id, ':m'=>$modulo_id, ':n'=>$n, ':d'=>$date]);
    $items[] = ['class_nro'=>$n, 'class_date'=>$date];
  }

  echo json_encode(['ok'=>true,'msg'=>'Clases programadas','items'=>$items]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al programar','detail'=>$e->getMessage()]);
}
