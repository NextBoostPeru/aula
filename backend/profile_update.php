<?php
// backend/profile_update.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';

// Config de subida de archivos (ajusta rutas según tu server)
$UPLOAD_DIR = __DIR__ . '/../uploads/avatars/';
$PUBLIC_BASE = '../uploads/avatars/'; // ruta pública relativa desde /public/ (ajusta si usas otro docroot)
$MAX_SIZE = 2 * 1024 * 1024; // 2MB
$ALLOWED_MIME = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$uid = (int)$_SESSION['user']['id'];

function respond($ok, $msg, $extra=[]) {
  echo json_encode(['ok'=>$ok,'msg'=>$msg]+$extra);
  exit;
}

// Asegurar directorio
if (!is_dir($UPLOAD_DIR)) {
  @mkdir($UPLOAD_DIR, 0775, true);
}

try {
  // Cargamos registro actual (lo usamos para difs y validar)
  $st = $pdo->prepare("SELECT id, name, email, dni, phone, avatar_url, password_hash FROM users WHERE id = :id LIMIT 1");
  $st->execute([':id'=>$uid]);
  $user = $st->fetch();
  if (!$user) respond(false, 'Usuario no encontrado');

  // Recibir datos del form (FormData)
  $name  = trim($_POST['name'] ?? $user['name']);
  $email = trim($_POST['email'] ?? $user['email']);
  $dni   = trim($_POST['dni'] ?? $user['dni']);
  $phone = trim($_POST['phone'] ?? $user['phone']);

  $current_password = (string)($_POST['current_password'] ?? '');
  $new_password     = (string)($_POST['new_password'] ?? '');
  $new_password_confirm = (string)($_POST['new_password_confirm'] ?? '');

  // Validaciones mínimas
  if ($name === '' || $email === '' || $dni === '') respond(false,'Nombre, correo y DNI son obligatorios');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Correo inválido');

  // Unicidad de email / dni (excluyendo el propio usuario)
  $q = $pdo->prepare("SELECT id FROM users WHERE (email=:email OR dni=:dni) AND id<>:id LIMIT 1");
  $q->execute([':email'=>$email, ':dni'=>$dni, ':id'=>$uid]);
  if ($q->fetch()) respond(false, 'Correo o DNI ya están registrados por otro usuario');

  // Manejo de avatar (opcional)
  $avatar_url = $user['avatar_url'];
  if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['avatar'];
    if ($f['error'] !== UPLOAD_ERR_OK) respond(false, 'Error al subir el archivo');
    if ($f['size'] > $MAX_SIZE) respond(false, 'La imagen supera 2MB');

    // Verificar MIME real
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']);
    if (!isset($ALLOWED_MIME[$mime])) respond(false, 'Formato no permitido (usa JPG/PNG/WebP)');

    // Nombre de archivo
    $ext = $ALLOWED_MIME[$mime];
    $rand = bin2hex(random_bytes(8));
    $filename = "u{$uid}_{$rand}.{$ext}";
    $dest = $UPLOAD_DIR . $filename;

    if (!move_uploaded_file($f['tmp_name'], $dest)) respond(false, 'No se pudo guardar el archivo');

    // Puedes borrar la anterior si deseas:
    // if ($avatar_url && file_exists($UPLOAD_DIR . basename($avatar_url))) @unlink($UPLOAD_DIR . basename($avatar_url));

    // Ruta pública (ajústala a tu hosting)
    $avatar_url = $PUBLIC_BASE . $filename;
  }

  // Cambio de contraseña (opcional)
  $set_password = false;
  $new_hash = null;
  if ($current_password !== '' || $new_password !== '' || $new_password_confirm !== '') {
    if ($current_password === '' || $new_password === '' || $new_password_confirm === '') {
      respond(false, 'Para cambiar la contraseña completa los tres campos');
    }
    if ($new_password !== $new_password_confirm) respond(false, 'La nueva contraseña no coincide');
    if (!password_verify($current_password, $user['password_hash'])) respond(false, 'La contraseña actual es incorrecta');
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $set_password = true;
  }

  // Actualizar
  if ($set_password) {
    $sql = "UPDATE users SET name=:name, email=:email, dni=:dni, phone=:phone, avatar_url=:avatar_url, password_hash=:ph WHERE id=:id";
    $params = [
      ':name'=>$name, ':email'=>$email, ':dni'=>$dni, ':phone'=>$phone,
      ':avatar_url'=>$avatar_url, ':ph'=>$new_hash, ':id'=>$uid
    ];
  } else {
    $sql = "UPDATE users SET name=:name, email=:email, dni=:dni, phone=:phone, avatar_url=:avatar_url WHERE id=:id";
    $params = [
      ':name'=>$name, ':email'=>$email, ':dni'=>$dni, ':phone'=>$phone,
      ':avatar_url'=>$avatar_url, ':id'=>$uid
    ];
  }

  $upd = $pdo->prepare($sql);
  $upd->execute($params);

  // Refrescar sesión básica
  $_SESSION['user']['name']  = $name;
  $_SESSION['user']['email'] = $email;
  $_SESSION['user']['dni']   = $dni;

  respond(true, 'Perfil actualizado', ['avatar_url'=>$avatar_url]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
