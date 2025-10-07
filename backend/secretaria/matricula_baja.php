<?php
// backend/secretaria/matricula_baja.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria') {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
  }

  $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
  if ($enrollment_id<=0) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'enrollment_id requerido']); exit; }

  // Validar que la secretaria tenga permiso sobre la sede del aula
  $q = $pdo->prepare("SELECT a.sede_id
                      FROM enrollment e
                      JOIN aula_curso ac ON ac.id = e.aula_curso_id
                      JOIN aula a ON a.id = ac.aula_id
                      WHERE e.id=:enr LIMIT 1");
  $q->execute([':enr'=>$enrollment_id]);
  $sede_id = $q->fetchColumn();
  if(!$sede_id){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Matrícula no encontrada']); exit; }

  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$sede_id]);
  if(!$chk->fetch()){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permiso para esta sede']); exit; }

  // Marcar baja (historial se conserva)
  $st = $pdo->prepare("UPDATE enrollment SET status='baja', ended_at=NOW() WHERE id=:enr AND status='activa'");
  $st->execute([':enr'=>$enrollment_id]);

  echo json_encode(['ok'=>true,'msg'=>'Alumno retirado del aula']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al dar de baja','detail'=>$e->getMessage()]);
}
