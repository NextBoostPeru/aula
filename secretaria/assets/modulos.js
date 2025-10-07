// public/secretaria/assets/modulos.js
(function(){
  const box = $('#tablaModulos');
  const btnAdd = $('#btnAddModulo');

  async function list(){
    const aula = $('#selAula')?.value;
    if(!aula){ box.innerHTML='Elige sede y aula.'; return; }

    box.innerHTML = `<div class="bg-white rounded-xl border p-4 text-gray-600">Cargando…</div>`;
    try{
      const r = await api(`../backend/secretaria/modulos_por_aula.php?aula_id=${aula}`);
      const items = r.items || [];

      const rows = items.map(m => {
        const dur = (m.duracion_dias!=null && m.duracion_dias!=='') ? m.duracion_dias : 28;
        const dataEdit = {
          modulo_id: m.modulo_id,
          numero: m.numero,
          modulo_titulo: m.modulo_titulo,
          duracion_dias: dur,
          descripcion: m.descripcion || ''
        };
        return `
        <tr>
          <td class="px-3 py-2">${m.curso_id}</td>
          <td class="px-3 py-2">${m.titulo}</td>
          <td class="px-3 py-2">#${m.numero ?? '-'}</td>
          <td class="px-3 py-2">${m.modulo_titulo ?? '-'}</td>
          <td class="px-3 py-2">${dur} días</td>
          <td class="px-3 py-2">
            <div class="flex flex-wrap gap-2">
              <button class="px-2 py-1 rounded-lg border text-xs" data-prog="${m.modulo_id}">Programar</button>
              <button class="px-2 py-1 rounded-lg border text-xs" data-show="${m.modulo_id}">Ver fechas</button>
              <button class="px-2 py-1 rounded-lg border text-xs text-indigo-600" data-edit='${JSON.stringify(dataEdit)}'>Editar</button>
              <button class="px-2 py-1 rounded-lg border text-xs text-red-600" data-del="${m.modulo_id}">Eliminar</button>
            </div>
          </td>
        </tr>`;
      }).join('');

      box.innerHTML = `
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="border-b"><tr>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">CursoID</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Curso</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Módulo #</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Módulo</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Duración</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Acciones</th>
            </tr></thead>
            <tbody class="divide-y">
              ${rows || '<tr><td colspan="6" class="px-3 py-2 text-gray-500">Sin módulos</td></tr>'}
            </tbody>
          </table>
        </div>`;
      feather.replace();

      bindRowActions();
    }catch(e){
      box.innerHTML = `<div class="text-red-600 text-sm">No se pudo cargar módulos.<br><span class="text-xs">${e.message||e}</span></div>`;
    }
  }

  // --------- Agregar ----------
  async function openAdd(){
    const aulaId = $('#selAula')?.value;
    if(!aulaId) return modal.err('Selecciona sede y aula.');

    let cursos = [];
    try {
      const r = await api(`../backend/secretaria/aula_cursos.php?aula_id=${aulaId}`);
      cursos = r.items || [];
    } catch {}
    if(!cursos.length) return modal.err('Este aula no tiene cursos configurados.');

    modal.open({
      title: 'Agregar módulo',
      bodyHTML: `
        <form id="formModulo" class="space-y-3">
          <div>
            <label class="text-sm text-gray-600">Curso del aula</label>
            <select name="aula_curso_id" id="fmAulaCurso" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
              ${cursos.map(c=>`<option value="${c.aula_curso_id}">${c.titulo}</option>`).join('')}
            </select>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-gray-600">Título del módulo</label>
              <input name="titulo" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" placeholder="Ej. Fundamentos" required>
            </div>
            <div>
              <label class="text-sm text-gray-600">Número (opcional)</label>
              <input name="numero" type="number" min="1" class="w-full mt-1 px-3 py-2 border rounded-xl" placeholder="Autonum si vacío">
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-gray-600">Duración (días)</label>
              <input name="duracion_dias" type="number" min="1" class="w-full mt-1 px-3 py-2 border rounded-xl" placeholder="28 por defecto">
            </div>
            <div>
              <label class="text-sm text-gray-600">Descripción</label>
              <input name="descripcion" class="w-full mt-1 px-3 py-2 border rounded-xl" placeholder="Opcional">
            </div>
          </div>
        </form>
      `,
      primaryLabel: 'Crear',
      onPrimary: async ()=>{
        const fd = new FormData($('#formModulo'));
        try{
          const resp = await api('../backend/secretaria/modulo_crear.php', { method:'POST', body:fd });
          modal.close(); modal.ok(resp.msg || 'Módulo creado');
          await list();
        }catch(err){ modal.err('No se pudo crear el módulo'); }
      }
    });
  }

  // --------- Editar ----------
  function openEdit(data){
    const mod = typeof data === 'string' ? JSON.parse(data) : data;
    modal.open({
      title: 'Editar módulo',
      bodyHTML: `
        <form id="formEditModulo" class="space-y-3">
          <input type="hidden" name="modulo_id" value="${mod.modulo_id}">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-gray-600">Título</label>
              <input name="titulo" value="${escapeHTML(mod.modulo_titulo||'')}" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
            </div>
            <div>
              <label class="text-sm text-gray-600">Número</label>
              <input type="number" name="numero" value="${Number(mod.numero||'')||''}" class="w-full mt-1 px-3 py-2 border rounded-xl">
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-gray-600">Duración (días)</label>
              <input type="number" name="duracion_dias" value="${Number(mod.duracion_dias||28)}" class="w-full mt-1 px-3 py-2 border rounded-xl">
            </div>
            <div>
              <label class="text-sm text-gray-600">Descripción</label>
              <input name="descripcion" value="${escapeHTML(mod.descripcion||'')}" class="w-full mt-1 px-3 py-2 border rounded-xl">
            </div>
          </div>
        </form>
      `,
      primaryLabel: 'Guardar',
      onPrimary: async ()=>{
        const fd = new FormData($('#formEditModulo'));
        try{
          const res = await fetch('../backend/secretaria/modulo_editar.php', { method:'POST', body:fd });
          const txt = await res.text();
          let d;
          try { d = JSON.parse(txt); } catch { throw new Error('Respuesta no válida del servidor:\n'+txt); }
          if (!res.ok || !d.ok) throw new Error(d.msg || ('HTTP '+res.status));
          modal.close(); modal.ok(d.msg || 'Módulo actualizado');
          await list();
        }catch(e){ modal.err(e.message || 'No se pudo editar el módulo'); }
      }
    });
  }

  // --------- Eliminar ----------
  function openDelete(id){
    modal.open({
      title: 'Eliminar módulo',
      bodyHTML: '<div class="text-sm">¿Estás seguro de eliminar este módulo? Se eliminarán también las fechas programadas.</div>',
      primaryLabel: 'Eliminar',
      onPrimary: async ()=>{
        const fd = new FormData(); fd.append('modulo_id', id);
        try{
          const r = await api('../backend/secretaria/modulo_eliminar.php', { method:'POST', body:fd });
          modal.close(); modal.ok(r.msg || 'Módulo eliminado');
          await list();
        }catch(e){ modal.err('No se pudo eliminar el módulo'); }
      }
    });
  }

  function openProgramar(modulo_id){
    modal.open({
      title:'Programar clases',
      bodyHTML:`<div class="space-y-2">
        <label class="text-sm text-gray-600">Fecha inicio</label>
        <input id="startDate" type="date" class="w-full px-3 py-2 border rounded-xl">
        <p class="text-xs text-gray-500">Se generan 4 clases (1 por semana).</p>
      </div>`,
      primaryLabel:'Programar',
      onPrimary: async ()=>{
        const fd = new FormData();
        fd.append('aula_id', $('#selAula').value);
        fd.append('modulo_id', modulo_id);
        fd.append('start_date', $('#startDate').value);
        try{
          const r = await api('../backend/secretaria/programar_clases.php', { method:'POST', body:fd });
          modal.close(); modal.ok(r.msg || 'Clases programadas');
        }catch{ modal.err('No se pudo programar'); }
      }
    });
  }

  async function openFechas(modulo_id){
    try {
      const r = await api(`../backend/secretaria/clases_listar.php?aula_id=${$('#selAula').value}&modulo_id=${modulo_id}`);
      const rows = (r.items||[]).map(x=>`<tr><td class="px-3 py-2">Clase ${x.class_nro}</td><td class="px-3 py-2">${x.class_date}</td></tr>`).join('');
      modal.open({
        title:'Fechas programadas',
        bodyHTML:`<div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="border-b"><tr><th class="px-3 py-2 text-left">Clase</th><th class="px-3 py-2 text-left">Fecha</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="2" class="px-3 py-2 text-gray-500">Sin programación</td></tr>'}</tbody>
          </table>
        </div>`
      });
    } catch(e) { modal.err('No se pudo cargar'); }
  }

  function bindRowActions(){
    $$('#tablaModulos [data-edit]').forEach(b=>{
      b.addEventListener('click', ()=> openEdit(b.getAttribute('data-edit')));
    });
    $$('#tablaModulos [data-del]').forEach(b=>{
      b.addEventListener('click', ()=> openDelete(b.getAttribute('data-del')));
    });
    $$('#tablaModulos [data-prog]').forEach(b=>{
      b.addEventListener('click', ()=> openProgramar(b.getAttribute('data-prog')));
    });
    $$('#tablaModulos [data-show]').forEach(b=>{
      b.addEventListener('click', ()=> openFechas(b.getAttribute('data-show')));
    });
  }

  function escapeHTML(s){
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  btnAdd?.addEventListener('click', openAdd);
  list();
  document.addEventListener('contextChanged', list);
})();
