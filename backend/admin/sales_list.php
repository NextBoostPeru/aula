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

try {
    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        json_reply(['ok' => false, 'msg' => 'No autorizado'], 403);
    }

    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!table_exists($pdo, 'payment_receipt') || !table_exists($pdo, 'payment_quota') || !table_exists($pdo, 'enrollment') || !table_exists($pdo, 'aula_curso')) {
        json_reply([
            'ok'   => true,
            'items'=> [],
            'meta' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'pages' => 0, 'sum' => 0],
        ]);
    }

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    if ($perPage < 10) { $perPage = 10; }
    if ($perPage > 100) { $perPage = 100; }
    $offset = ($page - 1) * $perPage;

    $method = strtolower(trim((string)($_GET['method'] ?? '')));
    $sedeId = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : 0;
    $search = trim((string)($_GET['q'] ?? ''));
    $from   = trim((string)($_GET['from'] ?? ''));
    $to     = trim((string)($_GET['to'] ?? ''));

    $conditions = [];
    $params = [];

    if ($from !== '') {
        $conditions[] = 'DATE(r.paid_at) >= :from';
        $params[':from'] = $from;
    }
    if ($to !== '') {
        $conditions[] = 'DATE(r.paid_at) <= :to';
        $params[':to'] = $to;
    }

    $baseJoin = 'FROM payment_receipt r JOIN payment_quota q ON q.id = r.quota_id JOIN enrollment e ON e.id = q.enrollment_id JOIN aula_curso ac ON ac.id = e.aula_curso_id JOIN curso c ON c.id = ac.curso_id JOIN users u ON u.id = e.user_id';

    $sedeJoin = '';
    $sedeFilter = '';
    $sedeSelect = "'—' AS sede_nombre";
    if (table_exists($pdo, 'sede')) {
        $sedeNameCol = pick_col($pdo, 'sede', ['nombre', 'name', 'descripcion', 'title']);
        $sedeSelect = $sedeNameCol ? "TRIM(s.`$sedeNameCol`) AS sede_nombre" : "CONCAT('Sede ', s.id) AS sede_nombre";
        $acHasAulaId = column_exists($pdo, 'aula_curso', 'aula_id');
        $acHasSedeId = column_exists($pdo, 'aula_curso', 'sede_id');
        $aulaHasSede = column_exists($pdo, 'aula', 'sede_id');

        if ($acHasAulaId && $aulaHasSede && table_exists($pdo, 'aula')) {
            $sedeJoin = 'JOIN aula a ON a.id = ac.aula_id JOIN sede s ON s.id = a.sede_id';
            $sedeFilter = 'a.sede_id = :sede_id';
        } elseif ($acHasSedeId) {
            $sedeJoin = 'JOIN sede s ON s.id = ac.sede_id';
            $sedeFilter = 'ac.sede_id = :sede_id';
        }
    }

    if ($sedeId > 0 && $sedeFilter !== '') {
        $conditions[] = $sedeFilter;
        $params[':sede_id'] = $sedeId;
    }

    if ($method !== '') {
        if ($method === 'otros') {
            $conditions[] = "LOWER(COALESCE(r.method, '')) NOT IN ('efectivo','yape','transferencia')";
        } else {
            $conditions[] = 'LOWER(r.method) = :method';
            $params[':method'] = $method;
        }
    }

    $firstNameCol = pick_col($pdo, 'users', ['firstname', 'nombres', 'first_name', 'name']);
    $lastNameCol  = pick_col($pdo, 'users', ['lastname', 'apellidos', 'last_name']);
    $dniCol       = pick_col($pdo, 'users', ['dni', 'documento', 'doc_number', 'idnumber']);
    $usernameCol  = pick_col($pdo, 'users', ['username', 'user']);

    if ($firstNameCol && $lastNameCol) {
        $studentExpr = "TRIM(CONCAT(IFNULL(u.`$firstNameCol`, ''), ' ', IFNULL(u.`$lastNameCol`, '')))";
    } elseif ($firstNameCol) {
        $studentExpr = "TRIM(u.`$firstNameCol`)";
    } elseif ($lastNameCol) {
        $studentExpr = "TRIM(u.`$lastNameCol`)";
    } else {
        $studentExpr = "TRIM(COALESCE(u.name, u.email, u.id))";
    }
    $studentSelect = $studentExpr . ' AS student_name';

    $dniExpr = $dniCol ? "TRIM(u.`$dniCol`)" : "''";
    $usernameExpr = $usernameCol ? "TRIM(u.`$usernameCol`)" : "''";
    $dniSelect = $dniCol ? $dniExpr . ' AS student_dni' : "NULL AS student_dni";
    $usernameSelect = $usernameCol ? $usernameExpr . ' AS student_username' : "NULL AS student_username";

    if ($search !== '') {
        $conditions[] = "(" . implode(' OR ', [
            "$studentExpr LIKE :search",
            "COALESCE($dniExpr, '') LIKE :search",
            "COALESCE($usernameExpr, '') LIKE :search",
            "COALESCE(r.reference, '') LIKE :search",
        ]) . ')';
        $params[':search'] = '%' . $search . '%';
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $countSql = 'SELECT COUNT(*) ' . $baseJoin . ' ' . $sedeJoin . ' ' . $where;
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        if ($key === ':method' || $key === ':search') {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        } elseif ($key === ':sede_id') {
            $countStmt->bindValue($key, (int)$value, PDO::PARAM_INT);
        } else {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $sumSql = 'SELECT COALESCE(SUM(r.amount),0) ' . $baseJoin . ' ' . $sedeJoin . ' ' . $where;
    $sumStmt = $pdo->prepare($sumSql);
    foreach ($params as $key => $value) {
        if ($key === ':method' || $key === ':search') {
            $sumStmt->bindValue($key, $value, PDO::PARAM_STR);
        } elseif ($key === ':sede_id') {
            $sumStmt->bindValue($key, (int)$value, PDO::PARAM_INT);
        } else {
            $sumStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $sumStmt->execute();
    $sum = (float)$sumStmt->fetchColumn();

    $dataSql = 'SELECT r.id, r.paid_at, r.amount, r.method, r.reference, r.notes, ' . $studentSelect . ', ' . $dniSelect . ', ' . $usernameSelect . ', c.titulo AS curso';
    if ($sedeJoin !== '') {
        $dataSql .= ', ' . $sedeSelect;
    } else {
        $dataSql .= ", '—' AS sede_nombre";
    }
    $dataSql .= ' ' . $baseJoin . ' ' . $sedeJoin . ' ' . $where . ' ORDER BY r.paid_at DESC, r.id DESC LIMIT :limit OFFSET :offset';

    $dataStmt = $pdo->prepare($dataSql);
    foreach ($params as $key => $value) {
        if ($key === ':method' || $key === ':search') {
            $dataStmt->bindValue($key, $value, PDO::PARAM_STR);
        } elseif ($key === ':sede_id') {
            $dataStmt->bindValue($key, (int)$value, PDO::PARAM_INT);
        } else {
            $dataStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();

    $rows = $dataStmt->fetchAll();
    $items = array_map(static function (array $row): array {
        return [
            'id'       => (int)$row['id'],
            'fecha'    => $row['paid_at'] ? substr($row['paid_at'], 0, 16) : '',
            'monto'    => (float)$row['amount'],
            'metodo'   => strtolower(trim((string)$row['method'] ?? '')),
            'referencia'=> $row['reference'] ?? '',
            'curso'    => $row['curso'] ?? '',
            'sede'     => $row['sede_nombre'] ?? '—',
            'alumno'   => [
                'nombre'   => $row['student_name'] ?? '',
                'dni'      => $row['student_dni'] ?? '',
                'username' => $row['student_username'] ?? '',
            ],
        ];
    }, $rows);

    $pages = $total > 0 ? (int)ceil($total / $perPage) : 0;

    json_reply([
        'ok'    => true,
        'items' => $items,
        'meta'  => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => $pages,
            'sum'      => $sum,
        ],
    ]);
} catch (Throwable $e) {
    json_reply(['ok' => false, 'msg' => 'No se pudo listar los pagos', 'detail' => $e->getMessage()], 500);
}
