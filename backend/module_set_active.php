<?php
// backend/module_set_active.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';
require __DIR__ . '/_courses_helpers.php';

try {
    $userId = must_session_user();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error(405, 'Método no permitido');

    $enrollmentId    = (int)($_POST['enrollment_id'] ?? 0);
    $moduloId        = (int)($_POST['modulo_id'] ?? 0);
    $startDate       = isset($_POST['start_date']) ? ymd($_POST['start_date']) : date('Y-m-d');
    $startFromClass  = (int)($_POST['start_from_class'] ?? 1);
    if ($enrollmentId<=0 || $moduloId<=0) json_error(422, 'Datos incompletos');
    if (!in_array($startFromClass, [1,2], true)) json_error(422, 'start_from_class inválido');

    // Validar que la matrícula pertenezca al usuario y obtener curso_id
    $cursoId = curso_id_by_enrollment($pdo, $enrollmentId, $userId);
    if (!$cursoId) json_error(403, 'Matrícula no encontrada o no pertenece al usuario');

    // Validar que módulo pertenece a ese curso
    if (!modulo_belongs_to_curso($pdo, $moduloId, $cursoId)) {
        json_error(422, 'El módulo no pertenece al curso de la matrícula');
    }

    $pdo->beginTransaction();

    // Desactivar otros activos de esta matrícula
    $up = $pdo->prepare("UPDATE alumno_modulo SET is_active=0 WHERE enrollment_id=:eid AND is_active=1");
    $up->execute([':eid'=>$enrollmentId]);

    // Insertar nuevo activo
    $ins = $pdo->prepare("INSERT INTO alumno_modulo (enrollment_id, modulo_id, start_date, start_from_class, is_active)
                          VALUES (:eid, :mid, :sd, :sfc, 1)");
    $ins->execute([
        ':eid'=>$enrollmentId,
        ':mid'=>$moduloId,
        ':sd'=>$startDate,
        ':sfc'=>$startFromClass
    ]);

    $pdo->commit();

    echo json_encode([
        'ok'=>true,
        'msg'=>'Módulo activado',
        'data'=>[
            'enrollment_id'=>$enrollmentId,
            'modulo_id'=>$moduloId,
            'start_date'=>$startDate,
            'start_from_class'=>$startFromClass
        ]
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error(500, 'Error del servidor');
}
