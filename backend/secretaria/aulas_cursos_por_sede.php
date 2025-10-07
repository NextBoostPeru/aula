<?php
// backend/secretaria/aulas_cursos_por_sede.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
date_default_timezone_set('America/Lima');
$pdo->exec("SET time_zone='-05:00'");

try {
  $sede_id = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : 0;
  if ($sede_id<=0) { echo json_encode(['ok'=>true,'items'=>[]]); exit; }

  // Soporta dos esquemas: aula_curso.aula_id -> aula.sede_id  ó aula_curso.sede_id directo
  $hasAC_Aula = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aula_curso' AND COLUMN_NAME='aula_id'")->fetchColumn();
  $hasAC_Sede = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aula_curso' AND COLUMN_NAME='sede_id'")->fetchColumn();
  $hasAulaSede= (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aula' AND COLUMN_NAME='sede_id'")->fetchColumn();

  $join = ""; $where = ""; $params = [];
  if ($hasAC_Aula && $hasAulaSede) {
    $join  = "JOIN aula a ON a.id = ac.aula_id";
    $where = "WHERE a.sede_id = ?";
    $params = [$sede_id];
  } elseif ($hasAC_Sede) {
    $where = "WHERE ac.sede_id = ?";
    $params = [$sede_id];
  } else {
    echo json_encode(['ok'=>true,'items'=>[]]); exit;
  }

  $sql = "
    SELECT ac.id, c.titulo AS curso, 
           IFNULL(a.nombre, CONCAT('Aula ', ac.id)) AS aula
    FROM aula_curso ac
    JOIN curso c ON c.id = ac.curso_id
    $join
    $where
    ORDER BY c.titulo, aula
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'items'=>$items]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
