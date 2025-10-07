<?php
// backend/module_advance.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';
require __DIR__ . '/_courses_helpers.php';

try {
    $userId = must_session_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error(405, 'Método no permitido');

    $enrollmentId   = (int)($_POST['enrollment_id'] ?? 0);
    if ($enrollmentId<=0) json_error(422, 'enrollment_id requerido');

    $startDate      = isset($_POST['start_date']) ? ymd($_POST['start_date']) : date('Y-m-d');
    $startFromClass = (int)($_POST['start_from_class'] ?? 1);
    if (!in_array($startFromClass,[1,2],true)) json_error(422, 'start_from_class inválido');

    // Seguridad: matrícula del usuario
    $cursoId = curso_id_by_enrollment($pdo, $enrollmentId, $userId);
    if (!$cursoId) json_error(403, 'Matrícula no encontrada o no pertenece al usuario');

    // Módulo actual
    $am = current_active_modulo($pdo, $enrollmentId);
    if (!$am) json_error(409, 'No hay módulo activo para avanzar');

    // Buscar siguiente módulo por número
    $next = next_modulo_for_enrollment($pdo, $enrollmentId);
    if (!$next) json_error(409, 'No existe un siguiente módulo');

    $pdo->beginTransaction();

    // cerrar actual
    $pdo->prepare("UPDATE alumno_modulo SET is_active=0 WHERE id=:id")->execute([':id'=>$am['id']]);

    // abrir siguiente
    $pdo->prepare("INSERT INTO alumno_modulo (enrollment_id, modulo_id, start_date, start_from_class, is_active)
                   VALUES (:eid, :mid, :sd, :sfc, 1)")
        ->execute([
            ':eid'=>$enrollmentId,
            ':mid'=>(int)$next['id'],
            ':sd'=>$startDate,
            ':sfc'=>$startFromClass
        ]);

    $pdo->commit();

    echo json_encode([
        'ok'=>true,
        'msg'=>'Avanzaste al siguiente módulo',
        'data'=>[
            'from_modulo_id'=>(int)$am['modulo_id'],
            'to_modulo_id'=>(int)$next['id'],
            'start_date'=>$startDate,
            'start_from_class'=>$startFromClass
        ]
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error(500, 'Error del servidor');
}
