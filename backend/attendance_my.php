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
      ac.aula_id AS aula_id,
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
      'start_from_class'=>(int)$r['start_from_class'],
      'estado'=>'activo'
    ];
  }

  // Prepara consulta de asistencias existentes
  $qAtt = $pdo->prepare("
    SELECT class_nro, status, class_date
    FROM attendance
    WHERE enrollment_id=:enr AND modulo_id=:mid
  ");

  $qLastModule = $pdo->prepare("SELECT modulo_id FROM attendance WHERE enrollment_id=:enr ORDER BY marked_at DESC LIMIT 1");
  $qSchedule = $pdo->prepare("
    SELECT class_nro, class_date
    FROM modulo_clase
    WHERE aula_id=:a AND modulo_id=:m
    ORDER BY class_nro ASC
  ");

  $today = new DateTimeImmutable('today');
  $out = [];

  foreach ($enrolls as $e) {
    $enrId = (int)$e['enrollment_id'];
    $moduleInfo = $actives[$enrId] ?? null;
    $moduleId = $moduleInfo['modulo_id'] ?? null;
    $estadoModulo = $moduleInfo['estado'] ?? null;

    if (!$moduleId) {
      $qLastModule->execute([':enr'=>$enrId]);
      $moduleId = (int)$qLastModule->fetchColumn();
      if ($moduleId) {
        $moduleInfo = [
          'modulo_id'=>$moduleId,
          'start_date'=>null,
          'start_from_class'=>1,
          'estado'=>'registrado'
        ];
        $estadoModulo = 'registrado';
      }
    }

    if (!$moduleId) {
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

    $schedule = [];
    $qSchedule->execute([':a'=>(int)$e['aula_id'], ':m'=>$moduleId]);
    while ($row = $qSchedule->fetch(PDO::FETCH_ASSOC)) {
      $schedule[(int)$row['class_nro']] = $row['class_date'];
    }

    $start = $moduleInfo['start_date'] ?? ($schedule[1] ?? null);
    if (!$start) {
      $first = $schedule ? reset($schedule) : null;
      $start = $first ?: null;
    }

    $startFrom = $moduleInfo['start_from_class'] ?? 1;

    $dates = [];
    for ($i=1; $i<=4; $i++) {
      if (isset($schedule[$i])) {
        $dates[$i] = $schedule[$i];
      } elseif ($start) {
        $dates[$i] = addDays($start, ($i-1)*7);
      } else {
        $dates[$i] = null;
      }
    }

    // Cargar asistencias guardadas
    $qAtt->execute([':enr'=>$enrId, ':mid'=>$moduleId]);
    $saved = [];
    while ($r = $qAtt->fetch()) {
      $saved[(int)$r['class_nro']] = ['status'=>$r['status'], 'date'=>$r['class_date']];
    }

    // Construir salida de 4 clases con reglas
    $clases = [];
    for ($i=1; $i<=4; $i++) {
      $dstr = $dates[$i];
      $dObj = $dstr ? new DateTimeImmutable($dstr) : null;
      if ($i < $startFrom) {
        $state = 'no_aplica';
      } else if (isset($saved[$i])) {
        $state = $saved[$i]['status']; // asistio/tarde/falta/justificado
      } else {
        if ($dObj && $dObj <= $today) {
          $state = 'pendiente';
        } else {
          $state = 'programada';
        }
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
        'id'=>$moduleId,
        'start_date'=>$start,
        'start_from_class'=>$startFrom,
        'estado'=>$estadoModulo ?? 'activo'
      ],
      'clases'=>$clases
    ];
  }

  echo json_encode(['ok'=>true,'data'=>$out]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
