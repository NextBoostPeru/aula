<?php
  // lee tab y sede_id (si viene en la URL)
  $tab = $_GET['tab'] ?? 'estudiantes';
  $sede_id = isset($_GET['sede_id']) ? trim($_GET['sede_id']) : '';
  // helper para anexar sede_id a los enlaces
  $sede_qs = $sede_id !== '' ? '&sede_id=' . urlencode($sede_id) : '';
?>
<div class="bg-white rounded-2xl shadow p-4 space-y-3">
  <div>
    <label class="text-sm text-gray-600">Sede</label>
    <select id="selSede" class="w-full mt-1 px-3 py-2 border rounded-xl"></select>
  </div>
  <div>
    <label class="text-sm text-gray-600">Aula</label>
    <select id="selAula" class="w-full mt-1 px-3 py-2 border rounded-xl"></select>
  </div>
</div>

<nav id="sidebarNav" class="bg-white rounded-2xl shadow p-2 mt-4 space-y-1">
  <?php
    $items = [ 
      'estudiantes'   => ['users','Estudiantes'],
      'modulos'       => ['list','Modulos'],
      'asistencias'   => ['check-circle','Asistencias'],
       'matriculas'         => ['credit-card','Matriculas'],
      'pagos'         => ['credit-card','Pagos'],
      'caja'          => ['credit-card','Caja'],
      'notificaciones'=> ['bell','Notificaciones'],
    ];
    foreach ($items as $k=>$v):
      $active = $tab===$k ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : '';
  ?>
  <!-- añadimos sede_id si existe -->
  <a href="?tab=<?= $k . $sede_qs ?>"
     class="w-full flex items-center gap-2 p-3 rounded-xl sidebar-btn <?= $active ?>">
    <i data-feather="<?= $v[0] ?>"></i> <?= $v[1] ?>
  </a>
  <?php endforeach; ?>
</nav>

<script>
  // === Mantener sede_id en los enlaces y en la URL cuando cambie la sede ===
  (function(){
    const selSede = document.getElementById('selSede');
    const sidebar = document.getElementById('sidebarNav');

    // lee sede_id actual de la URL
    const qs = new URLSearchParams(location.search);
    const currentSede = qs.get('sede_id') || '';

    // si ya tienes un script que llena #selSede, solo asegúrate de setear su valor:
    // cuando termine de poblar, selecciona la sede actual
    function setSelectedSedeOnce(){
      if (!selSede) return;
      if (currentSede && selSede.querySelector(`option[value="${currentSede}"]`)) {
        selSede.value = currentSede;
      }
    }
    // Si tu población es async, llama setSelectedSedeOnce() al final de tu carga de sedes.
    // Aquí lo intentamos una vez y luego un pequeño retry por si llega tarde:
    setSelectedSedeOnce();
    setTimeout(setSelectedSedeOnce, 500);

    // al cambiar sede, actualizamos enlaces y la URL actual
    selSede?.addEventListener('change', ()=>{
      const sede = selSede.value || '';
      // 1) reescribir enlaces del sidebar con sede_id
      sidebar?.querySelectorAll('a[href]').forEach(a=>{
        const url = new URL(a.getAttribute('href'), location.href);
        if (sede) url.searchParams.set('sede_id', sede);
        else url.searchParams.delete('sede_id');
        a.setAttribute('href', url.pathname + '?' + url.searchParams.toString());
      });
      // 2) refrescar la URL actual (misma pestaña) para que contenido/JS tomen la nueva sede
      if (sede) qs.set('sede_id', sede); else qs.delete('sede_id');
      // preservamos el tab actual
      const tab = qs.get('tab') || 'estudiantes';
      qs.set('tab', tab);
      location.search = qs.toString();
    });
  })();
</script>
