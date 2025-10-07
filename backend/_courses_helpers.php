<?php
// backend/_courses_helpers.php

function json_error(int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit;
}

function must_session_user(): int {
    if (!isset($_SESSION['user']['id'])) json_error(401, 'No autorizado');
    return (int)$_SESSION['user']['id'];
}

function ymd(string $d): string {
    // normaliza a Y-m-d o lanza
    $ts = strtotime($d);
    if ($ts === false) throw new Exception('Fecha inválida');
    return date('Y-m-d', $ts);
}

function calcularFechasClases(string $startDate): array {
    $base = strtotime($startDate . ' 00:00:00');
    return [
        date('Y-m-d', $base + 0*86400*7),
        date('Y-m-d', $base + 1*86400*7),
        date('Y-m-d', $base + 2*86400*7),
        date('Y-m-d', $base + 3*86400*7),
    ];
}

function curso_id_by_enrollment(PDO $pdo, int $enrollmentId, int $userId): ?int {
    $sql = "SELECT c.id
            FROM enrollment e
            JOIN aula_curso ac ON ac.id=e.aula_curso_id
            JOIN curso c ON c.id=ac.curso_id
            WHERE e.id=:eid AND e.user_id=:uid";
    $st = $pdo->prepare($sql);
    $st->execute([':eid'=>$enrollmentId, ':uid'=>$userId]);
    $row = $st->fetch();
    return $row ? (int)$row['id'] : null;
}

function modulo_belongs_to_curso(PDO $pdo, int $moduloId, int $cursoId): bool {
    $st = $pdo->prepare("SELECT 1 FROM curso_modulo WHERE id=:mid AND curso_id=:cid");
    $st->execute([':mid'=>$moduloId, ':cid'=>$cursoId]);
    return (bool)$st->fetch();
}

function current_active_modulo(PDO $pdo, int $enrollmentId): ?array {
    $st = $pdo->prepare("SELECT * FROM alumno_modulo WHERE enrollment_id=:eid AND is_active=1 LIMIT 1");
    $st->execute([':eid'=>$enrollmentId]);
    return $st->fetch() ?: null;
}

function next_modulo_for_enrollment(PDO $pdo, int $enrollmentId): ?array {
    // Encuentra el curso y el módulo actual -> devuelve el módulo de número siguiente
    $sql = "SELECT cm2.*
            FROM alumno_modulo am
            JOIN curso_modulo cm ON cm.id=am.modulo_id
            JOIN enrollment e ON e.id=am.enrollment_id
            JOIN aula_curso ac ON ac.id=e.aula_curso_id
            JOIN curso c ON c.id=ac.curso_id
            JOIN curso_modulo cm2 ON cm2.curso_id=c.id AND cm2.numero=cm.numero+1
            WHERE am.enrollment_id=:eid AND am.is_active=1
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':eid'=>$enrollmentId]);
    return $st->fetch() ?: null;
}
