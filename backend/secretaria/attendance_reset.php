<?php
declare(strict_types=1);
require __DIR__.'/../_json_bootstrap.php';
require __DIR__.'/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'secretaria') {
    json_reply(['ok' => false, 'msg' => 'No autorizado'], 403);
  }

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_reply(['ok' => false, 'msg' => 'Método no permitido'], 405);
  }

  $aula_id   = (int)($_POST['aula_id'] ?? 0);
  $modulo_id = (int)($_POST['modulo_id'] ?? 0);
  $class_nro = (int)($_POST['class_nro'] ?? 0);

  if ($aula_id <= 0 || $modulo_id <= 0 || $class_nro < 1 || $class_nro > 4) {
    json_reply(['ok' => false, 'msg' => 'Parámetros inválidos'], 422);
  }

  $q = $pdo->prepare('SELECT sede_id FROM aula WHERE id = :a LIMIT 1');
  $q->execute([':a' => $aula_id]);
  $sede_id = $q->fetchColumn();
  if (!$sede_id) {
    json_reply(['ok' => false, 'msg' => 'Aula no encontrada'], 404);
  }

  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare('SELECT 1 FROM secretaria_sede WHERE user_id = :u AND sede_id = :s LIMIT 1');
  $chk->execute([':u' => $uid, ':s' => $sede_id]);
  if (!$chk->fetch()) {
    json_reply(['ok' => false, 'msg' => 'Sin permiso para esta sede'], 403);
  }

  $val = $pdo->prepare('
    SELECT 1
    FROM aula_curso ac
    JOIN curso_modulo m ON m.curso_id = ac.curso_id
    WHERE ac.aula_id = :a AND m.id = :m
    LIMIT 1
  ');
  $val->execute([':a' => $aula_id, ':m' => $modulo_id]);
  if (!$val->fetch()) {
    json_reply(['ok' => false, 'msg' => 'El módulo no corresponde al curso del aula'], 422);
  }

  $del = $pdo->prepare('
    DELETE at
    FROM attendance at
    JOIN enrollment e ON e.id = at.enrollment_id
    JOIN aula_curso ac ON ac.id = e.aula_curso_id
    WHERE ac.aula_id = :aula AND at.modulo_id = :mod AND at.class_nro = :nro
  ');
  $del->execute([':aula' => $aula_id, ':mod' => $modulo_id, ':nro' => $class_nro]);

  json_reply([
    'ok' => true,
    'msg' => 'Asistencia reseteada',
    'removed' => $del->rowCount(),
  ]);
} catch (Throwable $e) {
  json_reply(['ok' => false, 'msg' => 'Error al resetear', 'detail' => $e->getMessage()], 500);
}
