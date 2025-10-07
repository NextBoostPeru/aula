<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'secretaria') {
  header('Location: ./index.html'); exit;
}
$tab = $_GET['tab'] ?? 'estudiantes';
$valid = ['estudiantes','modulos','asistencias','pagos','notificaciones'];
if (!in_array($tab, $valid, true)) $tab = 'estudiantes';

require __DIR__.'/partials/layout.php';
