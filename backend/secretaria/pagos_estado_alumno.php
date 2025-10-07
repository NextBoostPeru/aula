<?php
// backend/secretaria/pagos_estado_alumno.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $enrollment_id = (int)($_GET['enrollment_id'] ?? 0);
  if ($enrollment_id <= 0) {
    echo json_encode(['ok'=>false,'msg'=>'enrollment_id requerido']); exit;
  }

  // Curso: enrollment -> aula_curso -> curso.titulo
  $sqlCurso = "
    SELECT c.titulo AS curso
    FROM enrollment e
    JOIN aula_curso ac ON ac.id = e.aula_curso_id
    JOIN curso c ON c.id = ac.curso_id
    WHERE e.id = ?
    LIMIT 1
  ";
  $st = $pdo->prepare($sqlCurso);
  $st->execute([$enrollment_id]);
  $curso = $st->fetchColumn();
  if ($curso === false) $curso = '-';

  // Cuotas con SALDO
  $sqlCuotas = "
    SELECT
      q.id,
      COALESCE(NULLIF(q.cuota_no,0), q.nro)                                     AS nro,
      q.due_date                                                                 AS vence_en,
      CASE WHEN q.amount_due>0 THEN q.amount_due ELSE q.amount END               AS monto,
      q.amount_paid,
      (CASE WHEN q.amount_due>0 THEN q.amount_due ELSE q.amount END) - q.amount_paid AS saldo,
      CASE
        WHEN q.status='paid'     THEN 'pagado'
        WHEN q.status='overdue'  THEN 'vencido'
        ELSE 'pendiente'
      END AS status
    FROM payment_quota q
    WHERE q.enrollment_id = ?
    ORDER BY q.cuota_no, q.nro
  ";
  $st = $pdo->prepare($sqlCuotas);
  $st->execute([$enrollment_id]);
  $cuotas = $st->fetchAll(PDO::FETCH_ASSOC);

  // Historial (boletas)
  $sqlHist = "
    SELECT
      r.paid_at   AS fecha,
      r.amount    AS monto,
      r.method    AS metodo,
      r.reference AS ref
    FROM payment_receipt r
    JOIN payment_quota q ON q.id = r.quota_id
    WHERE q.enrollment_id = ?
    ORDER BY r.paid_at DESC, r.id DESC
  ";
  $st = $pdo->prepare($sqlHist);
  $st->execute([$enrollment_id]);
  $hist = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'curso'=>$curso,'cuotas'=>$cuotas,'historial'=>$hist]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
