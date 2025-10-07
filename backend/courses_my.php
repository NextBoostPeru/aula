<?php
// backend/courses_my.php
session_start();
header('Content-Type: application/json');

require __DIR__ . '/db.php';

// Asegurar sesión
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}
$userId = (int)$_SESSION['user']['id'];

/**
 * Util: fecha -> Y-m-d
 */
function d($ts) {
    return date('Y-m-d', $ts);
}

/**
 * Calcula fechas de 4 clases semanales (D0, D7, D14, D21),
 * a partir de start_date (fecha de PRIMERA clase para el alumno).
 */
function calcularFechasClases(string $startDate): array {
    $base = strtotime($startDate . ' 00:00:00');
    return [
        d($base + 0*86400*7),
        d($base + 1*86400*7),
        d($base + 2*86400*7),
        d($base + 3*86400*7),
    ];
}

/**
 * Progreso en 28 días desde start_date hasta hoy (0..1)
 */
function progreso28(string $startDate): float {
    $start = strtotime($startDate . ' 00:00:00');
    $end   = $start + 28*86400;
    $now   = time();
    if ($now <= $start) return 0.0;
    if ($now >= $end)   return 1.0;
    return ($now - $start) / ($end - $start);
}

try {
    // Traer matrículas del usuario + sede, aula, curso, módulo ACTIVO (alumno_modulo.is_active=1)
    $sql = "
        SELECT
          e.id               AS enrollment_id,
          s.nombre           AS sede_nombre,
          a.nombre           AS aula_nombre,
          c.id               AS curso_id,
          c.titulo           AS curso_titulo,
          cm.id              AS modulo_id,
          cm.numero          AS modulo_numero,
          cm.titulo          AS modulo_titulo,
          cm.video_url, cm.pdf_url, cm.slides_url,
          am.start_date,
          am.start_from_class
        FROM enrollment e
        JOIN aula_curso ac   ON ac.id = e.aula_curso_id
        JOIN aula a          ON a.id = ac.aula_id
        JOIN sede s          ON s.id = a.sede_id
        JOIN curso c         ON c.id = ac.curso_id
        LEFT JOIN alumno_modulo am
                             ON am.enrollment_id = e.id AND am.is_active = 1
        LEFT JOIN curso_modulo cm
                             ON cm.id = am.modulo_id
        WHERE e.user_id = :uid
        ORDER BY e.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':uid' => $userId]);
    $rows = $st->fetchAll();

    $out = [];
    $today = date('Y-m-d');

    foreach ($rows as $r) {
        // Si no hay modulo activo aún, lo marcamos como null
        if (!$r['modulo_id']) {
            $out[] = [
                'curso' => [
                    'id' => (int)$r['curso_id'],
                    'titulo' => $r['curso_titulo'],
                    'sede' => $r['sede_nombre'],
                    'aula' => $r['aula_nombre'],
                ],
                'modulo' => null,
                'progress' => 0,
                'periodo' => null,
                'clases' => [],
                'links' => []
            ];
            continue;
        }

        $startDate = $r['start_date'];
        $endDate   = date('Y-m-d', strtotime($startDate) + 28*86400);
        $clases    = calcularFechasClases($startDate);
        $prog      = progreso28($startDate);
        $pct       = round($prog * 100);

        // Próxima clase (>= hoy)
        $nextClass = null;
        foreach ($clases as $cd) {
            if ($cd >= $today) { $nextClass = $cd; break; }
        }

        $out[] = [
            'curso' => [
                'id' => (int)$r['curso_id'],
                'titulo' => $r['curso_titulo'],
                'sede' => $r['sede_nombre'],
                'aula' => $r['aula_nombre'],
            ],
            'modulo' => [
                'id' => (int)$r['modulo_id'],
                'numero' => (int)$r['modulo_numero'],
                'titulo' => $r['modulo_titulo'],
                'start_from_class' => (int)$r['start_from_class'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_class_date' => $nextClass
            ],
            'progress' => $prog,      // 0..1
            'progress_pct' => $pct,   // 0..100
            'periodo' => [
                'inicio' => $startDate,
                'fin'    => $endDate
            ],
            'clases' => [
                ['nro' => 1, 'date' => $clases[0]],
                ['nro' => 2, 'date' => $clases[1]],
                ['nro' => 3, 'date' => $clases[2]],
                ['nro' => 4, 'date' => $clases[3]],
            ],
            'links' => [
                'video'  => $r['video_url'],
                'pdf'    => $r['pdf_url'],
                'slides' => $r['slides_url'],
            ]
        ];
    }

    echo json_encode(['ok' => true, 'data' => $out]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor']);
}
