<?php
// backend/auth_login.php
session_start();
header('Content-Type: application/json');

require __DIR__ . '/db.php';

// (Opcional) CORS si pruebas desde otra URL
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Headers: Content-Type');
// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

// Soporte JSON o form-urlencoded
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
    $identifier = trim($data['identifier'] ?? '');
    $password   = (string)($data['password'] ?? '');
} else {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = (string)($_POST['password'] ?? '');
}

if ($identifier === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Completa usuario y contraseña']);
    exit;
}

// Email o DNI
$isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

$sql = $isEmail
    ? "SELECT id, name, email, dni, password_hash, status, role FROM users WHERE email = :id LIMIT 1"
    : "SELECT id, name, email, dni, password_hash, status, role FROM users WHERE dni   = :id LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $identifier]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
    exit;
}

if ((int)$user['status'] !== 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Usuario inactivo']);
    exit;
}

// Verificación con password_hash/password_verify
if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Credenciales inválidas']);
    exit;
}

// Guardar sesión (incluye rol)
$_SESSION['user'] = [
    'id'    => (int)$user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'dni'   => $user['dni'],
    'role'  => $user['role']
];

// Redirección según rol
$redirect = './alumno/'; // alumno por defecto
switch ($user['role']) {
  case 'secretaria': $redirect = './secretaria/'; break;
  case 'docente':    $redirect = './dashboard_docente.html';    break;
  case 'admin':      $redirect = './admin/';                    break;
}

echo json_encode([
    'ok'   => true,
    'msg'  => 'Login exitoso',
    'user' => [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'dni'   => $user['dni'],
        'role'  => $user['role']
    ],
    'redirect' => $redirect
]);
