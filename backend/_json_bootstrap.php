<?php
// backend/_json_bootstrap.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

// Nunca mezclar warnings con JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Capturar cualquier echo/print previo y limpiarlo antes de responder
if (!ob_get_level()) ob_start();

// Respuesta JSON segura
function json_reply(array $payload, int $status = 200): void {
    http_response_code($status);
    while (ob_get_level()) { ob_end_clean(); }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Si hay error fatal, responder JSON también
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Error fatal', 'detail' => $e['message'] ?? '']);
    }
});
