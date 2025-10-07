<?php
declare(strict_types=1);
require __DIR__.'/../_json_bootstrap.php';
require __DIR__.'/../db.php';

try{
  if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role']??'')!=='secretaria') {
    json_reply(['ok'=>false,'msg'=>'No autorizado'], 403);
  }

  if ($_SERVER['REQUEST_METHOD']!=='POST') {
    json_reply(['ok'=>false,'msg'=>'Método no permitido'], 405);
  }

  $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
  $modulo_id     = (int)($_POST['modulo_id'] ?? 0);
  $class_nro     = (int)($_POST['class_nro'] ?? 0);
  $status        = trim((string)($_POST['status'] ?? ''));

  if ($enrollment_id<=0 || $modulo_id<=0 || $class_nro<1 || $class_nro>4) {
    json_reply(['ok'=>false,'msg'=>'Parámetros inválidos'], 422);
  }
  $valid = ['asistio','tarde','falta','justificado'];
  if (!in_array($status, $valid, true)) {
    json_reply(['ok'=>false,'msg'=>'Estado inválido'], 422);
  }

  // Obtener aula_id y sede de la matrícula y validar pertenencia al módulo
  $q = $pdo->prepare("
    SELECT a.id AS aula_id, a.sede_id, ac.curso_id, e.status AS enr_status
    FROM enrollment e
    JOIN aula_curso ac ON ac.id = e.aula_curso_id
    JOIN aula a ON a.id = ac.aula_id
    WHERE e.id = :enr
    LIMIT 1
  ");
  $q->execute([':enr'=>$enrollment_id]);
  $row = $q->fetch();
  if (!$row) json_reply(['ok'=>false,'msg'=>'Matrícula no encontrada'], 404);
  if ($row['enr_status']!=='activa') json_reply(['ok'=>false,'msg'=>'La matrícula no está activa'], 422);

  // permiso sede
  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$row['sede_id']]);
  if (!$chk->fetch()) json_reply(['ok'=>false,'msg'=>'Sin permiso para esta sede'], 403);

  // módulo debe pertenecer al curso de la matrícula
  $v = $pdo->prepare("SELECT 1 FROM curso_modulo WHERE id=:m AND curso_id=:c LIMIT 1");
  $v->execute([':m'=>$modulo_id, ':c'=>$row['curso_id']]);
  if (!$v->fetch()) json_reply(['ok'=>false,'msg'=>'El módulo no corresponde a este curso'], 422);

  // UPSERT asistencia
  $ins = $pdo->prepare("
    INSERT INTO attendance (enrollment_id, modulo_id, class_nro, status, marked_at)
    VALUES (:e,:m,:n,:s,NOW())
    ON DUPLICATE KEY UPDATE status=VALUES(status), marked_at=NOW()
  ");
  $ins->execute([':e'=>$enrollment_id, ':m'=>$modulo_id, ':n'=>$class_nro, ':s'=>$status]);

  json_reply(['ok'=>true,'msg'=>'Asistencia marcada']);
}catch(Throwable $e){
  json_reply(['ok'=>false,'msg'=>'Error al marcar','detail'=>$e->getMessage()], 500);
}
