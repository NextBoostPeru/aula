<?php
// backend/attendance_my.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$uid = (int)$_SESSION['user']['id'];

function addDays($date, $days) {
  $d = new DateTimeImmutable($date);
  return $d->modify("+{$days} day")->format('Y-m-d');
}

try {
  // Traer matrículas del alumno con su curso/aula/sede
  $st = $pdo->prepare("
    SELECT
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
    ORDER BY e.id DESC
  ");
  $st->execute([':uid'=>$uid]);
  $enrolls = $st->fetchAll();

  if (!$enrolls) { echo json_encode(['ok'=>true,'data'=>[]]); exit; }

  // Módulo activo por matrícula
  $qActive = $pdo->query("SELECT enrollment_id, modulo_id, start_date, start_from_class FROM alumno_modulo WHERE is_active=1");
  $activeRows = $qActive->fetchAll(PDO::FETCH_ASSOC);
  $actives = [];
  foreach ($activeRows as $r) {
    $actives[(int)$r['enrollment_id']] = [
      'modulo_id'=>(int)$r['modulo_id'],
      'start_date'=>$r['start_date'],
      'start_from_class'=>(int)$r['start_from_class']
    ];
  }

  // Prepara consulta de asistencias existentes
  $qAtt = $pdo->prepare("
    SELECT class_nro, status, class_date
    FROM attendance
    WHERE enrollment_id=:enr AND modulo_id=:mid
  ");

  $today = new DateTimeImmutable('today');
  $out = [];

  foreach ($enrolls as $e) {
    $enrId = (int)$e['enrollment_id'];
    if (!isset($actives[$enrId])) {
      // Sin módulo activo: devolvemos estructura vacía para la vista
      $out[] = [
        'enrollment_id'=>$enrId,
        'curso'=>[
          'id'=>(int)$e['curso_id'],
          'titulo'=>$e['curso_titulo'],
          'sede'=>$e['sede_nombre'],
          'aula'=>$e['aula_nombre'],
        ],
        'modulo'=>null,
        'clases'=>[]
      ];
      continue;
    }

    $m = $actives[$enrId];
    $start = $m['start_date'];
    $dates = [
      1 => $start,
      2 => addDays($start, 7),
      3 => addDays($start, 14),
      4 => addDays($start, 21),
    ];

    // Cargar asistencias guardadas
    $qAtt->execute([':enr'=>$enrId, ':mid'=>$m['modulo_id']]);
    $saved = [];
    while ($r = $qAtt->fetch()) {
      $saved[(int)$r['class_nro']] = ['status'=>$r['status'], 'date'=>$r['class_date']];
    }

    // Construir salida de 4 clases con reglas
    $clases = [];
    for ($i=1; $i<=4; $i++) {
      $dstr = $dates[$i];
      $d = new DateTimeImmutable($dstr);
      if ($i < $m['start_from_class']) {
        $state = 'no_aplica';
      } else if (isset($saved[$i])) {
        $state = $saved[$i]['status']; // asistio/tarde/falta/justificado
      } else {
        $state = ($d <= $today) ? 'pendiente' : 'programada';
      }
      $clases[] = [
        'nro'=>$i,
        'date'=>$dstr,
        'status'=>$state
      ];
    }

    $out[] = [
      'enrollment_id'=>$enrId,
      'curso'=>[
        'id'=>(int)$e['curso_id'],
        'titulo'=>$e['curso_titulo'],
        'sede'=>$e['sede_nombre'],
        'aula'=>$e['aula_nombre'],
      ],
      'modulo'=>[
        'id'=>$m['modulo_id'],
        'start_date'=>$start,
        'start_from_class'=>$m['start_from_class']
      ],
      'clases'=>$clases
    ];
  }

  echo json_encode(['ok'=>true,'data'=>$out]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
