<?php
declare(strict_types=1);
require __DIR__.'/../_json_bootstrap.php';
require __DIR__.'/../db.php';

try{
  if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role']??'')!=='secretaria') {
    json_reply(['ok'=>false,'msg'=>'No autorizado'], 403);
  }

  $aula_id   = (int)($_GET['aula_id'] ?? 0);
  $modulo_id = (int)($_GET['modulo_id'] ?? 0);
  $class_nro = (int)($_GET['class_nro'] ?? 1);
  if ($aula_id<=0 || $modulo_id<=0 || $class_nro<1 || $class_nro>4) {
    json_reply(['ok'=>false,'msg'=>'Parámetros inválidos'], 422);
  }

  // permiso por sede
  $q = $pdo->prepare("SELECT sede_id FROM aula WHERE id=:a LIMIT 1");
  $q->execute([':a'=>$aula_id]);
  $sede_id = $q->fetchColumn();
  if (!$sede_id) json_reply(['ok'=>false,'msg'=>'Aula no existe'], 404);

  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$sede_id]);
  if (!$chk->fetch()) json_reply(['ok'=>false,'msg'=>'Sin permiso para esta sede'], 403);

  // verificar que el módulo pertenezca a curso dictado en el aula
  $val = $pdo->prepare("
    SELECT 1
    FROM aula_curso ac
    JOIN curso_modulo m ON m.curso_id = ac.curso_id
    WHERE ac.aula_id=:a AND m.id=:m
    LIMIT 1
  ");
  $val->execute([':a'=>$aula_id, ':m'=>$modulo_id]);
  if (!$val->fetch()) json_reply(['ok'=>false,'msg'=>'El módulo no corresponde al curso del aula'], 422);

  // fecha programada (si existe)
  $fd = $pdo->prepare("SELECT class_date FROM modulo_clase WHERE aula_id=:a AND modulo_id=:m AND class_nro=:n LIMIT 1");
  $fd->execute([':a'=>$aula_id, ':m'=>$modulo_id, ':n'=>$class_nro]);
  $class_date = $fd->fetchColumn() ?: null;

  // alumnos activos del curso del módulo en esa aula + estado marcado (si ya existe)
  $sql = "
    SELECT e.id AS enrollment_id, u.name, u.dni,
           (SELECT status FROM attendance at
            WHERE at.enrollment_id=e.id AND at.modulo_id=:mod AND at.class_nro=:nro
            LIMIT 1) AS att_status
    FROM enrollment e
    JOIN aula_curso ac ON ac.id = e.aula_curso_id
    JOIN curso_modulo m ON m.curso_id = ac.curso_id
    JOIN users u ON u.id = e.user_id
    WHERE ac.aula_id=:aula AND m.id=:mod AND e.status='activa'
    ORDER BY u.name ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':aula'=>$aula_id, ':mod'=>$modulo_id, ':nro'=>$class_nro]);

  $items=[];
  while($r=$st->fetch()){
    $items[] = [
      'enrollment_id'=>(int)$r['enrollment_id'],
      'class_date'=>$class_date,
      'status'=>$r['att_status'] ?: null,
      'user'=>['name'=>$r['name'], 'dni'=>$r['dni']]
    ];
  }

  json_reply(['ok'=>true,'items'=>$items,'class_date'=>$class_date]);
}catch(Throwable $e){
  json_reply(['ok'=>false,'msg'=>'Error','detail'=>$e->getMessage()], 500);
}
