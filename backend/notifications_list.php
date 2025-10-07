<?php
// backend/notifications_list.php
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
  $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;

  $st = $pdo->prepare("
    SELECT id, type, title, body, created_at, read_at
    FROM notification
    WHERE user_id = :uid
    ORDER BY created_at DESC
    LIMIT {$limit}
  ");
  $st->execute([':uid'=>$uid]);

  $items = [];
  while ($r = $st->fetch()) {
    $items[] = [
      'id'    => (int)$r['id'],
      'type'  => $r['type'],
      'title' => $r['title'],
      'desc'  => $r['body'],
      'date'  => $r['created_at'],
      'read'  => !is_null($r['read_at'])
    ];
  }

  // Contar no leídas (para el dot del bell)
  $cnt = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE user_id=:uid AND read_at IS NULL");
  $cnt->execute([':uid'=>$uid]);
  $unread = (int)$cnt->fetchColumn();

  echo json_encode(['ok'=>true,'items'=>$items,'unread'=>$unread]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
