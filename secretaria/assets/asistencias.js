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
        <button id="btnResetClase" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border text-red-600 hover:bg-red-50">
          Resetear clase
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
      const r = await apiSecretaria('modulos_por_aula.php', { searchParams: { aula_id: aula } });
      (r.items||[]).forEach((m)=>{
        const option = document.createElement('option');
        option.value = m.modulo_id ?? '';
        option.textContent = `[${m.titulo}] Módulo #${m.numero} - ${m.modulo_titulo}`;
        selModulo.appendChild(option);
      });
    } catch (error) {
      console.error('No se pudieron cargar los módulos del aula', error);
    }
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
      const r = await apiSecretaria('attendance_roster.php', { searchParams: { aula_id: aula, modulo_id: mid, class_nro: cls } });
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
          <td class="px-3 py-2">${escapeHTML(it.user?.name ?? '')} <div class="text-xs text-gray-500">DNI: ${escapeHTML(it.user?.dni ?? '')}</div></td>
          <td class="px-3 py-2">${escapeHTML(it.class_date ?? '')}</td>
          <td class="px-3 py-2">${badge(it.status)}</td>
          <td class="px-3 py-2">
            <div class="flex flex-wrap gap-2">
              <button data-mark="${encodeDataAttr({ enr: it.enrollment_id, st: 'asistio' })}" class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs">Asistió</button>
              <button data-mark="${encodeDataAttr({ enr: it.enrollment_id, st: 'tarde' })}"   class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs">Tarde</button>
              <button data-mark="${encodeDataAttr({ enr: it.enrollment_id, st: 'falta' })}"   class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs">Falta</button>
              <button data-mark="${encodeDataAttr({ enr: it.enrollment_id, st: 'justificado' })}" class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs">Justificado</button>
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
          const payload = decodeDataAttr(btn.dataset.mark);
          if (!payload) return;
          const fd = new FormData();
          fd.append('enrollment_id', payload.enr);
          fd.append('modulo_id', $('#selModulo').value);
          fd.append('class_nro', $('#selClase').value);
          fd.append('status', payload.st);
          btn.disabled = true;
          try {
            await apiSecretaria('attendance_mark.php',{method:'POST', body:fd});
            await cargarLista();
          } catch(error){
            console.error('No se pudo marcar asistencia', error);
            modal.err('No se pudo marcar');
            btn.disabled=false;
          }
        });
      });

    } catch(error) {
      console.error('No se pudo cargar la lista de asistencia', error);
      tbl.innerHTML = '<div class="text-red-600">No se pudo cargar la lista.</div>';
    }
  }

  function confirmarMarcarTodos(){
    const aula = $('#selAula')?.value;
    const modulo = $('#selModulo')?.value;
    if(!aula || !modulo) return modal.err('Selecciona sede, aula y módulo.');
    if(!$$('#attTable [data-mark]').length) return modal.err('Primero carga la lista de asistencia.');

    modal.open({
      title:'Confirmar',
      bodyHTML:'<div class="text-sm">¿Marcar <b>Asistió</b> para todos los alumnos cargados?</div>',
      primaryLabel:'Confirmar',
      onPrimary: async ()=>{
        const btns = $$('#attTable [data-mark]');
        for(const b of btns){
          const p = decodeDataAttr(b.dataset.mark);
          if(!p || p.st!=='asistio') continue;
          const fd = new FormData();
          fd.append('enrollment_id', p.enr);
          fd.append('modulo_id', modulo);
          fd.append('class_nro', $('#selClase').value);
          fd.append('status', 'asistio');
          try{
            await apiSecretaria('attendance_mark.php',{method:'POST', body:fd});
          }catch(error){
            console.error('No se pudo marcar asistencia masiva', error);
          }
        }
        modal.close();
        modal.ok('Lista marcada como Asistió');
        await cargarLista();
      }
    });
  }

  function confirmarResetClase(){
    const aula = $('#selAula')?.value;
    const modulo = $('#selModulo')?.value;
    const clase = $('#selClase')?.value || 1;
    if(!aula || !modulo) return modal.err('Selecciona sede, aula y módulo.');

    modal.open({
      title:'Resetear asistencia',
      bodyHTML:'<div class="text-sm">¿Eliminar las asistencias marcadas para esta clase? Esta acción dejará la lista como “Sin marcar”.</div>',
      primaryLabel:'Resetear',
      onPrimary: async ()=>{
        const fd = new FormData();
        fd.append('aula_id', aula);
        fd.append('modulo_id', modulo);
        fd.append('class_nro', clase);
        try{
          await apiSecretaria('attendance_reset.php',{method:'POST', body:fd});
          modal.close();
          modal.ok('Asistencia reseteada');
          await cargarLista();
        }catch(error){
          console.error('No se pudo resetear la asistencia', error);
          modal.err('No se pudo resetear la asistencia.');
        }
      }
    });
  }

  // Montaje inicial de la vista
  const container = $('#content');
  container.innerHTML = rootHTML;
  feather.replace();

  // Eventos de UI
  $('#btnCargar')?.addEventListener('click', cargarLista);
  $('#btnMarcarTodos')?.addEventListener('click', confirmarMarcarTodos);
  $('#btnResetClase')?.addEventListener('click', confirmarResetClase);

  // Primer llenado de módulos y render
  await loadModulos();

  // Reaccionar a cambios de sede/aula
  document.addEventListener('contextChanged', async ()=>{
    await loadModulos();
    $('#attTable').innerHTML = 'Selecciona módulo y clase, luego “Cargar lista”.';
  });
})();
