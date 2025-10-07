<?php
// backend/secretaria/modulo_editar.php
declare(strict_types=1);
require __DIR__ . '/../_json_bootstrap.php';
require __DIR__ . '/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'secretaria') {
    json_reply(['ok'=>false,'msg'=>'No autorizado'], 403);
  }

  $id           = (int)($_POST['modulo_id'] ?? 0);
  $titulo       = trim((string)($_POST['titulo'] ?? ''));
  $numero_raw   = $_POST['numero'] ?? '';
  $dur_raw      = $_POST['duracion_dias'] ?? '';
  $desc_raw     = $_POST['descripcion'] ?? '';

  if ($id <= 0 || $titulo === '') {
    json_reply(['ok'=>false,'msg'=>'Datos incompletos'], 422);
  }

  // Permiso por sede
  $q = $pdo->prepare("
    SELECT a.sede_id
    FROM curso_modulo m
    JOIN curso c       ON c.id = m.curso_id
    JOIN aula_curso ac ON ac.curso_id = c.id
    JOIN aula a        ON a.id = ac.aula_id
    WHERE m.id = :m
    LIMIT 1
  ");
  $q->execute([':m'=>$id]);
  $sede_id = $q->fetchColumn();
  if (!$sede_id) json_reply(['ok'=>false,'msg'=>'Módulo no existe'], 404);

  $uid = (int)$_SESSION['user']['id'];
  $chk = $pdo->prepare("SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1");
  $chk->execute([':u'=>$uid, ':s'=>$sede_id]);
  if (!$chk->fetch()) json_reply(['ok'=>false,'msg'=>'Sin permiso'], 403);

  // Columnas existentes (evita 1054)
  $colsStmt = $pdo->prepare("
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curso_modulo'
  ");
  $colsStmt->execute();
  $cols   = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  $hasDur = in_array('duracion_dias', $cols, true);
  $hasDes = in_array('descripcion',   $cols, true);
  $hasUp  = in_array('updated_at',    $cols, true);

  $sets = ['titulo = :t'];
  $p    = [':t'=>$titulo, ':id'=>$id];

  if ($numero_raw !== '') { $sets[] = 'numero = :n'; $p[':n'] = (int)$numero_raw; }
  if ($hasDur && $dur_raw !== '') { $sets[] = 'duracion_dias = :d'; $p[':d'] = (int)$dur_raw; }
  if ($hasDes && $desc_raw !== '') { $sets[] = 'descripcion = :ds'; $p[':ds'] = trim((string)$desc_raw); }
  if ($hasUp) { $sets[] = 'updated_at = NOW()'; }

  $sql = "UPDATE curso_modulo SET ".implode(', ', $sets)." WHERE id = :id";
  $ok  = $pdo->prepare($sql)->execute($p);
  if (!$ok) throw new Exception('No se pudo actualizar');

  json_reply(['ok'=>true,'msg'=>'Módulo actualizado']);
} catch (Throwable $e) {
  json_reply(['ok'=>false,'msg'=>'Error al editar','detail'=>$e->getMessage()], 500);
}
