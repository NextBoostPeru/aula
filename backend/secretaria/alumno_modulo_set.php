<?php
session_start(); header('Content-Type: application/json');
require __DIR__.'/../db.php';
if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role']!=='secretaria'){ http_response_code(403); echo json_encode(['ok'=>false]); exit; }

$enrollment_id=(int)($_POST['enrollment_id']??0);
$modulo_id=(int)($_POST['modulo_id']??0);
$start_date=$_POST['start_date']??null;           // 'YYYY-MM-DD'
$start_from_class=(int)($_POST['start_from_class']??1);
$is_active=(int)($_POST['is_active']??1);

try{
  // Validar que enrollment pertenece a aula de sedes de la secretaria
  $uid=(int)$_SESSION['user']['id'];
  $chk=$pdo->prepare("
    SELECT 1
    FROM enrollment e
    JOIN aula_curso ac ON ac.id=e.aula_curso_id
    JOIN aula a        ON a.id=ac.aula_id
    JOIN secretaria_sede ss ON ss.sede_id=a.sede_id AND ss.user_id=:u
    WHERE e.id=:e LIMIT 1");
  $chk->execute([':u'=>$uid,':e'=>$enrollment_id]); if(!$chk->fetch()){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Sin permiso']); exit; }

  // UPSERT
  $q=$pdo->prepare("
    INSERT INTO alumno_modulo (enrollment_id, modulo_id, start_date, start_from_class, is_active)
    VALUES (:e,:m,:sd,:sfc,:ia)
    ON DUPLICATE KEY UPDATE start_date=VALUES(start_date), start_from_class=VALUES(start_from_class), is_active=VALUES(is_active)");
  $q->execute([':e'=>$enrollment_id,':m'=>$modulo_id,':sd'=>$start_date,':sfc'=>$start_from_class,':ia'=>$is_active]);
  echo json_encode(['ok'=>true,'msg'=>'Módulo asignado/actualizado']);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Error']);}
