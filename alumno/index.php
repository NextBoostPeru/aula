<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'alumno') {
  header('Location: ../index.html');
  exit;
}

$tab = $_GET['tab'] ?? 'modulos';
$validTabs = ['modulos', 'asistencias', 'pagos', 'perfil', 'notificaciones'];
if (!in_array($tab, $validTabs, true)) {
  $tab = 'modulos';
}

require __DIR__ . '/partials/layout.php';
