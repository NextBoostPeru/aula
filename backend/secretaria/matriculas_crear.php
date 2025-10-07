<?php
// backend/secretaria/matriculas_crear.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

date_default_timezone_set('America/Lima');
$pdo->exec("SET time_zone='-05:00'");

function colExists(PDO $pdo,$t,$c){
  $st=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}

try{
  if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit; }

  // Campos del formulario
  $sede_id          = (int)($_POST['sede_id'] ?? 0);
  $aula_curso_id    = (int)($_POST['aula_curso_id'] ?? 0);
  $nombres          = trim($_POST['nombres'] ?? '');
  $apellidos        = trim($_POST['apellidos'] ?? '');
  $dni              = trim($_POST['dni'] ?? '');
  $direccion        = trim($_POST['direccion'] ?? '');
  $correo           = trim($_POST['correo'] ?? '');
  $especialidad     = trim($_POST['especialidad'] ?? ''); // texto
  $fecha_inicio     = $_POST['fecha_inicio'] ?? null;
  $fecha_matricula  = $_POST['fecha_matricula'] ?? date('Y-m-d');
  $asesora          = trim($_POST['asesora'] ?? '');
  $nro_boleta       = trim($_POST['nro_boleta'] ?? '');
  $fecha_yape       = $_POST['fecha_yape'] ?? null;
  $celular          = trim($_POST['celular'] ?? '');
  $monto_matricula  = (float)($_POST['monto_matricula'] ?? 0);
  $metodo           = $_POST['metodo'] ?? 'efectivo'; // yape/efectivo/transferencia/otros
  $ref              = trim($_POST['ref'] ?? '');
  $paid_at          = $_POST['paid_at'] ?? date('Y-m-d H:i:s');

  if ($aula_curso_id<=0 || !$dni || !$nombres) {
    echo json_encode(['ok'=>false,'msg'=>'Faltan datos obligatorios']); exit;
  }

  $pdo->beginTransaction();

  // 1) Buscar/crear usuario (tabla users con columnas flexibles)
  // Intento por dni en 'username' o 'dni' si existiera
  $userId = null;
  // a) ¿existe columna dni en users?
  if (colExists($pdo,'users','dni')) {
    $st=$pdo->prepare("SELECT id FROM users WHERE dni=? LIMIT 1");
    $st->execute([$dni]); $userId = $st->fetchColumn();
  }
  // b) buscar por username==dni
  if (!$userId && colExists($pdo,'users','username')) {
    $st=$pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $st->execute([$dni]); $userId = $st->fetchColumn();
  }

  if (!$userId) {
    // columnas de nombre
    $hasFirst = colExists($pdo,'users','firstname');
    $hasLast  = colExists($pdo,'users','lastname');
    $cols = ['username','email'];
    $vals = [$dni, $correo ?: ($dni.'@no.mail')];
    $ph   = ['?','?'];
    if ($hasFirst){ $cols[]='firstname'; $vals[]=$nombres; $ph[]='?'; }
    if ($hasLast) { $cols[]='lastname' ; $vals[]=$apellidos; $ph[]='?'; }
    if (colExists($pdo,'users','dni')) { $cols[]='dni'; $vals[]=$dni; $ph[]='?'; }
    $sql = "INSERT INTO users (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
    $ins = $pdo->prepare($sql); $ins->execute($vals);
    $userId = (int)$pdo->lastInsertId();
  }

  // 2) Crear enrollment si no existe
  $st=$pdo->prepare("SELECT id FROM enrollment WHERE user_id=? AND aula_curso_id=? LIMIT 1");
  $st->execute([$userId, $aula_curso_id]);
  $enrollment_id = $st->fetchColumn();

  if (!$enrollment_id) {
    $ins=$pdo->prepare("INSERT INTO enrollment (user_id, aula_curso_id, created_at) VALUES (?,?, NOW())");
    $ins->execute([$userId, $aula_curso_id]);
    $enrollment_id = (int)$pdo->lastInsertId();
  }

  // 3) Guardar cabecera de matrícula (enrollment_matricula)
  $st=$pdo->prepare("SELECT id FROM enrollment_matricula WHERE enrollment_id=? LIMIT 1");
  $st->execute([$enrollment_id]);
  $matricula_id = $st->fetchColumn();

  if ($matricula_id) {
    $up = $pdo->prepare("UPDATE enrollment_matricula
      SET nombres=?, apellidos=?, dni=?, direccion=?, correo=?, sede_id=?, especialidad=?,
          fecha_inicio=?, fecha_matricula=?, asesora=?, nro_boleta=?, fecha_yape=?, celular=?, monto_matricula=?
      WHERE id=?");
    $up->execute([$nombres,$apellidos,$dni,$direccion,$correo,$sede_id?:null,$especialidad?:null,
                  $fecha_inicio?:null,$fecha_matricula,$asesora?:null,$nro_boleta?:null,$fecha_yape?:null,$celular?:null,$monto_matricula,$matricula_id]);
  } else {
    $ins=$pdo->prepare("INSERT INTO enrollment_matricula
      (enrollment_id,nombres,apellidos,dni,direccion,correo,sede_id,especialidad,fecha_inicio,fecha_matricula,asesora,nro_boleta,fecha_yape,celular,monto_matricula)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([$enrollment_id,$nombres,$apellidos,$dni,$direccion,$correo,$sede_id?:null,$especialidad?:null,$fecha_inicio?:null,$fecha_matricula,$asesora?:null,$nro_boleta?:null,$fecha_yape?:null,$celular?:null,$monto_matricula]);
    $matricula_id = (int)$pdo->lastInsertId();
  }

  // 4) Subir evidencias
  $base = __DIR__ . '/../../uploads/matriculas/'.$matricula_id.'/';
  if (!is_dir($base)) @mkdir($base, 0775, true);

  $saveFiles = function(string $field, string $tipo) use ($pdo,$matricula_id,$base){
    if (!isset($_FILES[$field])) return;
    $files = $_FILES[$field];
    $count = is_array($files['name']) ? count($files['name']) : 0;
    for ($i=0;$i<$count;$i++){
      if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
      $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION) ?: 'jpg';
      $fname = $tipo.'_'.time().'_'.$i.'.'.$ext;
      $dest = $base.$fname;
      if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
        $rel = 'uploads/matriculas/'.$matricula_id.'/'.$fname;
        $ins = $pdo->prepare("INSERT INTO matricula_evidence (matricula_id,tipo,file_path) VALUES (?,?,?)");
        $ins->execute([$matricula_id,$tipo,$rel]);
      }
    }
  };
  $saveFiles('chat_files','chat');
  $saveFiles('pago_files','pago');
  $saveFiles('boleta_files','boleta');

  // 5) Crear cuota de matrícula (nro=0, concepto=matricula)
  $st=$pdo->prepare("SELECT id FROM payment_quota WHERE enrollment_id=? AND concepto='matricula' LIMIT 1");
  $st->execute([$enrollment_id]);
  $quota_id = $st->fetchColumn();

  if (!$quota_id) {
    $ins=$pdo->prepare("INSERT INTO payment_quota (enrollment_id, nro, due_date, amount, concepto, created_at)
                        VALUES (?,?,?,?, 'matricula', NOW())");
    $ins->execute([$enrollment_id, 0, $fecha_matricula, $monto_matricula]);
    $quota_id = (int)$pdo->lastInsertId();
  }

  // 6) Registrar pago de matrícula (si >0)
  if ($monto_matricula > 0) {
    if ($ref==='') $ref = 'MAT-'.date('Ymd-His').'-'.$quota_id;
    $ins=$pdo->prepare("INSERT INTO payment_receipt (quota_id, amount, method, reference, curso_modulo_id, paid_at, created_at)
                        VALUES (?,?,?,?, NULL, ?, NOW())");
    $ins->execute([$quota_id, $monto_matricula, $metodo, $ref, $paid_at]);
    // Caja lo verá porque los listados usan payment_receipt.paid_at con joins por sede
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'msg'=>'Matrícula registrada','enrollment_id'=>$enrollment_id,'matricula_id'=>$matricula_id]);
} catch(Throwable $e){
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
