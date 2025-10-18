<?php
$title = 'Panel Administrativo';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="min-h-screen bg-slate-50">
  <header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="inline-flex w-9 h-9 rounded-xl bg-slate-900 text-white items-center justify-center">
          <i data-feather="settings"></i>
        </span>
        <div>
          <h1 class="text-lg font-semibold"><?= htmlspecialchars($title) ?></h1>
          <p class="text-xs text-slate-500">Supervisa métricas, ventas y usuarios.</p>
        </div>
      </div>
      <form action="../backend/logout.php" method="post" class="hidden lg:block">
        <button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900 text-white hover:bg-black">
          <i data-feather="log-out"></i>
          <span>Salir</span>
        </button>
      </form>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 py-6 grid grid-cols-12 gap-6">
    <aside class="col-span-12 lg:col-span-3 space-y-6">
      <?php include __DIR__.'/sidebar.php'; ?>
      <form action="../backend/logout.php" method="post" class="lg:hidden">
        <button class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-slate-900 text-white">
          <i data-feather="log-out"></i>
          <span>Salir</span>
        </button>
      </form>
    </aside>

    <section class="col-span-12 lg:col-span-9 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 id="title" class="text-xl font-semibold capitalize">resumen</h2>
          <p class="text-sm text-slate-500" id="subtitle"></p>
        </div>
        <div id="actions" class="flex flex-wrap gap-2"></div>
      </div>
      <div id="content" class="space-y-6">
        <?php
          $view = __DIR__.'/../views/'.($tab ?? 'resumen').'.php';
          if (is_file($view)) {
            include $view;
          } else {
            include __DIR__.'/../views/resumen.php';
          }
        ?>
      </div>
    </section>
  </main>

  <?php include __DIR__.'/modal.php'; ?>

  <script src="./assets/app.js"></script>
  <?php
    $asset = __DIR__."/../assets/{$tab}.js";
    if (is_file($asset)) {
      echo '<script src="./assets/'.htmlspecialchars($tab).'.js"></script>';
    }
  ?>
  <script>feather.replace();</script>
</body>
</html>
