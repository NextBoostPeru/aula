<?php
// backend/session_check.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'auth' => false]);
    exit;
}

echo json_encode(['ok' => true, 'auth' => true, 'user' => $_SESSION['user']]);
