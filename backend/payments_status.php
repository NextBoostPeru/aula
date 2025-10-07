<?php
// backend/payments_status.php
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
  // Filtro opcional por matrícula (enrollment_id) si quisieras ver solo un curso
  $enrollmentFilter = isset($_GET['enrollment_id']) ? (int)$_GET['enrollment_id'] : 0;

  $sql = "
    SELECT
      pq.id,
      pq.enrollment_id,
      pq.nro,
      pq.due_date,
      pq.amount,
      pq.paid_at,
      c.titulo   AS curso_titulo,
      a.nombre   AS aula_nombre,
      s.nombre   AS sede_nombre
    FROM payment_quota pq
    JOIN enrollment e   ON e.id = pq.enrollment_id
    JOIN aula_curso ac  ON ac.id = e.aula_curso_id
    JOIN curso c        ON c.id = ac.curso_id
    JOIN aula a         ON a.id = ac.aula_id
    JOIN sede s         ON s.id = a.sede_id
    WHERE e.user_id = :uid
    ".($enrollmentFilter ? " AND pq.enrollment_id = :eid " : "")."
    ORDER BY pq.due_date ASC, pq.nro ASC
  ";
  $st = $pdo->prepare($sql);
  $params = [':uid'=>$userId];
  if ($enrollmentFilter) $params[':eid'] = $enrollmentFilter;
  $st->execute($params);

  $today = new DateTimeImmutable('today');
  $cuotas = [];
  while ($r = $st->fetch()) {
    $due = new DateTimeImmutable($r['due_date']);
    $status = 'pendiente';
    if (!empty($r['paid_at'])) {
      $status = 'pagado';
    } else if ($due < $today) {
      $status = 'vencido';
    }
    $cuotas[] = [
      'id'            => (int)$r['id'],
      'enrollment_id' => (int)$r['enrollment_id'],
      'curso'         => $r['curso_titulo'],
      'aula'          => $r['aula_nombre'],
      'sede'          => $r['sede_nombre'],
      'nro'           => (int)$r['nro'],
      'vence_en'      => $r['due_date'],
      'monto'         => (float)$r['amount'],
      'status'        => $status
    ];
  }

  echo json_encode([
    'ok'     => true,
    'cuotas' => $cuotas
    // Puedes incluir otros campos si lo deseas (totales, etc.)
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
