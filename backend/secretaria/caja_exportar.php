<?php
// backend/secretaria/caja_exportar.php  — CSV del cierre por sede y fecha (compatible con Excel)
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
date_default_timezone_set('America/Lima');
$pdo->exec("SET time_zone='-05:00'");

/* helpers */
function db_name(PDO $pdo){ return (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); }
function column_exists(PDO $pdo,$t,$c){
  $st=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  $st->execute([db_name($pdo),$t,$c]); return (bool)$st->fetchColumn();
}

try{
  $fecha   = $_GET['fecha']   ?? date('Y-m-d');
  $sede_id = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : 0;
  if ($sede_id<=0) { http_response_code(400); echo "sede_id requerido"; exit; }

  $ac_has_aula_id = column_exists($pdo,'aula_curso','aula_id');
  $aula_has_sede  = column_exists($pdo,'aula','sede_id');
  $ac_has_sede_id = column_exists($pdo,'aula_curso','sede_id');

  $joinAula=''; $whereSede=''; $params=[$fecha];
  if ($ac_has_aula_id && $aula_has_sede){ $joinAula="JOIN aula a ON a.id=ac.aula_id"; $whereSede=" AND a.sede_id=? "; $params[]=$sede_id; }
  elseif($ac_has_sede_id){ $whereSede=" AND ac.sede_id=? "; $params[]=$sede_id; }
  else { http_response_code(400); echo "No se puede filtrar por sede con el esquema actual"; exit; }

  // Traer boletas
  $sql = "
    SELECT r.paid_at, r.amount, r.method, r.reference,
           cm.titulo AS modulo, c.titulo AS curso
    FROM payment_receipt r
    JOIN payment_quota q ON q.id=r.quota_id
    JOIN enrollment e ON e.id=q.enrollment_id
    JOIN aula_curso ac ON ac.id=e.aula_curso_id
    $joinAula
    JOIN curso c ON c.id=ac.curso_id
    LEFT JOIN curso_modulo cm ON cm.id=r.curso_modulo_id
    WHERE DATE(r.paid_at)=? $whereSede
    ORDER BY r.paid_at ASC, r.id ASC";
  $st=$pdo->prepare($sql); $st->execute($params);
  $rows=$st->fetchAll(PDO::FETCH_ASSOC);

  // Totales
  $tot=['total'=>0,'efectivo'=>0,'yape'=>0,'transferencia'=>0,'otros'=>0];
  foreach($rows as $r){
    $amt=(float)$r['amount']; $tot['total']+=$amt;
    $m=strtolower(trim((string)$r['method']));
    if(isset($tot[$m])) $tot[$m]+=$amt; else $tot['otros']+=$amt;
  }

  // CSV headers
  $fname = "cierre_{$fecha}_sede{$sede_id}.csv";
  header('Content-Type: text/csv; charset=UTF-8');
  header("Content-Disposition: attachment; filename=\"$fname\"");

  // BOM + "sep=;" para que Excel use punto y coma como separador
  echo "\xEF\xBB\xBF";
  echo "sep=;\r\n";

  $out = fopen('php://output','w');

  // Título
  fputcsv($out, ["Cierre del {$fecha} - Sede {$sede_id}"], ';');
  fputcsv($out, [], ';');

  // Encabezados de columnas
  fputcsv($out, ['Hora','Curso','Módulo','Método','Referencia','Monto (S/)'], ';');

  // Filas de detalle
  foreach($rows as $r){
    $hora = explode(' ',$r['paid_at'])[1] ?? $r['paid_at'];
    fputcsv(
      $out,
      [$hora, $r['curso'] ?: '-', $r['modulo'] ?: '-', ucfirst($r['method']), $r['reference'], number_format((float)$r['amount'],2,'.','')],
      ';'
    );
  }

  // Tabla de totales
  fputcsv($out, [], ';');
  fputcsv($out, ['Totales','Efectivo','Yape','Transferencia','Otros','Total'], ';');
  fputcsv(
    $out,
    [
      '',
      number_format($tot['efectivo'],2,'.',''),
      number_format($tot['yape'],2,'.',''),
      number_format($tot['transferencia'],2,'.',''),
      number_format($tot['otros'],2,'.',''),
      number_format($tot['total'],2,'.',''),
    ],
    ';'
  );

  fclose($out);
} catch(Throwable $e){
  http_response_code(500); echo $e->getMessage();
}
