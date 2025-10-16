<?php
declare(strict_types=1);

require __DIR__ . '/../_json_bootstrap.php';
require __DIR__ . '/../db.php';

// Helpers reutilizados desde caja_listar.php con caching sencillo
function db_name(PDO $pdo): string {
    static $name;
    if ($name === null) {
        $name = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    }
    return $name;
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
    );
    $st->execute([db_name($pdo), $table, $column]);
    return $cache[$key] = (bool)$st->fetchColumn();
}

function pick_col(PDO $pdo, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        if (column_exists($pdo, $table, $col)) {
            return $col;
        }
    }
    return null;
}

try {
    if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'secretaria') {
        json_reply(['ok' => false, 'msg' => 'No autorizado'], 403);
    }

    $aulaId = (int)($_GET['aula_id'] ?? 0);
    if ($aulaId <= 0) {
        json_reply(['ok' => false, 'msg' => 'aula_id requerido'], 422);
    }

    $page    = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 10);
    if ($page < 1) { $page = 1; }
    if ($perPage < 5) { $perPage = 5; }
    if ($perPage > 100) { $perPage = 100; }
    $offset = ($page - 1) * $perPage;

    // Verificar permiso por sede
    $st = $pdo->prepare('SELECT sede_id FROM aula WHERE id=:a LIMIT 1');
    $st->execute([':a' => $aulaId]);
    $sedeId = $st->fetchColumn();
    if (!$sedeId) {
        json_reply(['ok' => false, 'msg' => 'Aula no existe'], 404);
    }

    $uid = (int)$_SESSION['user']['id'];
    $chk = $pdo->prepare('SELECT 1 FROM secretaria_sede WHERE user_id=:u AND sede_id=:s LIMIT 1');
    $chk->execute([':u' => $uid, ':s' => $sedeId]);
    if (!$chk->fetch()) {
        json_reply(['ok' => false, 'msg' => 'Sin permiso para esta sede'], 403);
    }

    // Columnas dinámicas para nombre/DNI/username
    $firstNameCol = pick_col($pdo, 'users', ['firstname', 'nombres', 'first_name', 'name']);
    $lastNameCol  = pick_col($pdo, 'users', ['lastname', 'apellidos', 'last_name']);
    $dniCol       = pick_col($pdo, 'users', ['dni', 'documento', 'doc_number', 'idnumber']);
    $usernameCol  = column_exists($pdo, 'users', 'username') ? 'username' : null;

    if (!$firstNameCol && !$lastNameCol) {
        // último recurso: intenta "name" o "fullname"
        $firstNameCol = pick_col($pdo, 'users', ['name', 'fullname']);
    }

    $studentSelect = $firstNameCol && $lastNameCol
        ? "TRIM(CONCAT(IFNULL(u.`$firstNameCol`, ''), ' ', IFNULL(u.`$lastNameCol`, ''))) AS student_name"
        : ($firstNameCol
            ? "TRIM(u.`$firstNameCol`) AS student_name"
            : ($lastNameCol
                ? "TRIM(u.`$lastNameCol`) AS student_name"
                : "TRIM(COALESCE(u.name, u.username)) AS student_name"));

    $dniSelect = $dniCol
        ? "TRIM(u.`$dniCol`) AS student_dni"
        : "NULL AS student_dni";

    $usernameSelect = $usernameCol
        ? "TRIM(u.`$usernameCol`) AS username"
        : "NULL AS username";

    $aulaNameSelect = column_exists($pdo, 'aula', 'nombre')
        ? 'a.nombre AS aula_nombre'
        : "CONCAT('Aula ', a.id) AS aula_nombre";

    // Confirmamos que aula_curso tiene aula_id para filtrar
    if (!column_exists($pdo, 'aula_curso', 'aula_id')) {
        json_reply([
            'ok'   => true,
            'items'=> [],
            'meta' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'pages' => 0],
        ]);
    }

    $baseFrom = "
        FROM payment_receipt r
        JOIN payment_quota q ON q.id = r.quota_id
        JOIN enrollment e ON e.id = q.enrollment_id
        JOIN aula_curso ac ON ac.id = e.aula_curso_id
        JOIN aula a ON a.id = ac.aula_id
        JOIN curso c ON c.id = ac.curso_id
        JOIN users u ON u.id = e.user_id
    ";

    $where = 'WHERE ac.aula_id = :aula_id';
    $params = [':aula_id' => $aulaId];

    $countSql = "SELECT COUNT(*) " . $baseFrom . ' ' . $where;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $dataSql = "
        SELECT
            r.id,
            r.paid_at,
            r.amount,
            r.method,
            r.reference,
            r.notes,
            q.enrollment_id,
            COALESCE(NULLIF(q.cuota_no, 0), q.nro) AS cuota_label,
            c.titulo AS curso,
            $studentSelect,
            $dniSelect,
            $usernameSelect,
            $aulaNameSelect
        " . $baseFrom . ' ' . $where . "
        ORDER BY r.paid_at DESC, r.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $dataStmt = $pdo->prepare($dataSql);
    foreach ($params as $k => $v) {
        $dataStmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(static function (array $row): array {
        return [
            'id'            => (int)$row['id'],
            'paid_at'       => $row['paid_at'],
            'amount'        => (float)$row['amount'],
            'method'        => $row['method'],
            'reference'     => $row['reference'],
            'notes'         => $row['notes'],
            'enrollment_id' => (int)$row['enrollment_id'],
            'cuota_label'   => $row['cuota_label'],
            'curso'         => $row['curso'],
            'aula'          => $row['aula_nombre'],
            'student'       => [
                'name'     => $row['student_name'],
                'dni'      => $row['student_dni'],
                'username' => $row['username'],
            ],
        ];
    }, $rows);

    $pages = $total > 0 ? (int)ceil($total / $perPage) : 0;

    json_reply([
        'ok'   => true,
        'items'=> $items,
        'meta' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => $pages,
        ],
    ]);

} catch (Throwable $e) {
    json_reply(['ok' => false, 'msg' => 'Error al listar pagos', 'detail' => $e->getMessage()], 500);
}
