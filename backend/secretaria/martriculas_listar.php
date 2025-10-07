<?php
// backend/secretaria/matriculas_listar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

date_default_timezone_set('America/Lima');
$pdo->exec("SET time_zone='-05:00'");

try {
  $sede_id = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : 0;
  $aula_id = isset($_GET['aula_id']) ? (int)$_GET['aula_id'] : 0;

  if ($sede_id<=0) {
    echo json_encode(['ok'=>true,'items'=>[]]); exit;
  }

  // join según tu esquema: aula_curso -> aula -> sede
  $sql = "
    SELECT em.id, em.nombres, em.apellidos, em.dni, em.fecha_matricula, em.monto_matricula,
           c.titulo AS curso, IFNULL(a.nombre,'-') AS aula,
           (SELECT SUM(pr.amount) FROM payment_receipt pr
             JOIN payment_quota pq ON pq.id = pr.quota_id
             WHERE pq.enrollment_id = e.id AND pq.concepto='matricula') AS pagado
    FROM enrollment_matricula em
    JOIN enrollment e ON e.id = em.enrollment_id
    JOIN aula_curso ac ON ac.id = e.aula_curso_id
    JOIN curso c ON c.id = ac.curso_id
    LEFT JOIN aula a ON a.id = ac.aula_id
    WHERE 1=1
  ";
  $params = [];
  if ($aula_id>0) {
    $sql .= " AND ac.aula_id = ? ";
    $params[] = $aula_id;
  } elseif ($sede_id>0) {
    $sql .= " AND a.sede_id = ? ";
    $params[] = $sede_id;
  }

  $sql .= " ORDER BY em.fecha_matricula DESC";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'items'=>$items]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
