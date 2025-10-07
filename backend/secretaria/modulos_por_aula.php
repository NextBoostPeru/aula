<?php
// backend/secretaria/modulos_por_aula.php
declare(strict_types=1);
require __DIR__ . '/../_json_bootstrap.php';
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '')!=='secretaria') {
    json_reply(['ok'=>false,'msg'=>'No autorizado'], 403);
  }
  $aula_id = (int)($_GET['aula_id'] ?? 0);
  if ($aula_id<=0) json_reply(['ok'=>false,'msg'=>'aula_id requerido'], 422);

  $s = $pdo->prepare("SELECT sede_id FROM aula WHERE id=:a LIMIT 1");
  $s->execute([':a'=>$aula_id]);
  $sede_id = $s->fetchColumn();
  if(!$sede_id) json_reply(['ok'=>false,'msg'=>'Aula no existe'], 404);

  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$sede_id]);
  if(!$chk->fetch()) json_reply(['ok'=>false,'msg'=>'Sin permiso'], 403);

  $colsStmt = $pdo->prepare("
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curso_modulo'
  ");
  $colsStmt->execute();
  $cols = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  $selDur  = in_array('duracion_dias',$cols,true) ? ', m.duracion_dias' : ', NULL AS duracion_dias';
  $selDesc = in_array('descripcion',$cols,true)   ? ', m.descripcion'   : ', NULL AS descripcion';

  $sql = "
    SELECT
      ac.id         AS aula_curso_id,
      c.id          AS curso_id,
      c.titulo,
      m.id          AS modulo_id,
      m.numero,
      m.titulo      AS modulo_titulo
      $selDur
      $selDesc
    FROM aula_curso ac
    JOIN curso c        ON c.id = ac.curso_id
    LEFT JOIN curso_modulo m ON m.curso_id = c.id
    WHERE ac.aula_id = :a
    ORDER BY c.titulo ASC, m.numero ASC
  ";
  $q = $pdo->prepare($sql);
  $q->execute([':a'=>$aula_id]);
  $items = $q->fetchAll();

  json_reply(['ok'=>true,'items'=>$items]);
} catch (Throwable $e) {
  json_reply(['ok'=>false,'msg'=>'Error al listar','detail'=>$e->getMessage()], 500);
}
