<?php
// variables de layout
$title = 'Panel de Secretaría';
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
<body class="min-h-screen bg-gray-50">
  <header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="inline-flex w-9 h-9 rounded-xl bg-indigo-600 text-white items-center justify-center">
          <i data-feather="shield"></i>
        </span>
        <h1 class="text-lg font-semibold"><?= htmlspecialchars($title) ?></h1>
      </div>
      <form action="../backend/logout.php" method="post">
        <button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-900 text-white">
          <i data-feather="log-out"></i> Salir
        </button>
      </form>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 py-6 grid grid-cols-12 gap-6">
    <aside class="col-span-12 lg:col-span-3">
      <?php include __DIR__.'/sidebar.php'; ?>
    </aside>

    <section class="col-span-12 lg:col-span-9 space-y-6">
      <div class="flex items-center justify-between">
        <h2 id="title" class="text-xl font-semibold capitalize">
          <?= htmlspecialchars($_GET['tab'] ?? 'estudiantes') ?>
        </h2>
        <div id="actions"></div>
      </div>
      <div id="content" class="space-y-6">
        <?php
          $view = __DIR__.'/../views/'.($_GET['tab'] ?? 'estudiantes').'.php';
          if (is_file($view)) include $view; else include __DIR__.'/../views/estudiantes.php';
        ?>
      </div>
    </section>
  </main>

  <?php include __DIR__.'/modal.php'; ?>

  <!-- JS comunes -->
  <script src="./assets/app.js"></script>
  <!-- JS por vista -->
  <?php
    $tab = $_GET['tab'] ?? 'estudiantes';
    $asset = __DIR__."/../assets/$tab.js";
    if (is_file($asset)) echo '<script src="./assets/'.$tab.'.js"></script>';
  ?>
  <script>feather.replace();</script>
</body>
</html>
