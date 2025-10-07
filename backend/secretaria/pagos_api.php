<?php
// pagos_api.php (para sanignaciodeloyo_aulavirtual)
// Usa: users, curso, curso_modulo, enrollment, payment_quota, payment_receipt

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/db.php'; // $pdo PDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function ok($arr=[]){ echo json_encode(['ok'=>true]+$arr); exit; }
function err($m,$code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]); exit; }

/* ===== Helpers de dominio ===== */

function get_user_by_dni(PDO $pdo, string $dni): ?array {
  $st=$pdo->prepare("SELECT id AS userid, username, firstname, lastname, email, idnumber FROM users WHERE idnumber=? LIMIT 1");
  $st->execute([$dni]); $u=$st->fetch(PDO::FETCH_ASSOC); return $u?:null;
}

function get_user_courses(PDO $pdo, int $userid): array {
  // cursos por matrícula
  $st=$pdo->prepare("
    SELECT DISTINCT c.id AS courseid, c.nombre AS fullname
    FROM curso c
    JOIN enrollment e ON e.curso_id=c.id
    WHERE e.user_id=?
  ");
  $st->execute([$userid]); return $st->fetchAll(PDO::FETCH_ASSOC);
}

function get_enrollment(PDO $pdo, int $userid, int $courseid): ?array {
  $st=$pdo->prepare("SELECT id FROM enrollment WHERE user_id=? AND curso_id=? LIMIT 1");
  $st->execute([$userid,$courseid]); $e=$st->fetch(PDO::FETCH_ASSOC); return $e?:null;
}

function course_modules_count(PDO $pdo, int $courseid): int {
  // 1) si existe curso.n_modulos úsalo; si no, cuenta curso_modulo
  $st=$pdo->prepare("SELECT n_modulos FROM curso WHERE id=?");
  $st->execute([$courseid]); $n=$st->fetchColumn();
  if ($n!==false && (int)$n>0) return (int)$n;
  $st=$pdo->prepare("SELECT COUNT(*) FROM curso_modulo WHERE curso_id=?");
  $st->execute([$courseid]); return (int)$st->fetchColumn();
}

/**
 * Sincroniza payment_quota para enrollment_id:
 * - Crea cuotas faltantes hasta n_modulos
 * - due_date cada 28 días desde $startDate
 * - amount_due = $amountEach (si null, usa el de primera cuota o 0)
 */
function sync_quotas(PDO $pdo, int $enrollment_id, int $n_modulos, string $startDate, ?float $amountEach=null): array {
  // leer cuotas existentes
  $st=$pdo->prepare("SELECT cuota_no, amount_due FROM payment_quota WHERE enrollment_id=? ORDER BY cuota_no");
  $st->execute([$enrollment_id]);
  $rows=$st->fetchAll(PDO::FETCH_ASSOC);

  if ($amountEach===null) {
    foreach($rows as $r){ if((float)$r['amount_due']>0){ $amountEach=(float)$r['amount_due']; break; } }
  }
  if ($amountEach===null) $amountEach=0.0;

  $have=[]; foreach($rows as $r){ $have[(int)$r['cuota_no']]=true; }

  $created=0;
  $pdo->beginTransaction();
  try{
    for($i=1;$i<=$n_modulos;$i++){
      if(!isset($have[$i])){
        $due=(new DateTime($startDate))->modify('+'.(28*($i-1)).' days')->format('Y-m-d');
        $ins=$pdo->prepare("INSERT INTO payment_quota (enrollment_id, cuota_no, due_date, amount_due) VALUES (?,?,?,?)");
        $ins->execute([$enrollment_id,$i,$due,$amountEach]);
        $created++;
      }
    }
    $pdo->commit();
  }catch(Throwable $e){ $pdo->rollBack(); err($e->getMessage(),500); }

  // devolver cuotas
  $st=$pdo->prepare("
    SELECT q.*, (q.amount_due - q.amount_paid) AS saldo,
           c.nombre AS coursename, e.user_id, e.curso_id
    FROM payment_quota q
    JOIN enrollment e ON e.id=q.enrollment_id
    JOIN curso c ON c.id=e.curso_id
    WHERE q.enrollment_id=?
    ORDER BY q.cuota_no
  ");
  $st->execute([$enrollment_id]);
  return ['created'=>$created, 'quotas'=>$st->fetchAll(PDO::FETCH_ASSOC)];
}

/* ===== Roteo ===== */
$action = $_GET['action'] ?? $_POST['action'] ?? 'list_today';

try{
  switch($action){

    /* Pagos del día (JOINs para nombres legibles) */
    case 'list_today': {
      $sql="
        SELECT
          r.id, r.quota_id, r.amount, r.method, r.reference, r.paid_at, r.notes,
          q.cuota_no, q.enrollment_id,
          u.username, u.firstname, u.lastname,
          c.nombre AS coursename
        FROM payment_receipt r
        JOIN payment_quota q ON q.id=r.quota_id
        JOIN enrollment e ON e.id=q.enrollment_id
        JOIN users u ON u.id=e.user_id
        JOIN curso c ON c.id=e.curso_id
        WHERE DATE(r.paid_at)=CURDATE()
        ORDER BY r.paid_at DESC, r.id DESC
      ";
      $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
      ok(['data'=>$rows]);
    }

    /* Buscar por DNI; opcionalmente courseid y autosync */
    case 'find_user_by_dni': {
      $dni=trim($_GET['dni'] ?? $_POST['dni'] ?? '');
      $courseid=(int)($_GET['courseid'] ?? $_POST['courseid'] ?? 0);
      $autosync=(int)($_GET['autosync'] ?? $_POST['autosync'] ?? 0);
      $start=$_GET['start_date'] ?? $_POST['start_date'] ?? date('Y-m-d');
      $amount=isset($_GET['amount_each']) ? (float)$_GET['amount_each']
            : (isset($_POST['amount_each'])?(float)$_POST['amount_each']:null);

      if($dni==='') err('DNI requerido');

      $u=get_user_by_dni($pdo,$dni);
      if(!$u) err('No existe usuario con ese DNI');

      $courses=get_user_courses($pdo,(int)$u['userid']);

      $quotas=[]; $synced=null;
      if($courseid>0){
        $enr=get_enrollment($pdo,(int)$u['userid'],$courseid);
        if(!$enr) err('El alumno no está matriculado en este curso');
        $n = course_modules_count($pdo,$courseid);
        if($autosync===1){
          $synced=sync_quotas($pdo,(int)$enr['id'],$n,$start,$amount);
          $quotas=$synced['quotas'];
        }else{
          $st=$pdo->prepare("
            SELECT q.id AS quota_id, q.cuota_no, q.due_date, q.amount_due, q.amount_paid,
                   (q.amount_due - q.amount_paid) AS saldo, q.status,
                   c.id AS courseid, c.nombre AS coursename
            FROM payment_quota q
            JOIN enrollment e ON e.id=q.enrollment_id
            JOIN curso c ON c.id=e.curso_id
            WHERE q.enrollment_id=? AND q.status IN ('pending','partial','overdue')
            ORDER BY q.due_date ASC, q.cuota_no ASC
          ");
          $st->execute([$enr['id']]); $quotas=$st->fetchAll(PDO::FETCH_ASSOC);
        }
      }

      ok(['user'=>$u,'courses'=>$courses,'quotas'=>$quotas,'synced'=>$synced]);
    }

    /* Sincronizar cuotas (igualar a n_modulos) */
    case 'sync_quotas': {
      $userid=(int)($_POST['userid'] ?? 0);
      $courseid=(int)($_POST['courseid'] ?? 0);
      $start=$_POST['start_date'] ?? date('Y-m-d');
      $amount=isset($_POST['amount_each']) ? (float)$_POST['amount_each'] : null;
      if($userid<=0 || $courseid<=0) err('Parámetros inválidos');

      $enr=get_enrollment($pdo,$userid,$courseid);
      if(!$enr) err('El alumno no está matriculado en este curso');

      $n=course_modules_count($pdo,$courseid);
      $res=sync_quotas($pdo,(int)$enr['id'],$n,$start,$amount);
      ok($res);
    }

    /* Crear pago (parcial o total) + código de boleta */
    case 'create': {
      $quota_id=(int)($_POST['quota_id'] ?? $_POST['installment_id'] ?? 0); // compat frontend
      if($quota_id<=0) err('Selecciona la cuota a pagar');

      $amount=(float)($_POST['amount'] ?? 0);
      $method=$_POST['method'] ?? 'manual';
      $reference=$_POST['reference'] ?? null;
      $paid_at=$_POST['paid_at'] ?? date('Y-m-d H:i:s');
      $notes=$_POST['notes'] ?? null;
      if($amount<=0) err('Monto inválido');

      // obtener número de cuota
      $st=$pdo->prepare("SELECT cuota_no FROM payment_quota WHERE id=?");
      $st->execute([$quota_id]); $cuota_no=$st->fetchColumn();
      if($cuota_no===false) err('Cuota inválida');

      $pdo->beginTransaction();
      try{
        $ins=$pdo->prepare("INSERT INTO payment_receipt (quota_id, amount, method, reference, paid_at, notes) VALUES (?,?,?,?,?,?)");
        $ins->execute([$quota_id,$amount,$method,$reference,$paid_at,$notes]);
        $rid=(int)$pdo->lastInsertId();

        if(empty($reference)){
          $code=sprintf('B-%s-%d-%d', date('Ymd'), (int)$cuota_no, $rid);
          $pdo->prepare("UPDATE payment_receipt SET reference=? WHERE id=?")->execute([$code,$rid]);
          $reference=$code;
        }

        // actualizar saldo de la cuota
        $pdo->prepare("UPDATE payment_quota SET amount_paid = amount_paid + :m WHERE id=:q")->execute([':m'=>$amount, ':q'=>$quota_id]);

        // recalcular estado
        $st=$pdo->prepare("SELECT amount_due, amount_paid, due_date FROM payment_quota WHERE id=?");
        $st->execute([$quota_id]); $q=$st->fetch(PDO::FETCH_ASSOC);

        $status='pending';
        if($q['amount_paid'] >= $q['amount_due']) $status='paid';
        elseif($q['amount_paid'] > 0)             $status='partial';
        elseif(new DateTime($q['due_date']) < new DateTime(date('Y-m-d'))) $status='overdue';

        $pdo->prepare("UPDATE payment_quota SET status=?, paid_at=CASE WHEN ?='paid' THEN NOW() ELSE paid_at END WHERE id=?")
            ->execute([$status,$status,$quota_id]);

        $pdo->commit();
        ok(['id'=>$rid,'reference'=>$reference,'message'=>'Pago registrado']);
      }catch(Throwable $e){ $pdo->rollBack(); err($e->getMessage(),500); }
    }

    /* Obtener pago para editar */
    case 'get': {
      $id=(int)($_GET['id'] ?? 0);
      if($id<=0) err('ID inválido');

      $st=$pdo->prepare("
        SELECT r.*,
               q.cuota_no, q.enrollment_id,
               u.username, u.firstname, u.lastname,
               c.nombre AS coursename, e.user_id AS userid, e.curso_id AS courseid
        FROM payment_receipt r
        JOIN payment_quota q ON q.id=r.quota_id
        JOIN enrollment e ON e.id=q.enrollment_id
        JOIN users u ON u.id=e.user_id
        JOIN curso c ON c.id=e.curso_id
        WHERE r.id=?
      ");
      $st->execute([$id]); ok(['data'=>$st->fetch(PDO::FETCH_ASSOC)]);
    }

    /* Actualizar pago */
    case 'update': {
      $id=(int)($_POST['id'] ?? 0);
      $amount=(float)($_POST['amount'] ?? 0);
      $method=$_POST['method'] ?? 'manual';
      $reference=$_POST['reference'] ?? null;
      $paid_at=$_POST['paid_at'] ?? date('Y-m-d H:i:s');
      $notes=$_POST['notes'] ?? null;
      if($id<=0) err('ID inválido');
      if($amount<=0) err('Monto inválido');

      // leer pago y cuota
      $st=$pdo->prepare("SELECT quota_id, amount FROM payment_receipt WHERE id=?");
      $st->execute([$id]); $old=$st->fetch(PDO::FETCH_ASSOC);
      if(!$old) err('Pago no encontrado');
      $quota_id=(int)$old['quota_id']; $oldAmount=(float)$old['amount'];

      $pdo->beginTransaction();
      try{
        // revertir monto anterior
        $pdo->prepare("UPDATE payment_quota SET amount_paid = GREATEST(0, amount_paid - :m) WHERE id=:q")
            ->execute([':m'=>$oldAmount, ':q'=>$quota_id]);

        // actualizar pago
        $pdo->prepare("UPDATE payment_receipt SET amount=?, method=?, reference=?, paid_at=?, notes=? WHERE id=?")
            ->execute([$amount,$method,$reference,$paid_at,$notes,$id]);

        // aplicar nuevo monto
        $pdo->prepare("UPDATE payment_quota SET amount_paid = amount_paid + :m WHERE id=:q")
            ->execute([':m'=>$amount, ':q'=>$quota_id]);

        // recalcular estado
        $st=$pdo->prepare("SELECT amount_due, amount_paid, due_date FROM payment_quota WHERE id=?");
        $st->execute([$quota_id]); $q=$st->fetch(PDO::FETCH_ASSOC);

        $status='pending';
        if($q['amount_paid'] >= $q['amount_due']) $status='paid';
        elseif($q['amount_paid'] > 0)             $status='partial';
        elseif(new DateTime($q['due_date']) < new DateTime(date('Y-m-d'))) $status='overdue';

        $pdo->prepare("UPDATE payment_quota SET status=?, paid_at=CASE WHEN ?='paid' THEN NOW() ELSE paid_at END WHERE id=?")
            ->execute([$status,$status,$quota_id]);

        $pdo->commit(); ok(['message'=>'Pago actualizado']);
      }catch(Throwable $e){ $pdo->rollBack(); err($e->getMessage(),500); }
    }

    /* Eliminar pago */
    case 'delete': {
      $id=(int)($_POST['id'] ?? 0);
      if($id<=0) err('ID inválido');

      $st=$pdo->prepare("SELECT quota_id, amount FROM payment_receipt WHERE id=?");
      $st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
      if(!$row) err('Pago no encontrado');

      $pdo->beginTransaction();
      try{
        $pdo->prepare("DELETE FROM payment_receipt WHERE id=?")->execute([$id]);

        $pdo->prepare("UPDATE payment_quota SET amount_paid = GREATEST(0, amount_paid - :m) WHERE id=:q")
            ->execute([':m'=>$row['amount'], ':q'=>$row['quota_id']]);

        $st=$pdo->prepare("SELECT amount_due, amount_paid, due_date FROM payment_quota WHERE id=?");
        $st->execute([$row['quota_id']]); $q=$st->fetch(PDO::FETCH_ASSOC);

        $status='pending';
        if($q['amount_paid'] >= $q['amount_due']) $status='paid';
        elseif($q['amount_paid'] > 0)             $status='partial';
        elseif(new DateTime($q['due_date']) < new DateTime(date('Y-m-d'))) $status='overdue';

        $pdo->prepare("UPDATE payment_quota SET status=? WHERE id=?")->execute([$status,$row['quota_id']]);

        $pdo->commit(); ok(['message'=>'Pago eliminado']);
      }catch(Throwable $e){ $pdo->rollBack(); err($e->getMessage(),500); }
    }

    default: err('Acción no soportada',404);
  }

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
