<?php
// backend/courses_modules_list.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';
require __DIR__ . '/_courses_helpers.php';

try {
    $userId = must_session_user();
    $enrollmentId = (int)($_GET['enrollment_id'] ?? 0);
    if ($enrollmentId<=0) json_error(422, 'enrollment_id requerido');

    // seguridad + curso_id
    $cursoId = curso_id_by_enrollment($pdo, $enrollmentId, $userId);
    if (!$cursoId) json_error(403, 'Matrícula no encontrada o no pertenece al usuario');

    // módulos del curso
    $st = $pdo->prepare("SELECT id, numero, titulo, video_url, pdf_url, slides_url
                         FROM curso_modulo
                         WHERE curso_id=:cid
                         ORDER BY numero ASC");
    $st->execute([':cid'=>$cursoId]);
    $mods = $st->fetchAll() ?: [];

    // actual activo
    $act = current_active_modulo($pdo, $enrollmentId);
    $activeId = $act ? (int)$act['modulo_id'] : 0;

    echo json_encode([
        'ok'=>true,
        'curso_id'=>$cursoId,
        'active_modulo_id'=>$activeId,
        'modulos'=>array_map(function($m){
            return [
                'id'=>(int)$m['id'],
                'numero'=>(int)$m['numero'],
                'titulo'=>$m['titulo'],
                'links'=>[
                    'video'=>$m['video_url'],
                    'pdf'=>$m['pdf_url'],
                    'slides'=>$m['slides_url'],
                ]
            ];
        }, $mods)
    ]);
} catch (Throwable $e) {
    json_error(500, 'Error del servidor');
}
