<?php
// backend/secretaria/cierres_listar.php — lista de cierres por sede/mes/año
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
date_default_timezone_set('America/Lima');
$pdo->exec("SET time_zone='-05:00'");

try{
  $year    = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
  $month   = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n'); // 1..12
  $sede_id = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : 0;

  if ($sede_id<=0){
    echo json_encode(['ok'=>true,'items'=>[]]); exit;
  }
  // requerimos columna sede_id en cash_closure
  $hasSede = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cash_closure' AND COLUMN_NAME='sede_id'")->fetchColumn();
  if(!$hasSede){ echo json_encode(['ok'=>false,'msg'=>"cash_closure.sede_id requerido"]); exit; }

  $first = sprintf('%04d-%02d-01', $year, $month);
  // último día del mes
  $last  = date('Y-m-t', strtotime($first));

  $st = $pdo->prepare("
    SELECT id, fecha, sede_id, opened_at, closed_at, receipts_count,
           total, yape_total, efectivo_total, transferencia_total, otros_total, notes
    FROM cash_closure
    WHERE sede_id = ? AND fecha BETWEEN ? AND ?
    ORDER BY fecha DESC
  ");
  $st->execute([$sede_id, $first, $last]);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'items'=>$items, 'year'=>$year,'month'=>$month,'sede_id'=>$sede_id]);
} catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
