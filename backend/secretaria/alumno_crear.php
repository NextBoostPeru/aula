<?php
// backend/secretaria/alumno_crear.php
session_start();
header('Content-Type: application/json');
require __DIR__.'/../db.php';

try {
  if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'secretaria') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
  }

  $name  = trim((string)($_POST['name'] ?? ''));
  $dni   = trim((string)($_POST['dni'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $pass  = (string)($_POST['password'] ?? ''); // opcional, si vacío se genera

  if ($name === '' || $dni === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Nombre y DNI son obligatorios']);
    exit;
  }

  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Email inválido']);
    exit;
  }

  $colCache = [];
  $colExists = static function(PDO $pdo, string $table, string $column) use (&$colCache): bool {
    $key = $table.'::'.$column;
    if (array_key_exists($key, $colCache)) {
      return $colCache[$key];
    }
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([':table' => $table, ':column' => $column]);
    return $colCache[$key] = (bool)$st->fetchColumn();
  };

  $hasUsername = $colExists($pdo, 'users', 'username');
  $hasDni      = $colExists($pdo, 'users', 'dni');
  $hasEmail    = $colExists($pdo, 'users', 'email');

  $dupConds = [];
  $dupParams = [];
  if ($hasDni) {
    $dupConds[] = 'dni = :dni';
    $dupParams[':dni'] = $dni;
  }
  if ($hasUsername) {
    $dupConds[] = 'username = :username';
    $dupParams[':username'] = $dni;
  }
  if ($email !== '' && $hasEmail) {
    $dupConds[] = 'email = :email';
    $dupParams[':email'] = $email;
  }

  if ($dupConds) {
    $dupSql = 'SELECT 1 FROM users WHERE '.implode(' OR ', $dupConds).' LIMIT 1';
    $dup = $pdo->prepare($dupSql);
    $dup->execute($dupParams);
    if ($dup->fetchColumn()) {
      http_response_code(409);
      echo json_encode(['ok' => false, 'msg' => 'DNI o Email ya existe']);
      exit;
    }
  }

  if ($pass === '') {
    $pass = substr(bin2hex(random_bytes(8)), 0, 8);
  }
  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $firstName = $name;
  $lastName  = '';
  if (strpos($name, ' ') !== false) {
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts) {
      $firstName = array_shift($parts);
      $lastName  = trim(implode(' ', $parts));
    }
  }

  $fallbackEmail = $email !== '' ? $email : ($dni !== '' ? ($dni.'@no.mail') : 'sin-correo@local.test');

  $columns = [];
  $params  = [];
  $addCol = static function(string $col, $value) use (&$columns, &$params) {
    $columns[$col] = $value;
    $params[':'.$col] = $value;
  };

  if ($colExists($pdo, 'users', 'name')) {
    $addCol('name', $name);
  }
  if ($colExists($pdo, 'users', 'firstname')) {
    $addCol('firstname', $firstName);
  }
  if ($colExists($pdo, 'users', 'lastname')) {
    $addCol('lastname', $lastName ?: null);
  }
  if ($colExists($pdo, 'users', 'nombres')) {
    $addCol('nombres', $firstName);
  }
  if ($colExists($pdo, 'users', 'apellidos')) {
    $addCol('apellidos', $lastName ?: null);
  }
  if ($hasUsername) {
    $addCol('username', $dni);
  }
  if ($hasDni) {
    $addCol('dni', $dni);
  }
  if ($hasEmail) {
    $addCol('email', $fallbackEmail);
  }
  if ($colExists($pdo, 'users', 'phone')) {
    $addCol('phone', $phone !== '' ? $phone : null);
  }
  if ($colExists($pdo, 'users', 'password_hash')) {
    $addCol('password_hash', $hash);
  }
  if ($colExists($pdo, 'users', 'password')) {
    $addCol('password', $hash);
  }
  if ($colExists($pdo, 'users', 'status')) {
    $addCol('status', 1);
  }
  if ($colExists($pdo, 'users', 'role')) {
    $addCol('role', 'alumno');
  }
  if ($colExists($pdo, 'users', 'type')) {
    $addCol('type', 'alumno');
  }
  if ($colExists($pdo, 'users', 'user_type')) {
    $addCol('user_type', 'student');
  }
  if ($colExists($pdo, 'users', 'created_at')) {
    $addCol('created_at', date('Y-m-d H:i:s'));
  }
  if ($colExists($pdo, 'users', 'updated_at')) {
    $addCol('updated_at', date('Y-m-d H:i:s'));
  }
  if ($colExists($pdo, 'users', 'email_verified_at')) {
    $addCol('email_verified_at', null);
  }
  if ($colExists($pdo, 'users', 'remember_token')) {
    $addCol('remember_token', bin2hex(random_bytes(10)));
  }

  if (!$columns) {
    throw new RuntimeException('No se pudo determinar columnas para insertar al usuario');
  }

  $insertCols = array_keys($columns);
  $placeholders = array_map(static function($c){ return ':'.$c; }, $insertCols);

  $sql = 'INSERT INTO users ('.implode(', ', $insertCols).') VALUES ('.implode(', ', $placeholders).')';
  $st = $pdo->prepare($sql);
  $st->execute($params);

  echo json_encode([
    'ok'            => true,
    'user_id'       => (int)$pdo->lastInsertId(),
    'temp_password' => $pass,
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  error_log('[alumno_crear] '.$e->getMessage());
  echo json_encode(['ok' => false, 'msg' => 'Error al crear alumno']);
}
