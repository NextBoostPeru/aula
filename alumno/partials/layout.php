<?php
$title = 'Panel del Alumno';
$labels = [
  'modulos' => 'Módulos',
  'asistencias' => 'Asistencias',
  'pagos' => 'Pagos',
  'perfil' => 'Perfil',
  'notificaciones' => 'Notificaciones'
];
$currentLabel = $labels[$tab] ?? 'Módulos';
$user = $_SESSION['user'] ?? ['name' => 'Alumno'];
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
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-600 text-white">
          <i data-feather="user"></i>
        </span>
        <div>
          <h1 class="text-lg font-semibold"><?= htmlspecialchars($title) ?></h1>
          <p class="text-xs text-gray-500">Bienvenido, <?= htmlspecialchars($user['name'] ?? 'Alumno') ?></p>
        </div>
      </div>
      <div class="flex items-center gap-4">
        <a id="notifButton" href="?tab=notificaciones" class="relative p-2 rounded-lg hover:bg-gray-100" aria-label="Notificaciones">
          <i data-feather="bell"></i>
          <span id="notifDot" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-red-500 rounded-full hidden"></span>
        </a>
        <form action="../backend/logout.php" method="post">
          <button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-900 text-white">
            <i data-feather="log-out"></i> Salir
          </button>
        </form>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 py-6 grid grid-cols-12 gap-6">
    <aside class="col-span-12 lg:col-span-3">
      <?php include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <section class="col-span-12 lg:col-span-9 space-y-6">
      <div class="flex items-center justify-between">
        <h2 id="title" class="text-xl font-semibold">
          <?= htmlspecialchars($currentLabel) ?>
        </h2>
        <div id="actions"></div>
      </div>
      <div id="content" class="space-y-6">
        <?php
          $view = __DIR__ . '/../views/' . $tab . '.php';
          if (is_file($view)) {
            include $view;
          }
        ?>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/modal.php'; ?>

  <script src="./assets/app.js"></script>
  <?php
    $asset = __DIR__ . '/../assets/' . $tab . '.js';
    if (is_file($asset)) {
      echo '<script src="./assets/' . htmlspecialchars($tab) . '.js"></script>';
    }
  ?>
  <script>feather.replace();</script>
</body>
</html>
