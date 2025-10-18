<?php
$tab = $tab ?? 'resumen';
$items = [
  'resumen' => ['icon' => 'activity', 'label' => 'Resumen'],
  'ventas'  => ['icon' => 'trending-up', 'label' => 'Ventas'],
  'usuarios'=> ['icon' => 'users', 'label' => 'Usuarios'],
];
?>
<nav id="sidebarNav" class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
  <div class="px-5 py-4 border-b">
    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Navegación</p>
  </div>
  <ul class="divide-y divide-slate-100">
    <?php foreach ($items as $key => $item): $isActive = $tab === $key; ?>
      <li>
        <a
          href="?tab=<?= urlencode($key) ?>"
          class="flex items-center gap-3 px-5 py-3 text-sm font-medium transition <?php echo $isActive ? 'bg-slate-900 text-white' : 'hover:bg-slate-100 text-slate-700'; ?>"
        >
          <i data-feather="<?= htmlspecialchars($item['icon']) ?>" class="w-4 h-4"></i>
          <span><?= htmlspecialchars($item['label']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
