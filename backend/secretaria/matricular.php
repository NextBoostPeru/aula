<?php
// backend/secretaria/matricular.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

function enrollment_has_column(PDO $pdo, string $column): bool {
  static $cache = [];
  if (array_key_exists($column, $cache)) {
    return $cache[$column];
  }
  $st = $pdo->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = :col LIMIT 1'
  );
  $st->execute([':tbl' => 'enrollment', ':col' => $column]);
  return $cache[$column] = (bool)$st->fetchColumn();
}

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria') {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
  }

  $user_id       = (int)($_POST['user_id'] ?? 0);
  $aula_curso_id = (int)($_POST['aula_curso_id'] ?? 0);
  if ($user_id<=0 || $aula_curso_id<=0) {
    http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit;
  }

  // validar aula_curso y permiso por sede
  $q = $pdo->prepare("SELECT ac.id, a.sede_id FROM aula_curso ac
                      JOIN aula a ON a.id=ac.aula_id
                      WHERE ac.id=:ac LIMIT 1");
  $q->execute([':ac'=>$aula_curso_id]);
  $ac = $q->fetch();
  if(!$ac){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Aula/curso no existe']); exit; }

  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$ac['sede_id']]);
  if(!$chk->fetch()){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permiso para esta sede']); exit; }

  // ¿existe matrícula previa para el mismo user+aula_curso?
  $sel = $pdo->prepare("SELECT id, status FROM enrollment
                        WHERE user_id=:u AND aula_curso_id=:ac LIMIT 1");
  $sel->execute([':u'=>$user_id, ':ac'=>$aula_curso_id]);
  $ex = $sel->fetch();

  $now = date('Y-m-d H:i:s');
  $hasCreated = enrollment_has_column($pdo, 'created_at');
  $hasUpdated = enrollment_has_column($pdo, 'updated_at');
  $hasEnded   = enrollment_has_column($pdo, 'ended_at');

  if ($ex) {
    if ($ex['status'] === 'activa') {
      echo json_encode(['ok'=>true,'msg'=>'Ya estaba matriculado','enrollment_id'=>(int)$ex['id']]); exit;
    }
    // reactivar historial
    $sql = "UPDATE enrollment SET status='activa'";
    $params = [':id' => $ex['id']];
    if ($hasEnded) {
      $sql .= ", ended_at=NULL";
    }
    if ($hasUpdated) {
      $sql .= ", updated_at=:updated_at";
      $params[':updated_at'] = $now;
    }
    $sql .= " WHERE id=:id";
    $up = $pdo->prepare($sql);
    $up->execute($params);
    echo json_encode(['ok'=>true,'msg'=>'Matrícula reactivada','enrollment_id'=>(int)$ex['id']]); exit;
  }

  // no existe: crear matrícula nueva
  $columns = ['user_id', 'aula_curso_id', 'status'];
  $placeholders = [':user_id', ':aula_curso_id', ':status'];
  $params = [
    ':user_id'       => $user_id,
    ':aula_curso_id' => $aula_curso_id,
    ':status'        => 'activa',
  ];
  if ($hasCreated) {
    $columns[] = 'created_at';
    $placeholders[] = ':created_at';
    $params[':created_at'] = $now;
  }
  if ($hasUpdated) {
    $columns[] = 'updated_at';
    $placeholders[] = ':updated_at';
    $params[':updated_at'] = $now;
  }
  $sql = 'INSERT INTO enrollment ('.implode(', ', $columns).') VALUES ('.implode(', ', $placeholders).')';
  $ins = $pdo->prepare($sql);
  $ins->execute($params);
  $enr_id = (int)$pdo->lastInsertId();

  echo json_encode(['ok'=>true,'msg'=>'Matriculado','enrollment_id'=>$enr_id]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('[matricular] '.$e->getMessage());
  echo json_encode(['ok'=>false,'msg'=>'Error al matricular']);
}
