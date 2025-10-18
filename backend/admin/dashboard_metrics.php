<?php
declare(strict_types=1);

require __DIR__ . '/../_json_bootstrap.php';
require __DIR__ . '/../db.php';

function db_name(PDO $pdo): string {
    static $name;
    if ($name === null) {
        $name = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    }
    return $name;
}

function table_exists(PDO $pdo, string $table): bool {
    static $cache = [];
    $table = strtolower($table);
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1');
    $st->execute([db_name($pdo), $table]);
    return $cache[$table] = (bool)$st->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $tableKey = strtolower($table) . ':' . strtolower($column);
    if (array_key_exists($tableKey, $cache)) {
        return $cache[$tableKey];
    }
    if (!table_exists($pdo, $table)) {
        return $cache[$tableKey] = false;
    }
    $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $st->execute([db_name($pdo), $table, $column]);
    return $cache[$tableKey] = (bool)$st->fetchColumn();
}

function pick_col(PDO $pdo, string $table, array $candidates, ?string $fallback = null): ?string {
    foreach ($candidates as $candidate) {
        if (column_exists($pdo, $table, $candidate)) {
            return $candidate;
        }
    }
    return $fallback;
}

function format_currency(float $value): string {
    return 'S/ ' . number_format($value, 2, '.', ',');
}

function format_delta(float $current, float $previous, string $suffix): ?string {
    if ($previous <= 0.0 && $current <= 0.0) {
        return null;
    }
    if ($previous <= 0.0) {
        return '+100% ' . $suffix;
    }
    $percent = (($current - $previous) / $previous) * 100;
    return sprintf('%s%.1f%% %s', $percent >= 0 ? '+' : '', $percent, $suffix);
}

try {
    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        json_reply(['ok' => false, 'msg' => 'No autorizado'], 403);
    }

    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    date_default_timezone_set('America/Lima');

    if (!table_exists($pdo, 'payment_receipt')) {
        json_reply([
            'ok'      => true,
            'cards'   => [],
            'monthly' => [],
            'sedes'   => [],
            'recent'  => [],
        ]);
    }

    $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
    $yesterday = $today->modify('-1 day');
    $monthStart = $today->modify('first day of this month');
    $prevMonthStart = $monthStart->modify('-1 month');
    $nextMonthStart = $monthStart->modify('+1 month');

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payment_receipt WHERE DATE(paid_at) = :d');
    $stmt->execute([':d' => $today->format('Y-m-d')]);
    $todaySum = (float)$stmt->fetchColumn();

    $stmt->execute([':d' => $yesterday->format('Y-m-d')]);
    $yesterdaySum = (float)$stmt->fetchColumn();

    $stmtMonth = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payment_receipt WHERE paid_at >= :start AND paid_at < :end');
    $stmtMonth->execute([
        ':start' => $monthStart->format('Y-m-d 00:00:00'),
        ':end'   => $nextMonthStart->format('Y-m-d 00:00:00'),
    ]);
    $monthSum = (float)$stmtMonth->fetchColumn();

    $stmtMonth->execute([
        ':start' => $prevMonthStart->format('Y-m-d 00:00:00'),
        ':end'   => $monthStart->format('Y-m-d 00:00:00'),
    ]);
    $prevMonthSum = (float)$stmtMonth->fetchColumn();

    $stmtAvg = $pdo->prepare('SELECT COALESCE(AVG(amount),0) FROM payment_receipt WHERE paid_at >= :start AND paid_at < :end');
    $stmtAvg->execute([
        ':start' => $monthStart->format('Y-m-d 00:00:00'),
        ':end'   => $nextMonthStart->format('Y-m-d 00:00:00'),
    ]);
    $ticketProm = (float)$stmtAvg->fetchColumn();

    $stmtAvg->execute([
        ':start' => $prevMonthStart->format('Y-m-d 00:00:00'),
        ':end'   => $monthStart->format('Y-m-d 00:00:00'),
    ]);
    $ticketPrev = (float)$stmtAvg->fetchColumn();

    $totalSum = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM payment_receipt')->fetchColumn();

    $cards = [
        [
            'label' => 'Ingresos hoy',
            'value' => format_currency($todaySum),
            'delta' => format_delta($todaySum, $yesterdaySum, 'vs. ayer'),
        ],
        [
            'label' => 'Ingresos mes actual',
            'value' => format_currency($monthSum),
            'delta' => format_delta($monthSum, $prevMonthSum, 'vs. mes anterior'),
        ],
        [
            'label' => 'Ticket promedio (mes)',
            'value' => format_currency($ticketProm),
            'delta' => format_delta($ticketProm, $ticketPrev, 'vs. promedio anterior'),
        ],
        [
            'label' => 'Ingresos acumulados',
            'value' => format_currency($totalSum),
            'delta' => null,
        ],
    ];

    $monthsBack = 6;
    $trendStart = $monthStart->modify('-' . ($monthsBack - 1) . ' months');
    $trendStmt = $pdo->prepare('SELECT DATE_FORMAT(paid_at, "%Y-%m") AS period, SUM(amount) AS total FROM payment_receipt WHERE paid_at >= :start GROUP BY period ORDER BY period ASC');
    $trendStmt->execute([':start' => $trendStart->format('Y-m-01 00:00:00')]);
    $trendRows = $trendStmt->fetchAll();

    $monthNames = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];

    $maxTotal = 0.0;
    foreach ($trendRows as $row) {
        $maxTotal = max($maxTotal, (float)$row['total']);
    }

    $monthly = array_map(static function (array $row) use ($monthNames, $maxTotal): array {
        $period = $row['period'];
        $total = (float)$row['total'];
        $label = $period;
        if ($period && preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            $monthIndex = (int)$m[2] - 1;
            $label = ucfirst(($monthNames[$monthIndex] ?? $period)) . ' ' . $m[1];
        }
        $share = $maxTotal > 0 ? ($total / $maxTotal) * 100 : 0;
        return [
            'period' => $period,
            'label'  => $label,
            'total'  => $total,
            'share'  => round($share, 1),
        ];
    }, $trendRows);

    $sedes = [];
    $recent = [];

    $hasQuota = table_exists($pdo, 'payment_quota');
    $hasEnrollment = table_exists($pdo, 'enrollment');
    $hasAulaCurso = table_exists($pdo, 'aula_curso');
    $hasCurso = table_exists($pdo, 'curso');
    $hasUsers = table_exists($pdo, 'users');

    if ($hasQuota && $hasEnrollment && $hasAulaCurso && $hasCurso && $hasUsers) {
        $baseJoin = 'FROM payment_receipt r JOIN payment_quota q ON q.id = r.quota_id JOIN enrollment e ON e.id = q.enrollment_id JOIN aula_curso ac ON ac.id = e.aula_curso_id JOIN curso c ON c.id = ac.curso_id JOIN users u ON u.id = e.user_id';

        $sedeJoin = '';
        $sedeSelect = "'—' AS sede_nombre";
        $sedeIdSelect = 'NULL AS sede_id';
        $hasSede = table_exists($pdo, 'sede');

        if ($hasSede) {
            $sedeNameCol = pick_col($pdo, 'sede', ['nombre', 'name', 'descripcion', 'title']);
            $sedeSelect = $sedeNameCol ? "TRIM(s.`$sedeNameCol`) AS sede_nombre" : "CONCAT('Sede ', s.id) AS sede_nombre";
            $sedeIdSelect = 's.id AS sede_id';

            $acHasAulaId = column_exists($pdo, 'aula_curso', 'aula_id');
            $acHasSedeId = column_exists($pdo, 'aula_curso', 'sede_id');
            $aulaHasSede = column_exists($pdo, 'aula', 'sede_id');

            if ($acHasAulaId && $aulaHasSede && table_exists($pdo, 'aula')) {
                $sedeJoin = 'JOIN aula a ON a.id = ac.aula_id JOIN sede s ON s.id = a.sede_id';
            } elseif ($acHasSedeId) {
                $sedeJoin = 'JOIN sede s ON s.id = ac.sede_id';
            }
        }

        if ($sedeJoin !== '') {
            $rangeStart = $today->modify('-89 days');
            $sedeStmt = $pdo->prepare("SELECT $sedeIdSelect, $sedeSelect, COUNT(*) AS cobros, COALESCE(SUM(r.amount),0) AS total $baseJoin $sedeJoin WHERE r.paid_at >= :start GROUP BY sede_id, sede_nombre ORDER BY total DESC LIMIT 6");
            $sedeStmt->execute([':start' => $rangeStart->format('Y-m-d 00:00:00')]);
            $sedes = array_map(static function (array $row): array {
                return [
                    'id'     => isset($row['sede_id']) ? (int)$row['sede_id'] : null,
                    'nombre' => $row['sede_nombre'] ?? 'Sede',
                    'cobros' => (int)$row['cobros'],
                    'total'  => (float)$row['total'],
                ];
            }, $sedeStmt->fetchAll());
        }

        $firstNameCol = pick_col($pdo, 'users', ['firstname', 'nombres', 'first_name', 'name']);
        $lastNameCol  = pick_col($pdo, 'users', ['lastname', 'apellidos', 'last_name']);
        $dniCol       = pick_col($pdo, 'users', ['dni', 'documento', 'doc_number', 'idnumber']);
        $usernameCol  = pick_col($pdo, 'users', ['username', 'user']);

        if ($firstNameCol && $lastNameCol) {
            $studentSelect = "TRIM(CONCAT(IFNULL(u.`$firstNameCol`, ''), ' ', IFNULL(u.`$lastNameCol`, ''))) AS alumno";
        } elseif ($firstNameCol) {
            $studentSelect = "TRIM(u.`$firstNameCol`) AS alumno";
        } elseif ($lastNameCol) {
            $studentSelect = "TRIM(u.`$lastNameCol`) AS alumno";
        } else {
            $studentSelect = "TRIM(COALESCE(u.name, u.email, u.id)) AS alumno";
        }

        $dniSelect = $dniCol ? "TRIM(u.`$dniCol`) AS dni" : "NULL AS dni";
        $usernameSelect = $usernameCol ? "TRIM(u.`$usernameCol`) AS username" : "NULL AS username";

        $recentSql = "SELECT r.paid_at, r.amount, $studentSelect, $dniSelect, $usernameSelect, c.titulo AS curso, r.method, r.reference";
        if ($sedeJoin !== '') {
            $recentSql .= ", $sedeSelect";
        } else {
            $recentSql .= ", NULL AS sede_nombre";
        }
        $recentSql .= " $baseJoin $sedeJoin ORDER BY r.paid_at DESC LIMIT 10";

        $recentStmt = $pdo->query($recentSql);
        $recentRows = $recentStmt->fetchAll();
        $recent = array_map(static function (array $row): array {
            return [
                'fecha' => $row['paid_at'] ? substr($row['paid_at'], 0, 16) : '',
                'monto' => (float)$row['amount'],
                'alumno'=> $row['alumno'] ?? '',
                'dni'   => $row['dni'] ?? '',
                'usuario'=> $row['username'] ?? '',
                'curso' => $row['curso'] ?? '',
                'sede'  => $row['sede_nombre'] ?? '—',
                'metodo'=> $row['method'] ?? '',
                'referencia' => $row['reference'] ?? '',
            ];
        }, $recentRows);
    }

    json_reply([
        'ok'      => true,
        'cards'   => $cards,
        'monthly' => $monthly,
        'sedes'   => $sedes,
        'recent'  => $recent,
    ]);
} catch (Throwable $e) {
    json_reply(['ok' => false, 'msg' => 'No se pudo generar el resumen', 'detail' => $e->getMessage()], 500);
}
