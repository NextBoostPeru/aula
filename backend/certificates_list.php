<?php
// backend/certificates_list.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$uid = (int)$_SESSION['user']['id'];

try {
  // Cuenta módulos COMPLETADOS del alumno en TODAS sus matrículas.
  // Un módulo se considera "completado" si start_date + 28 días <= hoy.
  $sql = "
    SELECT COUNT(*) AS completados
    FROM alumno_modulo am
    JOIN enrollment e ON e.id = am.enrollment_id
    WHERE e.user_id = :uid
      AND DATE_ADD(am.start_date, INTERVAL 28 DAY) <= CURDATE()
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':uid'=>$uid]);
  $row = $st->fetch();
  $completados = (int)($row ? $row['completados'] : 0);

  $meta = 10; // meta de módulos para habilitar certificado
  $faltan = max(0, $meta - $completados);
  $eligible = ($completados >= $meta);

  // Sugerimos una ruta de PDF por usuario (coloca el archivo allí cuando corresponda)
  // Si prefieres emitir/firmar dinámicamente, solo cambia esta URL.
  $pdf_url = $eligible ? ('../uploads/certificados/cert_'.$uid.'.pdf') : null;

  $data = [[
    'id'          => 'cert10',
    'title'       => 'Certificado por 10 módulos',
    'status'      => $eligible ? 'disponible' : 'no_disponible',
    'requisitos'  => [
      'Acumular 10 módulos completados (cada módulo dura 28 días).'
    ],
    'completados' => $completados,
    'faltan'      => $faltan,
    'link_descarga' => $pdf_url
  ]];

  echo json_encode(['ok'=>true, 'data'=>$data]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
