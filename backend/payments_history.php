<?php
// backend/payments_history.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$userId = (int)$_SESSION['user']['id'];

try {
  // Filtro opcional por matrícula
  $enrollmentFilter = isset($_GET['enrollment_id']) ? (int)$_GET['enrollment_id'] : 0;

  $sql = "
    SELECT
      pq.id,
      pq.enrollment_id,
      pq.nro,
      pq.amount,
      pq.method,
      pq.ref,
      pq.paid_at,
      c.titulo   AS curso_titulo
    FROM payment_quota pq
    JOIN enrollment e   ON e.id = pq.enrollment_id
    JOIN aula_curso ac  ON ac.id = e.aula_curso_id
    JOIN curso c        ON c.id = ac.curso_id
    WHERE e.user_id = :uid
      AND pq.paid_at IS NOT NULL
      ".($enrollmentFilter ? " AND pq.enrollment_id = :eid " : "")."
    ORDER BY pq.paid_at DESC
  ";
  $st = $pdo->prepare($sql);
  $params = [':uid'=>$userId];
  if ($enrollmentFilter) $params[':eid'] = $enrollmentFilter;
  $st->execute($params);

  $history = [];
  while ($r = $st->fetch()) {
    $history[] = [
      'fecha'     => substr($r['paid_at'], 0, 10),
      'monto'     => (float)$r['amount'],
      'metodo'    => $r['method'] ?: '—',
      'ref'       => $r['ref'] ?: ('CUOTA#'.$r['nro']),
      'curso'     => $r['curso_titulo'],
      'cuota_nro' => (int)$r['nro']
    ];
  }

  echo json_encode([
    'ok'      => true,
    'history' => $history
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
