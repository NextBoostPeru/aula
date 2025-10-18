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

try {
    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        json_reply(['ok' => false, 'msg' => 'No autorizado'], 403);
    }

    if (!table_exists($pdo, 'sede')) {
        json_reply(['ok' => true, 'sedes' => []]);
    }

    $nameCol = column_exists($pdo, 'sede', 'nombre') ? 'nombre' : (column_exists($pdo, 'sede', 'name') ? 'name' : null);
    $orderCol = $nameCol ?? 'id';
    $sql = 'SELECT id' . ($nameCol ? ", $nameCol AS nombre" : '') . ' FROM sede ORDER BY ' . $orderCol . ' ASC';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $sedes = array_map(static function (array $row) use ($nameCol): array {
        return [
            'id'     => isset($row['id']) ? (int)$row['id'] : null,
            'nombre' => $nameCol ? ($row['nombre'] ?? '') : ('Sede #' . ($row['id'] ?? '')),
        ];
    }, $rows);

    json_reply(['ok' => true, 'sedes' => $sedes]);
} catch (Throwable $e) {
    json_reply(['ok' => false, 'msg' => 'No se pudieron listar las sedes', 'detail' => $e->getMessage()], 500);
}
