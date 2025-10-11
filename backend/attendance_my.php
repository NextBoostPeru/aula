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

  // Prepara consultas reutilizables
  $qAtt = $pdo->prepare("
    SELECT class_nro, status, class_date, marked_at
    FROM attendance
    WHERE enrollment_id=:enr AND modulo_id=:mid
    ORDER BY class_nro ASC
  ");
  $qMarkedModules = $pdo->prepare("
    SELECT modulo_id, MAX(marked_at) AS last_marked
    FROM attendance
    WHERE enrollment_id=:enr
    GROUP BY modulo_id
  ");
  $qSchedule = $pdo->prepare("
    SELECT class_nro, class_date
    FROM modulo_clase
    WHERE aula_id=:a AND modulo_id=:m
    ORDER BY class_nro ASC
  ");
  $qModuloMeta = $pdo->prepare("SELECT numero, titulo FROM curso_modulo WHERE id=:mid");
  $moduleMetaCache = [];

  $today = new DateTimeImmutable('today');
  $out = [];

  foreach ($enrolls as $e) {
    $enrId = (int)$e['enrollment_id'];
    $moduleInfo = $actives[$enrId] ?? null;
    $modules = [];

    if ($moduleInfo && !empty($moduleInfo['modulo_id'])) {
      $modId = (int)$moduleInfo['modulo_id'];
      if ($modId) {
        $modules[$modId] = [
          'modulo_id'=>$modId,
          'start_date'=>$moduleInfo['start_date'] ?? null,
          'start_from_class'=>$moduleInfo['start_from_class'] ?? 1,
          'estado'=>$moduleInfo['estado'] ?? 'activo',
          'sort_last_marked'=>null,
        ];
      }
    }

    $qMarkedModules->execute([':enr'=>$enrId]);
    while ($row = $qMarkedModules->fetch(PDO::FETCH_ASSOC)) {
      $modId = (int)$row['modulo_id'];
      if (!$modId) { continue; }
      if (!isset($modules[$modId])) {
        $modules[$modId] = [
          'modulo_id'=>$modId,
          'start_date'=>null,
          'start_from_class'=>1,
          'estado'=>'registrado',
          'sort_last_marked'=>$row['last_marked'] ?? null,
        ];
      } else {
        $modules[$modId]['sort_last_marked'] = $row['last_marked'] ?? $modules[$modId]['sort_last_marked'];
        if ($modules[$modId]['estado'] !== 'activo') {
          $modules[$modId]['estado'] = 'registrado';
        }
      }
    }

    if (!$modules) {
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

    $modulesList = array_values($modules);
    usort($modulesList, function ($a, $b) {
      $aState = $a['estado'] ?? '';
      $bState = $b['estado'] ?? '';
      if ($aState === 'activo' && $bState !== 'activo') { return -1; }
      if ($bState === 'activo' && $aState !== 'activo') { return 1; }
      $aTime = !empty($a['sort_last_marked']) ? strtotime($a['sort_last_marked']) : 0;
      $bTime = !empty($b['sort_last_marked']) ? strtotime($b['sort_last_marked']) : 0;
      if ($aTime === $bTime) { return ($a['modulo_id'] ?? 0) <=> ($b['modulo_id'] ?? 0); }
      return $bTime <=> $aTime;
    });

    foreach ($modulesList as $moduleRow) {
      $moduleId = (int)$moduleRow['modulo_id'];

      $schedule = [];
      if ($moduleId) {
        $qSchedule->execute([':a'=>(int)$e['aula_id'], ':m'=>$moduleId]);
        while ($row = $qSchedule->fetch(PDO::FETCH_ASSOC)) {
          $schedule[(int)$row['class_nro']] = $row['class_date'];
        }
      }

      $qAtt->execute([':enr'=>$enrId, ':mid'=>$moduleId]);
      $saved = [];
      $firstRecorded = null;
      while ($r = $qAtt->fetch(PDO::FETCH_ASSOC)) {
        $idx = (int)$r['class_nro'];
        $saved[$idx] = [
          'status'=>$r['status'],
          'date'=>$r['class_date'],
        ];
        if (!$firstRecorded) {
          $firstRecorded = $r['class_date'] ?: ($r['marked_at'] ? substr($r['marked_at'], 0, 10) : null);
        }
      }

      $start = $moduleRow['start_date'] ?? null;
      if (!$start) { $start = $schedule[1] ?? null; }
      if (!$start) { $start = $firstRecorded ?? null; }
      if (!$start && $schedule) { $first = reset($schedule); $start = $first ?: null; }

      $startFrom = (int)($moduleRow['start_from_class'] ?? 1);
      if ($startFrom < 1) { $startFrom = 1; }
      if ($startFrom === 1 && $saved) {
        $startFrom = min(array_keys($saved));
      }

      $maxSaved = $saved ? max(array_keys($saved)) : 0;
      $maxScheduled = $schedule ? max(array_keys($schedule)) : 0;
      $maxClass = max(4, $startFrom + 3, $maxSaved, $maxScheduled);

      $dates = [];
      for ($i=1; $i<=$maxClass; $i++) {
        if (isset($schedule[$i])) {
          $dates[$i] = $schedule[$i];
        } elseif (!empty($saved[$i]['date'])) {
          $dates[$i] = $saved[$i]['date'];
        } elseif ($start) {
          $dates[$i] = addDays($start, ($i-1)*7);
        } else {
          $dates[$i] = null;
        }
      }

      $clases = [];
      for ($i=1; $i<=$maxClass; $i++) {
        $dstr = $dates[$i];
        $dObj = $dstr ? new DateTimeImmutable($dstr) : null;
        if ($i < $startFrom) {
          $state = 'no_aplica';
        } elseif (isset($saved[$i])) {
          $state = $saved[$i]['status'];
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

      $modMeta = ['numero'=>null, 'titulo'=>null];
      if ($moduleId) {
        if (!isset($moduleMetaCache[$moduleId])) {
          $qModuloMeta->execute([':mid'=>$moduleId]);
          $moduleMetaCache[$moduleId] = $qModuloMeta->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($moduleMetaCache[$moduleId]) {
          $modMeta = [
            'numero'=>isset($moduleMetaCache[$moduleId]['numero']) ? (int)$moduleMetaCache[$moduleId]['numero'] : null,
            'titulo'=>$moduleMetaCache[$moduleId]['titulo'] ?? null,
          ];
        }
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
          'numero'=>$modMeta['numero'],
          'titulo'=>$modMeta['titulo'],
          'start_date'=>$start,
          'start_from_class'=>$startFrom,
          'estado'=>$moduleRow['estado'] ?? 'activo'
        ],
        'clases'=>$clases
      ];
    }
  }

  echo json_encode(['ok'=>true,'data'=>$out]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
