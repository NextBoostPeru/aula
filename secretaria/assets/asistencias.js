// public/secretaria/assets/asistencias.js
(async function(){
  const rootHTML = `
    <div class="bg-white rounded-2xl shadow p-4 space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="text-sm text-gray-600">Módulo</label><br/>
          <select id="selModulo" class="px-3 py-2 border rounded-xl">
            <option value="">Selecciona módulo...</option>
          </select>
        </div>
        <div>
          <label class="text-sm text-gray-600">Clase</label><br/>
          <select id="selClase" class="px-3 py-2 border rounded-xl">
            <option value="1">Clase 1</option>
            <option value="2">Clase 2</option>
            <option value="3">Clase 3</option>
            <option value="4">Clase 4</option>
          </select>
        </div>
        <button id="btnCargar" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border hover:bg-gray-50">
          Cargar lista
        </button>
        <button id="btnMarcarTodos" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border hover:bg-gray-50">
          Marcar todo Asistió
        </button>
      </div>
      <div id="attTable" class="text-sm text-gray-700">Selecciona módulo y clase, luego “Cargar lista”.</div>
    </div>`;

  async function loadModulos() {
    const aula = $('#selAula')?.value;
    const selModulo = $('#selModulo');
    if (!aula || !selModulo) return;
    selModulo.innerHTML = '<option value="">Selecciona módulo...</option>';
    try {
      const r = await api(`../backend/secretaria/modulos_por_aula.php?aula_id=${aula}`);
      (r.items||[]).forEach(m=>{
        selModulo.insertAdjacentHTML('beforeend',
          `<option value="${m.modulo_id}">[${m.titulo}] Módulo #${m.numero} - ${m.modulo_titulo}</option>`);
      });
    } catch {}
  }

  async function cargarLista(){
    const aula = $('#selAula')?.value;
    const mid  = $('#selModulo')?.value;
    const cls  = $('#selClase')?.value || 1;
    const tbl  = $('#attTable');
    if(!aula){ tbl.innerHTML = 'Elige sede y aula.'; return; }
    if(!mid){  tbl.innerHTML = '<div class="text-red-600">Selecciona un módulo.</div>'; return; }

    tbl.innerHTML = `<div class="bg-white rounded-xl border p-4 text-gray-600">Cargando…</div>`;
    try {
      const r = await api(`../backend/secretaria/attendance_roster.php?aula_id=${aula}&modulo_id=${mid}&class_nro=${cls}`);
      const items = r.items || [];
      if (!items.length) { tbl.innerHTML = '<div class="text-gray-600">No hay alumnos con el módulo activo.</div>'; return; }
      const badge = (st) => {
        if (!st) return '<span class="inline-block px-2 py-0.5 rounded-lg border bg-gray-100 text-gray-600 text-xs">Sin marcar</span>';
        if (st==='asistio') return '<span class="inline-block px-2 py-0.5 rounded-lg border bg-green-100 text-green-700 text-xs">Asistió</span>';
        if (st==='tarde')   return '<span class="inline-block px-2 py-0.5 rounded-lg border bg-yellow-100 text-yellow-700 text-xs">Tarde</span>';
        if (st==='falta')   return '<span class="inline-block px-2 py-0.5 rounded-lg border bg-red-100 text-red-700 text-xs">Falta</span>';
        return '<span class="inline-block px-2 py-0.5 rounded-lg border bg-blue-100 text-blue-700 text-xs">Justificado</span>';
      };
      const rows = items.map(it => `
        <tr>
          <td class="px-3 py-2">${it.user.name} <div class="text-xs text-gray-500">DNI: ${it.user.dni}</div></td>
          <td class="px-3 py-2">${it.class_date}</td>
          <td class="px-3 py-2">${badge(it.status)}</td>
          <td class="px-3 py-2">
            <div class="flex flex-wrap gap-2">
              <button data-mark='${JSON.stringify({enr:it.enrollment_id, st:"asistio"})}' class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs">Asistió</button>
              <button data-mark='${JSON.stringify({enr:it.enrollment_id, st:"tarde"})}'   class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs">Tarde</button>
              <button data-mark='${JSON.stringify({enr:it.enrollment_id, st:"falta"})}'   class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs">Falta</button>
              <button data-mark='${JSON.stringify({enr:it.enrollment_id, st:"justificado"})}' class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs">Justificado</button>
            </div>
          </td>
        </tr>`).join('');
      tbl.innerHTML = `
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="border-b">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Alumno</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Fecha</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Estado</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y">${rows}</tbody>
          </table>
        </div>`;
      feather.replace();

      // marcar individual
      $$('#attTable [data-mark]').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
          const payload = JSON.parse(btn.getAttribute('data-mark'));
          const fd = new FormData();
          fd.append('enrollment_id', payload.enr);
          fd.append('modulo_id', $('#selModulo').value);
          fd.append('class_nro', $('#selClase').value);
          fd.append('status', payload.st);
          btn.disabled = true;
          try {
            const r = await api('../backend/secretaria/attendance_mark.php',{method:'POST', body:fd});
            await cargarLista();
          } catch(e){ modal.err('No se pudo marcar'); btn.disabled=false; }
        });
      });

      // marcar todos asistió
      $('#btnMarcarTodos')?.addEventListener('click', ()=>{
        modal.open({
          title:'Confirmar',
          bodyHTML:'<div class="text-sm">¿Marcar <b>Asistió</b> para todos los alumnos cargados?</div>',
          primaryLabel:'Confirmar',
          onPrimary: async ()=>{
            const btns = $$('#attTable [data-mark]');
            for(const b of btns){
              const p = JSON.parse(b.getAttribute('data-mark'));
              if(p.st!=='asistio') continue;
              const fd = new FormData();
              fd.append('enrollment_id', p.enr);
              fd.append('modulo_id', $('#selModulo').value);
              fd.append('class_nro', $('#selClase').value);
              fd.append('status', 'asistio');
              try{ await fetch('../backend/secretaria/attendance_mark.php',{method:'POST', body:fd}); }catch{}
            }
            modal.close(); modal.ok('Lista marcada como Asistió');
            await cargarLista();
          }
        });
      });

    } catch(e) {
      tbl.innerHTML = '<div class="text-red-600">No se pudo cargar la lista.</div>';
    }
  }

  // Montaje inicial de la vista
  const container = $('#content');
  container.innerHTML = rootHTML;
  feather.replace();

  // Eventos de UI
  $('#btnCargar')?.addEventListener('click', cargarLista);

  // Primer llenado de módulos y render
  await loadModulos();

  // Reaccionar a cambios de sede/aula
  document.addEventListener('contextChanged', async ()=>{
    await loadModulos();
    $('#attTable').innerHTML = 'Selecciona módulo y clase, luego “Cargar lista”.';
  });
})();
