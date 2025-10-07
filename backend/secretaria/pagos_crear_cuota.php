<?php
// backend/secretaria/pagos_crear_cuota.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit; }

  $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
  $nro           = (int)($_POST['nro'] ?? 0);
  $due_date      = $_POST['due_date'] ?? null;
  $amount        = (float)($_POST['amount'] ?? 0);

  if ($enrollment_id<=0 || $nro<=0 || !$due_date || $amount<=0) {
    echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit;
  }

  // Evitar duplicado (# de cuota) en la misma matrícula
  $st = $pdo->prepare("SELECT 1 FROM payment_quota WHERE enrollment_id=? AND (cuota_no=? OR nro=?)");
  $st->execute([$enrollment_id, $nro, $nro]);
  if ($st->fetchColumn()) { echo json_encode(['ok'=>false,'msg'=>'Ya existe una cuota con ese número']); exit; }

  // Insertar (id es AUTO_INCREMENT por el parche SQL)
  $ins = $pdo->prepare("
    INSERT INTO payment_quota (enrollment_id, cuota_no, nro, due_date, amount_due, amount_paid, status)
    VALUES (?,?,?,?,?,0,'pending')
  ");
  $ins->execute([$enrollment_id, $nro, $nro, $due_date, $amount]);

  echo json_encode(['ok'=>true,'msg'=>'Cuota creada']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
