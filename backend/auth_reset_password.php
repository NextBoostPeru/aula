<?php
// backend/auth_reset_password.php
session_start();
header('Content-Type: application/json');

require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
    $selector = trim($data['selector'] ?? '');
    $token    = trim($data['token'] ?? '');
    $password = (string)($data['password'] ?? '');
    $confirm  = (string)($data['confirm'] ?? '');
} else {
    $selector = trim($_POST['selector'] ?? '');
    $token    = trim($_POST['token'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');
}

if ($selector === '' || $token === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Enlace inválido']);
    exit;
}

if ($password === '' || $confirm === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Completa la nueva contraseña']);
    exit;
}

if (!hash_equals($password, $confirm)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Las contraseñas no coinciden']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.consumed_at, u.status '
    . 'FROM password_resets pr '
    . 'JOIN users u ON u.id = pr.user_id '
    . 'WHERE pr.selector = :selector LIMIT 1'
);
$stmt->execute([':selector' => $selector]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Enlace inválido o expirado']);
    exit;
}

if ($record['consumed_at'] !== null || strtotime($record['expires_at']) < time()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'El enlace ya fue utilizado o ha expirado']);
    exit;
}

if (!password_verify($token, $record['token_hash'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Enlace inválido']);
    exit;
}

if ((int)$record['status'] !== 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'La cuenta no está activa']);
    exit;
}

$newHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $upUser = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :uid LIMIT 1');
    $upUser->execute([
        ':hash' => $newHash,
        ':uid'  => (int)$record['user_id'],
    ]);

    $upReset = $pdo->prepare('UPDATE password_resets SET consumed_at = NOW() WHERE id = :id');
    $upReset->execute([':id' => (int)$record['id']]);

    $pdo->commit();

    echo json_encode(['ok' => true, 'msg' => 'Contraseña actualizada correctamente']);
} catch (Throwable $th) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'No se pudo actualizar la contraseña']);
}
