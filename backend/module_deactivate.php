<?php
// backend/module_deactivate.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';
require __DIR__ . '/_courses_helpers.php';

try {
    $userId = must_session_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error(405, 'Método no permitido');

    $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
    if ($enrollmentId<=0) json_error(422, 'enrollment_id requerido');

    // seguridad
    $cursoId = curso_id_by_enrollment($pdo, $enrollmentId, $userId);
    if (!$cursoId) json_error(403, 'Matrícula no encontrada o no pertenece al usuario');

    $pdo->prepare("UPDATE alumno_modulo SET is_active=0 WHERE enrollment_id=:eid AND is_active=1")
        ->execute([':eid'=>$enrollmentId]);

    echo json_encode(['ok'=>true, 'msg'=>'Módulo desactivado']);
} catch (Throwable $e) {
    json_error(500, 'Error del servidor');
}
