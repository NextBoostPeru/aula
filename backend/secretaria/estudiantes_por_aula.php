<?php
// backend/secretaria/estudiantes_por_aula.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria') {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
  }

  $aula_id = (int)($_GET['aula_id'] ?? 0);
  if ($aula_id <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'aula_id requerido']); exit; }

  // validar permiso por sede
  $st = $pdo->prepare("SELECT sede_id FROM aula WHERE id=:a LIMIT 1");
  $st->execute([':a'=>$aula_id]);
  $sede_id = $st->fetchColumn();
  if(!$sede_id){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Aula no existe']); exit; }

  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$sede_id]);
  if(!$chk->fetch()){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permiso para esta sede']); exit; }

  // IMPORTANTE: solo matrículas ACTIVAS
  $sql = "
    SELECT
      u.id       AS user_id,
      u.name,
      u.dni,
      u.email,
      e.id       AS enrollment_id
    FROM enrollment e
    JOIN aula_curso ac ON ac.id = e.aula_curso_id
    JOIN aula a        ON a.id = ac.aula_id
    JOIN users u       ON u.id = e.user_id
    WHERE a.id = :aula_id
      AND e.status = 'activa'
    ORDER BY u.name ASC
  ";
  $q = $pdo->prepare($sql);
  $q->execute([':aula_id' => $aula_id]);
  $items = $q->fetchAll();

  echo json_encode(['ok'=>true, 'items'=>$items]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al listar','detail'=>$e->getMessage()]);
}
