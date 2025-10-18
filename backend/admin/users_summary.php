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
    $key = strtolower($table) . ':' . strtolower($column);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    if (!table_exists($pdo, $table)) {
        return $cache[$key] = false;
    }
    $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $st->execute([db_name($pdo), $table, $column]);
    return $cache[$key] = (bool)$st->fetchColumn();
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

    if (!table_exists($pdo, 'users')) {
        json_reply(['ok' => true, 'roles' => [], 'recientes' => [], 'total' => 0]);
    }

    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $roleCol = pick_col($pdo, 'users', ['role', 'rol', 'perfil']);
    $statusCol = pick_col($pdo, 'users', ['status', 'estado', 'active']);
    $createdCol = pick_col($pdo, 'users', ['created_at', 'fecha_creacion', 'created', 'createdon', 'registered_at']);
    $firstNameCol = pick_col($pdo, 'users', ['firstname', 'nombres', 'first_name', 'name']);
    $lastNameCol  = pick_col($pdo, 'users', ['lastname', 'apellidos', 'last_name']);
    $dniCol       = pick_col($pdo, 'users', ['dni', 'documento', 'doc_number', 'idnumber']);
    $usernameCol  = pick_col($pdo, 'users', ['username', 'user']);
    $emailCol     = pick_col($pdo, 'users', ['email', 'correo', 'email_address']);

    if ($firstNameCol && $lastNameCol) {
        $nameExpr = "TRIM(CONCAT(IFNULL(u.`$firstNameCol`, ''), ' ', IFNULL(u.`$lastNameCol`, '')))";
    } elseif ($firstNameCol) {
        $nameExpr = "TRIM(u.`$firstNameCol`)";
    } elseif ($lastNameCol) {
        $nameExpr = "TRIM(u.`$lastNameCol`)";
    } else {
        $nameExpr = "TRIM(COALESCE(u.name, u.email, u.id))";
    }

    $dniExpr = $dniCol ? "TRIM(u.`$dniCol`)" : "''";
    $usernameExpr = $usernameCol ? "TRIM(u.`$usernameCol`)" : "''";
    $emailExpr = $emailCol ? "TRIM(u.`$emailCol`)" : "''";

    $roleExpr = $roleCol ? "COALESCE(NULLIF(TRIM(u.`$roleCol`), ''), 'Sin rol')" : "'Sin rol'";

    $rolesSql = 'SELECT ' . $roleExpr . ' AS rol, COUNT(*) AS cantidad FROM users u GROUP BY rol ORDER BY cantidad DESC';
    $roles = $pdo->query($rolesSql)->fetchAll();

    $totalUsers = 0;
    foreach ($roles as $row) {
        $totalUsers += (int)$row['cantidad'];
    }

    $roles = array_map(static function (array $row) use ($totalUsers): array {
        $cantidad = (int)$row['cantidad'];
        $participacion = $totalUsers > 0 ? round(($cantidad / $totalUsers) * 100, 1) : 0.0;
        return [
            'rol'           => $row['rol'] ?? 'Sin rol',
            'cantidad'      => $cantidad,
            'participacion' => $participacion,
        ];
    }, $roles);

    $orderColumn = $createdCol ? "u.`$createdCol`" : 'u.id';
    $recentSql = 'SELECT u.id, ' . $nameExpr . ' AS nombre, ' . $dniExpr . ' AS dni, ' . $usernameExpr . ' AS username, ' . $emailExpr . ' AS email';
    if ($roleCol) {
        $recentSql .= ', ' . $roleExpr . ' AS rol';
    } else {
        $recentSql .= ", 'Sin rol' AS rol";
    }
    if ($statusCol) {
        $recentSql .= ', COALESCE(u.`' . $statusCol . '`, 0) AS estado';
    } else {
        $recentSql .= ', NULL AS estado';
    }
    if ($createdCol) {
        $recentSql .= ', u.`' . $createdCol . '` AS registrado';
    } else {
        $recentSql .= ', NULL AS registrado';
    }
    $recentSql .= ' FROM users u ORDER BY ' . $orderColumn . ' DESC LIMIT 10';
    $recentRows = $pdo->query($recentSql)->fetchAll();

    $recent = array_map(static function (array $row) use ($createdCol): array {
        $registered = $row['registrado'] ?? null;
        if ($registered) {
            $registered = substr((string)$registered, 0, 19);
        }
        return [
            'id'        => (int)($row['id'] ?? 0),
            'nombre'    => $row['nombre'] ?? '',
            'dni'       => $row['dni'] ?? '',
            'username'  => $row['username'] ?? '',
            'email'     => $row['email'] ?? '',
            'rol'       => $row['rol'] ?? 'Sin rol',
            'estado'    => isset($row['estado']) ? (int)$row['estado'] : null,
            'registrado'=> $registered,
        ];
    }, $recentRows);

    json_reply([
        'ok'        => true,
        'roles'     => $roles,
        'recientes' => $recent,
        'total'     => $totalUsers,
    ]);
} catch (Throwable $e) {
    json_reply(['ok' => false, 'msg' => 'No se pudo obtener el resumen de usuarios', 'detail' => $e->getMessage()], 500);
}
