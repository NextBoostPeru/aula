<?php
$tab = $_GET['tab'] ?? 'modulos';
$items = [
  'modulos' => ['list', 'Módulos'],
  'asistencias' => ['check-circle', 'Asistencias'],
  'pagos' => ['credit-card', 'Pagos'],
  'perfil' => ['user', 'Perfil'],
  'notificaciones' => ['bell', 'Notificaciones']
];
?>
<nav class="bg-white rounded-2xl shadow p-2 space-y-1">
  <?php foreach ($items as $key => [$icon, $label]):
    $active = $tab === $key ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'hover:bg-gray-100 text-gray-700';
  ?>
    <a href="?tab=<?= htmlspecialchars($key) ?>" class="w-full flex items-center gap-2 p-3 rounded-xl sidebar-btn <?= $active ?>">
      <i data-feather="<?= htmlspecialchars($icon) ?>"></i> <?= htmlspecialchars($label) ?>
    </a>
  <?php endforeach; ?>
</nav>
