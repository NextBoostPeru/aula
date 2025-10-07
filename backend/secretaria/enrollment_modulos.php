<?php
// backend/secretaria/enrollment_modulos.php  (FIX: ORDER BY cm.numero)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $enrollment_id = (int)($_GET['enrollment_id'] ?? 0);
  if ($enrollment_id <= 0) { echo json_encode(['ok'=>false,'msg'=>'enrollment_id requerido']); exit; }

  // enrollment -> aula_curso -> curso_id
  $st = $pdo->prepare("
    SELECT ac.curso_id
    FROM enrollment e
    JOIN aula_curso ac ON ac.id = e.aula_curso_id
    WHERE e.id = ?
    LIMIT 1
  ");
  $st->execute([$enrollment_id]);
  $curso_id = $st->fetchColumn();
  if ($curso_id === false) { echo json_encode(['ok'=>false,'msg'=>'Curso no encontrado para la matrícula']); exit; }

  // módulos del curso (usa 'numero' y 'titulo' según tu schema)
  $st = $pdo->prepare("
    SELECT cm.id, cm.titulo
    FROM curso_modulo cm
    WHERE cm.curso_id = ?
    ORDER BY cm.numero ASC, cm.id ASC
  ");
  $st->execute([$curso_id]);
  $mods = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'items'=>$mods]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
