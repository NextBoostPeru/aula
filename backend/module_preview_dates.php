<?php
// backend/module_preview_dates.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/_courses_helpers.php';

try {
    must_session_user(); // si prefieres permitir sin sesión, quítalo

    $startDate = isset($_GET['start_date']) ? ymd($_GET['start_date']) : date('Y-m-d');
    $clases = calcularFechasClases($startDate);
    $endDate = date('Y-m-d', strtotime($startDate) + 28*86400);

    echo json_encode([
        'ok'=>true,
        'periodo'=>['inicio'=>$startDate,'fin'=>$endDate],
        'clases'=>[
            ['nro'=>1, 'date'=>$clases[0]],
            ['nro'=>2, 'date'=>$clases[1]],
            ['nro'=>3, 'date'=>$clases[2]],
            ['nro'=>4, 'date'=>$clases[3]],
        ]
    ]);
} catch (Throwable $e) {
    json_error(500, 'Error del servidor');
}
