<?php
// backend/grades_my.php
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
  // Todas las matrículas del alumno + info de curso/aula/sede
  $st = $pdo->prepare("
    SELECT
      e.id AS enrollment_id,
      ac.id AS aula_curso_id,
      c.id  AS curso_id, c.titulo AS curso_titulo,
      s.nombre AS sede_nombre,
      a.nombre AS aula_nombre
    FROM enrollment e
    JOIN aula_curso ac ON ac.id = e.aula_curso_id
    JOIN curso c       ON c.id = ac.curso_id
    JOIN aula a        ON a.id = ac.aula_id
    JOIN sede s        ON s.id = a.sede_id
    WHERE e.user_id = :uid
    ORDER BY e.id DESC
  ");
  $st->execute([':uid'=>$uid]);
  $enrs = $st->fetchAll();

  if (!$enrs) { echo json_encode(['ok'=>true, 'data'=>[]]); exit; }

  // Preparar consultas
  $qMod  = $pdo->prepare("
    SELECT m.id, m.numero, m.titulo
    FROM curso_modulo m
    JOIN aula_curso ac ON ac.curso_id = m.curso_id
    WHERE ac.id = :acid
    ORDER BY m.numero ASC, m.id ASC
  ");
  $qNote = $pdo->prepare("
    SELECT modulo_id, grade
    FROM module_grade
    WHERE enrollment_id = :enr
  ");

  $data = [];
  foreach ($enrs as $e) {
    $enrollment_id = (int)$e['enrollment_id'];

    // módulos del curso de esa matrícula
    $qMod->execute([':acid'=>$e['aula_curso_id']]);
    $mods = $qMod->fetchAll();

    // notas existentes de esa matrícula (por módulo)
    $qNote->execute([':enr'=>$enrollment_id]);
    $grades = [];
    while ($r = $qNote->fetch()) $grades[(int)$r['modulo_id']] = (float)$r['grade'];

    $modulos = [];
    $sum = 0.0; $n = 0;
    foreach ($mods as $m) {
      $mid = (int)$m['id'];
      $nota = isset($grades[$mid]) ? $grades[$mid] : null;
      if ($nota !== null) { $sum += $nota; $n++; }
      $modulos[] = [
        'id'     => $mid,
        'numero' => (int)$m['numero'],
        'titulo' => $m['titulo'],
        'nota'   => $nota,
        'estado' => ($nota === null ? 'pendiente' : 'calificado')
      ];
    }

    $prom = ($n > 0) ? round($sum / $n, 2) : null; // promedio general del curso (de módulos calificados)

    $data[] = [
      'enrollment_id' => $enrollment_id,
      'curso' => [
        'id'    => (int)$e['curso_id'],
        'titulo'=> $e['curso_titulo'],
        'sede'  => $e['sede_nombre'],
        'aula'  => $e['aula_nombre']
      ],
      'modulos' => $modulos,
      'promedio_curso' => $prom,
      'calificados'    => $n,
      'total_modulos'  => count($mods)
    ];
  }

  echo json_encode(['ok'=>true,'data'=>$data]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
