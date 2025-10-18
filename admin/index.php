<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
  header('Location: ../index.html');
  exit;
}

$tab = $_GET['tab'] ?? 'resumen';
$valid = ['resumen', 'ventas', 'usuarios'];
if (!in_array($tab, $valid, true)) {
  $tab = 'resumen';
}

require __DIR__.'/partials/layout.php';
