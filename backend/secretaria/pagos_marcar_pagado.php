<?php
// backend/secretaria/pagos_marcar_pagado.php  (con fecha del pago)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Asegura TZ de Perú para PHP
date_default_timezone_set('America/Lima');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit; }

  $quota_id        = (int)($_POST['quota_id'] ?? 0);
  $metodo          = trim($_POST['metodo'] ?? 'efectivo');
  $ref             = trim($_POST['ref'] ?? '');
  $curso_modulo_id = (int)($_POST['curso_modulo_id'] ?? 0);
  $amount          = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
  $paid_at_raw     = trim($_POST['paid_at'] ?? ''); // viene como "YYYY-MM-DDTHH:MM"

  if ($quota_id<=0) { echo json_encode(['ok'=>false,'msg'=>'quota_id requerido']); exit; }
  if (!in_array($metodo, ['yape','efectivo','transferencia','otros'], true)) {
    echo json_encode(['ok'=>false,'msg'=>'Método inválido']); exit;
  }
  if ($curso_modulo_id<=0) { echo json_encode(['ok'=>false,'msg'=>'Selecciona el módulo']); exit; }
  if ($amount<=0) { echo json_encode(['ok'=>false,'msg'=>'Ingresa un monto válido']); exit; }

  // Normalizar paid_at (Perú)
  if ($paid_at_raw !== '') {
    // Acepta datetime-local o solo fecha
    $paid_at_raw = str_replace('T', ' ', $paid_at_raw);
    $dt = new DateTime($paid_at_raw, new DateTimeZone('America/Lima'));
  } else {
    $dt = new DateTime('now', new DateTimeZone('America/Lima'));
  }
  $paid_at = $dt->format('Y-m-d H:i:s');

  // Traer cuota y curso
  $st = $pdo->prepare("
    SELECT q.cuota_no, q.amount_due, q.amount_paid, q.amount, q.due_date, e.aula_curso_id
    FROM payment_quota q
    JOIN enrollment e ON e.id = q.enrollment_id
    WHERE q.id = ?
  ");
  $st->execute([$quota_id]);
  $qrow = $st->fetch(PDO::FETCH_ASSOC);
  if (!$qrow) { echo json_encode(['ok'=>false,'msg'=>'Cuota no encontrada']); exit; }

  $st = $pdo->prepare("SELECT curso_id FROM aula_curso WHERE id = ?");
  $st->execute([(int)$qrow['aula_curso_id']]);
  $curso_id = $st->fetchColumn();
  if ($curso_id === false) { echo json_encode(['ok'=>false,'msg'=>'Curso no encontrado']); exit; }

  // Validar que el módulo pertenece al curso
  $st = $pdo->prepare("SELECT 1 FROM curso_modulo WHERE id=? AND curso_id=?");
  $st->execute([$curso_modulo_id, $curso_id]);
  if (!$st->fetchColumn()) { echo json_encode(['ok'=>false,'msg'=>'El módulo no pertenece al curso']); exit; }

  // SALDO
  $base  = ((float)$qrow['amount_due'] > 0) ? (float)$qrow['amount_due'] : (float)$qrow['amount'];
  $saldo = $base - (float)$qrow['amount_paid'];
  if ($amount > $saldo) { echo json_encode(['ok'=>false,'msg'=>"El monto excede el saldo (S/ ".number_format($saldo,2).")"]); exit; }

  // Zona horaria MySQL (Perú) para que no desplace la fecha
  $pdo->exec("SET time_zone='-05:00'");

  $pdo->beginTransaction();

  // Inserta boleta con fecha elegida
  $ins = $pdo->prepare("
    INSERT INTO payment_receipt (quota_id, curso_modulo_id, amount, method, reference, paid_at)
    VALUES (?,?,?,?,?, ?)
  ");
  $ins->execute([$quota_id, $curso_modulo_id, $amount, $metodo, $ref ?: null, $paid_at]);
  $rid = (int)$pdo->lastInsertId();

  // Generar referencia si aplica
  if ($ref==='') {
    $code = sprintf('B-%s-%d-%d', (new DateTime($paid_at))->format('Ymd'), (int)$qrow['cuota_no'], $rid);
    $pdo->prepare("UPDATE payment_receipt SET reference=? WHERE id=?")->execute([$code, $rid]);
  }

  // Actualizar cuota
  $pdo->prepare("UPDATE payment_quota SET amount_paid = amount_paid + :m WHERE id=:id")
      ->execute([':m'=>$amount, ':id'=>$quota_id]);

  // Recalcular estado
  $st = $pdo->prepare("SELECT amount_due, amount_paid, amount, due_date FROM payment_quota WHERE id=?");
  $st->execute([$quota_id]); $qq=$st->fetch(PDO::FETCH_ASSOC);
  $base2 = ((float)$qq['amount_due'] > 0) ? (float)$qq['amount_due'] : (float)$qq['amount'];
  $status = 'pending';
  if ((float)$qq['amount_paid'] >= $base2) $status = 'paid';
  elseif ((float)$qq['amount_paid'] > 0)   $status = 'partial';
  elseif (new DateTime($qq['due_date']) < new DateTime('today', new DateTimeZone('America/Lima'))) $status='overdue';

  $pdo->prepare("UPDATE payment_quota SET status=?, paid_at=CASE WHEN ?='paid' THEN ? ELSE paid_at END WHERE id=?")
      ->execute([$status,$status,$paid_at,$quota_id]);

  $pdo->commit();
  echo json_encode(['ok'=>true,'msg'=>'Pago registrado']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
