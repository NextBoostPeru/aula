<?php
// backend/secretaria/caja_listar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// TZ Perú
date_default_timezone_set('America/Lima');
$pdo->exec("SET time_zone='-05:00'");

/* Helpers */
function db_name(PDO $pdo): string {
  return (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
}
function column_exists(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  $st->execute([db_name($pdo), $table, $column]);
  return (bool)$st->fetchColumn();
}
function pick_col(PDO $pdo, string $table, array $cands): ?string {
  foreach ($cands as $c) if (column_exists($pdo, $table, $c)) return $c;
  return null;
}

try {
  $fecha   = $_GET['fecha'] ?? date('Y-m-d'); // YYYY-MM-DD
  $sede_id = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : null;

  // Si no hay sede, no mostramos nada
  if (!$sede_id) {
    echo json_encode(['ok'=>true,'fecha'=>$fecha,'items'=>[],'totales'=>[
      'total'=>0,'efectivo'=>0,'yape'=>0,'transferencia'=>0,'otros'=>0,'count'=>0
    ],'closure'=>null]);
    exit;
  }

  // Nombre del alumno (schema-agnóstico)
  $first = pick_col($pdo, 'users', ['firstname','nombres','first_name','name']);
  $last  = pick_col($pdo, 'users', ['lastname','apellidos','last_name','surname']);
  if     ($first && $last) $alumnoSelect = "CONCAT(TRIM(u.`$first`),' ',TRIM(u.`$last`)) AS alumno";
  elseif ($first)          $alumnoSelect = "TRIM(u.`$first`) AS alumno";
  elseif ($last)           $alumnoSelect = "TRIM(u.`$last`) AS alumno";
  else                     $alumnoSelect = "'-' AS alumno";

  // Filtro por sede: esquema A (aula_curso->aula->sede_id) o B (aula_curso.sede_id)
  $joinAula  = "";
  $whereSede = "";
  $params    = [$fecha];

  $ac_has_aula_id = column_exists($pdo, 'aula_curso', 'aula_id');
  $ac_has_sede_id = column_exists($pdo, 'aula_curso', 'sede_id');
  $aula_has_sede  = column_exists($pdo, 'aula', 'sede_id');

  if ($ac_has_aula_id && $aula_has_sede) {
    $joinAula  = "JOIN aula a ON a.id = ac.aula_id";
    $whereSede = " AND a.sede_id = ? ";
    $params[]  = $sede_id;
  } elseif ($ac_has_sede_id) {
    $whereSede = " AND ac.sede_id = ? ";
    $params[]  = $sede_id;
  } else {
    // No hay forma de filtrar por sede en el esquema -> devolvemos vacío para no mostrar datos de otras sedes
    echo json_encode(['ok'=>true,'fecha'=>$fecha,'items'=>[],'totales'=>[
      'total'=>0,'efectivo'=>0,'yape'=>0,'transferencia'=>0,'otros'=>0,'count'=>0
    ],'closure'=>null]);
    exit;
  }

  // Traer pagos del día por sede
  $sql = "
    SELECT r.id, r.paid_at, r.amount, r.method, r.reference,
           cm.titulo AS modulo,
           c.titulo  AS curso,
           $alumnoSelect
    FROM payment_receipt r
    JOIN payment_quota q   ON q.id = r.quota_id
    JOIN enrollment e      ON e.id = q.enrollment_id
    JOIN aula_curso ac     ON ac.id = e.aula_curso_id
    $joinAula
    JOIN curso c           ON c.id = ac.curso_id
    LEFT JOIN curso_modulo cm ON cm.id = r.curso_modulo_id
    LEFT JOIN users u      ON u.id = e.user_id
    WHERE DATE(r.paid_at) = ? $whereSede
    ORDER BY r.paid_at ASC, r.id ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);

  // Totales por método (normalizamos método a minúscula)
  $tot = ['total'=>0.0,'efectivo'=>0.0,'yape'=>0.0,'transferencia'=>0.0,'otros'=>0.0,'count'=>0];
  foreach ($items as $it) {
    $amt = (float)$it['amount'];
    $method = strtolower(trim((string)$it['method']));
    $tot['total'] += $amt;
    $tot['count'] += 1;
    if (isset($tot[$method])) $tot[$method] += $amt;
    else $tot['otros'] += $amt;
  }

  // Cierre del día por sede (si la tabla tiene columna sede_id)
  $closure = null;
  if (column_exists($pdo, 'cash_closure', 'sede_id')) {
    $st = $pdo->prepare("SELECT * FROM cash_closure WHERE fecha=? AND sede_id=? LIMIT 1");
    $st->execute([$fecha, $sede_id]);
    $closure = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  } else {
    // Si no hay columna sede, no intentamos mostrar cierres globales para evitar confusión
    $closure = null;
  }

  echo json_encode(['ok'=>true,'fecha'=>$fecha,'items'=>$items,'totales'=>$tot,'closure'=>$closure]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
