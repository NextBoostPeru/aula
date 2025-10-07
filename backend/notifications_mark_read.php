<?php
// backend/notifications_mark_read.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$uid = (int)$_SESSION['user']['id'];

try {
  $all = isset($_POST['all']) ? (int)$_POST['all'] : 0;
  if ($all === 1) {
    $st = $pdo->prepare("UPDATE notification SET read_at=NOW() WHERE user_id=:uid AND read_at IS NULL");
    $st->execute([':uid'=>$uid]);
    echo json_encode(['ok'=>true,'msg'=>'Todas marcadas como leídas']);
    exit;
  }

  // ids[]=1&ids[]=2...
  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids) || !count($ids)) {
    echo json_encode(['ok'=>false,'msg'=>'Sin IDs']); exit;
  }
  // Sanitizar y construir placeholders
  $ids = array_values(array_unique(array_map('intval', $ids)));
  $in = implode(',', array_fill(0, count($ids), '?'));

  $sql = "UPDATE notification SET read_at=NOW() WHERE user_id=? AND id IN ($in)";
  $params = array_merge([$uid], $ids);
  $st = $pdo->prepare($sql);
  $st->execute($params);

  echo json_encode(['ok'=>true,'msg'=>'Notificaciones marcadas']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
