<?php
// backend/secretaria/caja_cerrar.php  — Cierre DIARIO por SEDE
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Hora Perú
date_default_timezone_set('America/Lima');
$pdo->exec("SET time_zone='-05:00'");

/* Helpers */
function db_name(PDO $pdo): string {
  return (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
}
function column_exists(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1
  ");
  $st->execute([db_name($pdo), $table, $column]);
  return (bool)$st->fetchColumn();
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit;
  }

  $fecha   = $_POST['fecha']   ?? date('Y-m-d');                 // YYYY-MM-DD
  $sede_id = isset($_POST['sede_id']) ? (int)$_POST['sede_id'] : 0;
  $notes   = trim($_POST['notes'] ?? '');
  $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;

  if ($sede_id <= 0) {
    echo json_encode(['ok'=>false,'msg'=>'No hay sede seleccionada']); exit;
  }

  // Validaciones de esquema necesarias para filtrar por sede
  $ac_has_aula_id = column_exists($pdo, 'aula_curso', 'aula_id');
  $aula_has_sede  = column_exists($pdo, 'aula', 'sede_id');
  $ac_has_sede_id = column_exists($pdo, 'aula_curso', 'sede_id');

  if (!($ac_has_aula_id && $aula_has_sede) && !$ac_has_sede_id) {
    echo json_encode(['ok'=>false,'msg'=>'No es posible filtrar por sede con el esquema actual (aula.sede_id o aula_curso.sede_id requerido).']); exit;
  }

  // Para guardar el cierre POR SEDE la tabla cash_closure debe tener sede_id
  if (!column_exists($pdo, 'cash_closure', 'sede_id')) {
    echo json_encode(['ok'=>false,'msg'=>"La tabla cash_closure no tiene columna 'sede_id'. Aplica el patch SQL para cierres por sede."]); exit;
  }

  // Construcción del filtro por sede según tu esquema
  $joinAula  = '';
  $whereSede = '';
  $paramsSel = [$fecha];

  if ($ac_has_aula_id && $aula_has_sede) {
    $joinAula  = "JOIN aula a ON a.id = ac.aula_id";
    $whereSede = " AND a.sede_id = ? ";
    $paramsSel[] = $sede_id;
  } else { // aula_curso.sede_id
    $whereSede = " AND ac.sede_id = ? ";
    $paramsSel[] = $sede_id;
  }

  // Movimientos del día para esa sede
  $sqlMov = "
    SELECT r.id, r.paid_at, r.amount, r.method
    FROM payment_receipt r
    JOIN payment_quota q   ON q.id = r.quota_id
    JOIN enrollment e      ON e.id = q.enrollment_id
    JOIN aula_curso ac     ON ac.id = e.aula_curso_id
    $joinAula
    WHERE DATE(r.paid_at) = ? $whereSede
    ORDER BY r.paid_at ASC, r.id ASC
  ";
  $st = $pdo->prepare($sqlMov);
  $st->execute($paramsSel);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Totales
  $tot = ['total'=>0.0,'efectivo'=>0.0,'yape'=>0.0,'transferencia'=>0.0,'otros'=>0.0,'count'=>0];
  $opened_at = null; $closed_at = null;
  foreach ($rows as $i=>$r) {
    $amt = (float)$r['amount'];
    $met = strtolower(trim((string)$r['method']));
    $tot['total'] += $amt; $tot['count'] += 1;
    if (isset($tot[$met])) $tot[$met] += $amt; else $tot['otros'] += $amt;

    $dt = $r['paid_at']; if ($i===0) $opened_at = $dt; $closed_at = $dt;
  }
  // Si no hubo pagos, igual permitimos cierre con montos 0
  if ($opened_at === null) $opened_at = $fecha.' 00:00:00';
  if ($closed_at === null) $closed_at = $fecha.' 23:59:59';

  $pdo->beginTransaction();

  // UPSERT por (fecha, sede_id)
  $st = $pdo->prepare("SELECT id FROM cash_closure WHERE fecha=? AND sede_id=? LIMIT 1");
  $st->execute([$fecha, $sede_id]);
  $cid = $st->fetchColumn();

  if ($cid) {
    $up = $pdo->prepare("
      UPDATE cash_closure SET
        opened_at = COALESCE(opened_at, ?),
        closed_at = ?,
        total = ?, yape_total=?, efectivo_total=?, transferencia_total=?, otros_total=?,
        receipts_count = ?, notes = ?, user_id = ?
      WHERE id = ?
    ");
    $up->execute([
      $opened_at, $closed_at,
      $tot['total'], $tot['yape'], $tot['efectivo'], $tot['transferencia'], $tot['otros'],
      $tot['count'], ($notes ?: null), $user_id, $cid
    ]);
  } else {
    $ins = $pdo->prepare("
      INSERT INTO cash_closure
        (fecha, sede_id, opened_at, closed_at, total,
         yape_total, efectivo_total, transferencia_total, otros_total,
         receipts_count, notes, user_id)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $ins->execute([
      $fecha, $sede_id, $opened_at, $closed_at, $tot['total'],
      $tot['yape'], $tot['efectivo'], $tot['transferencia'], $tot['otros'],
      $tot['count'], ($notes ?: null), $user_id
    ]);
    $cid = (int)$pdo->lastInsertId();
  }

  // Vincular boletas del día y sede a este cierre
  $updSql = "
    UPDATE payment_receipt r
    JOIN payment_quota q   ON q.id = r.quota_id
    JOIN enrollment e      ON e.id = q.enrollment_id
    JOIN aula_curso ac     ON ac.id = e.aula_curso_id
    ".($ac_has_aula_id && $aula_has_sede ? "JOIN aula a ON a.id = ac.aula_id" : "")."
    SET r.closure_id = ?
    WHERE DATE(r.paid_at) = ?
  ";
  $binds = [$cid, $fecha];
  if ($ac_has_aula_id && $aula_has_sede) {
    $updSql .= " AND a.sede_id = ? ";
    $binds[]  = $sede_id;
  } else {
    $updSql .= " AND ac.sede_id = ? ";
    $binds[]  = $sede_id;
  }
  $upd = $pdo->prepare($updSql);
  $upd->execute($binds);

  $pdo->commit();
  echo json_encode(['ok'=>true,'msg'=>'Cierre generado','closure_id'=>$cid,'totales'=>$tot]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
