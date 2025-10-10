<?php
// backend/auth_recover_request.php
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
    $identifier = trim($data['identifier'] ?? '');
} else {
    $identifier = trim($_POST['identifier'] ?? '');
}

if ($identifier === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Ingresa tu DNI o correo']);
    exit;
}

try {
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector CHAR(32) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_selector (selector),
            INDEX idx_expires (expires_at),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'No se pudo inicializar el módulo de recuperación']);
    exit;
}

$isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
$sql = $isEmail
    ? 'SELECT id, email, dni, status FROM users WHERE email = :id LIMIT 1'
    : 'SELECT id, email, dni, status FROM users WHERE dni = :id LIMIT 1';

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $identifier]);
$user = $stmt->fetch();

// Respuesta neutra para evitar enumeración de usuarios
$genericResponse = [
    'ok'  => true,
    'msg' => 'Si la cuenta existe, enviaremos las instrucciones a su correo registrado.'
];

if (!$user || (int)$user['status'] !== 1) {
    echo json_encode($genericResponse);
    exit;
}

try {
    $pdo->beginTransaction();

    $del = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid OR expires_at < NOW()');
    $del->execute([':uid' => (int)$user['id']]);

    $selector = bin2hex(random_bytes(16));
    $verifier = bin2hex(random_bytes(32));
    $tokenHash = password_hash($verifier, PASSWORD_DEFAULT);
    $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

    $ins = $pdo->prepare('INSERT INTO password_resets (user_id, selector, token_hash, expires_at) VALUES (:uid, :selector, :token, :expires)');
    $ins->execute([
        ':uid'      => (int)$user['id'],
        ':selector' => $selector,
        ':token'    => $tokenHash,
        ':expires'  => $expires,
    ]);

    $pdo->commit();

    $resetUrl = sprintf('./reset-password.html?selector=%s&token=%s', urlencode($selector), urlencode($verifier));

    echo json_encode($genericResponse + ['reset_url' => $resetUrl]);
} catch (Throwable $th) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'No se pudo generar el enlace de recuperación']);
}
