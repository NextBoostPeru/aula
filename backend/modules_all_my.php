<?php
// backend/modules_all_my.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$userId = (int)$_SESSION['user']['id'];

try {
  // Matrículas del alumno con su curso/sede/aula
  $sql = "SELECT
            e.id AS enrollment_id,
            c.id AS curso_id, c.titulo AS curso_titulo,
            s.nombre AS sede_nombre,
            a.nombre AS aula_nombre
          FROM enrollment e
          JOIN aula_curso ac ON ac.id=e.aula_curso_id
          JOIN curso c       ON c.id=ac.curso_id
          JOIN aula a        ON a.id=ac.aula_id
          JOIN sede s        ON s.id=a.sede_id
          WHERE e.user_id=:uid
          ORDER BY e.id DESC";
  $st = $pdo->prepare($sql);
  $st->execute([':uid'=>$userId]);
  $enrs = $st->fetchAll();

  if (!$enrs) { echo json_encode(['ok'=>true,'data'=>[]]); exit; }

  // Módulo activo por matrícula
  $actives = $pdo->query("SELECT enrollment_id, modulo_id FROM alumno_modulo WHERE is_active=1")
                 ->fetchAll(PDO::FETCH_KEY_PAIR);

  // Traer módulos del curso (con materiales)
  $qMods = $pdo->prepare("
    SELECT id, numero, titulo, video_url, pdf_url, slides_url
    FROM curso_modulo
    WHERE curso_id=:cid
    ORDER BY numero ASC
  ");

  $out = [];
  foreach ($enrs as $e) {
    $qMods->execute([':cid'=>$e['curso_id']]);
    $mods = $qMods->fetchAll() ?: [];

    $out[] = [
      'enrollment_id'    => (int)$e['enrollment_id'],
      'curso'            => [
        'id'     => (int)$e['curso_id'],
        'titulo' => $e['curso_titulo'],
        'sede'   => $e['sede_nombre'],
        'aula'   => $e['aula_nombre'],
      ],
      'active_modulo_id' => isset($actives[$e['enrollment_id']]) ? (int)$actives[$e['enrollment_id']] : 0,
      'modulos'          => array_map(function($m){
        return [
          'id'        => (int)$m['id'],
          'numero'    => (int)$m['numero'],
          'titulo'    => $m['titulo'],
          'video_url' => $m['video_url'],
          'pdf_url'   => $m['pdf_url'],
          'slides_url'=> $m['slides_url'],
        ];
      }, $mods)
    ];
  }

  echo json_encode(['ok'=>true,'data'=>$out]);
} catch(Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
