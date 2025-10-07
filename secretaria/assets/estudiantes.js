// public/secretaria/assets/estudiantes.js
(async function(){
  const box = $('#tablaEstudiantes');

  async function renderLista(){
    const aula = $('#selAula')?.value;
    if(!aula){ box.innerHTML='Elige sede y aula.'; return; }
    box.innerHTML = `<div class="bg-white rounded-xl border p-4 text-gray-600">Cargando…</div>`;
    try{
      const r = await api(`../backend/secretaria/estudiantes_por_aula.php?aula_id=${aula}`);
      const rows = (r.items||[]).map(x=>`
        <tr>
          <td class="px-3 py-2">${x.user_id}</td>
          <td class="px-3 py-2">${x.name}</td>
          <td class="px-3 py-2">${x.dni}</td>
          <td class="px-3 py-2">${x.email}</td>
          <td class="px-3 py-2 text-xs text-gray-500">enr:${x.enrollment_id}</td>
          <td class="px-3 py-2">
            <div class="flex flex-wrap gap-2">
              <button class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs"
                      data-edit='${JSON.stringify({uid:x.user_id})}'>Editar</button>
              <button class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs text-red-600"
                      data-baja='${JSON.stringify({enr:x.enrollment_id, name:x.name})}'>Quitar del aula</button>
            </div>
          </td>
        </tr>`).join('');
      box.innerHTML = `
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="border-b">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">ID</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Nombre</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">DNI</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Correo</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Matrícula</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y">${rows || '<tr><td colspan="6" class="px-3 py-2 text-gray-500">Sin estudiantes</td></tr>'}</tbody>
          </table>
        </div>`;
      feather.replace();

      // acciones
      $$('#tablaEstudiantes [data-edit]').forEach(btn=>{
        btn.addEventListener('click', ()=> openEditarModal(JSON.parse(btn.getAttribute('data-edit')).uid));
      });
      $$('#tablaEstudiantes [data-baja]').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const {enr, name} = JSON.parse(btn.getAttribute('data-baja'));
          openBajaModal(enr, name);
        });
      });

    }catch{
      box.innerHTML = `<div class="text-red-600 text-sm">Error cargando estudiantes</div>`;
    }
  }

  async function openMatricularModal(){
    const aulaId = $('#selAula')?.value;
    if(!aulaId) return modal.err('Selecciona sede y aula.');

    // cursos del aula
    let cursos=[];
    try{
      const r = await api(`../backend/secretaria/aula_cursos.php?aula_id=${aulaId}`);
      cursos = r.items || [];
    }catch{}
    if(!cursos.length) return modal.err('Este aula no tiene cursos asignados.');

    modal.open({
      title: 'Agregar/Matricular Alumno',
      bodyHTML: `
        <form id="formMat" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
              <label class="text-sm text-gray-600">Buscar por DNI o Email</label>
              <div class="flex gap-2">
                <input id="qBuscar" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" placeholder="DNI o email">
                <button id="btnBuscar" type="button" class="mt-1 px-3 py-2 rounded-xl border hover:bg-gray-50">Buscar</button>
              </div>
              <p class="text-xs text-gray-500 mt-1">Si no existe, completa el formulario y se creará automáticamente.</p>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-gray-600">Nombre</label>
              <input name="name" id="fName" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
            </div>
            <div>
              <label class="text-sm text-gray-600">DNI</label>
              <input name="dni" id="fDni" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
            </div>
            <div>
              <label class="text-sm text-gray-600">Email</label>
              <input name="email" id="fEmail" type="email" class="w-full mt-1 px-3 py-2 border rounded-xl">
            </div>
            <div>
              <label class="text-sm text-gray-600">Celular</label>
              <input name="phone" id="fPhone" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl">
            </div>
          </div>
          <div>
            <label class="text-sm text-gray-600">Curso del aula</label>
            <select name="aula_curso_id" id="fAulaCurso" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
              ${cursos.map(c=>`<option value="${c.aula_curso_id}">${c.titulo}</option>`).join('')}
            </select>
          </div>
          <input type="hidden" id="fUserId" name="user_id" value="">
          <div id="auxMsg" class="text-xs text-gray-500"></div>
        </form>`,
      primaryLabel: 'Guardar y Matricular',
      onPrimary: async ()=>{
        const form = $('#formMat');
        const fd   = new FormData(form);
        let userId = $('#fUserId').value;

        if(!userId){
          try{
            const r = await fetch('../backend/secretaria/alumno_crear.php', { method:'POST', body:fd });
            const d = await r.json();
            if(!r.ok || !d.ok) return modal.err(d.msg || 'No se pudo crear el alumno');
            userId = d.user_id; $('#fUserId').value = userId;
            $('#auxMsg').innerHTML = d.temp_password
              ? '<span class="text-green-700">Alumno creado. Contraseña: <b>'+d.temp_password+'</b></span>'
              : '<span class="text-green-700">Alumno creado.</span>';
          }catch{ return modal.err('Error de red al crear alumno'); }
        }

        const enrFd = new FormData();
        enrFd.append('user_id', userId);
        enrFd.append('aula_curso_id', $('#fAulaCurso').value);
        try{
          const r = await fetch('../backend/secretaria/matricular.php', { method:'POST', body: enrFd });
          const d = await r.json();
          if(!r.ok || !d.ok) return modal.err(d.msg || 'No se pudo matricular');
          modal.close(); modal.ok('Matriculado correctamente');
          await renderLista();
        }catch{ modal.err('Error de red al matricular'); }
      }
    });

    $('#btnBuscar')?.addEventListener('click', async ()=>{
      const q = $('#qBuscar').value.trim();
      if(!q) return;
      try{
        const r = await api(`../backend/secretaria/alumno_buscar.php?q=${encodeURIComponent(q)}`);
        const u = r.user;
        if(!u){
          $('#fUserId').value=''; 
          $('#auxMsg').innerHTML='<span class="text-amber-700">No se encontró. Completa el formulario para crearlo.</span>';
          return;
        }
        $('#fUserId').value = u.id;
        $('#fName').value   = u.name || '';
        $('#fDni').value    = u.dni  || '';
        $('#fEmail').value  = u.email|| '';
        $('#fPhone').value  = u.phone|| '';
        $('#auxMsg').innerHTML = '<span class="text-green-700">Usuario existente. Se usará ID '+u.id+'</span>';
      }catch{
        $('#auxMsg').innerHTML='<span class="text-red-700">Error al buscar</span>';
      }
    });
  }

  // --------- Editar datos de alumno ---------
  async function openEditarModal(user_id){
    try{
      const r = await api(`../backend/secretaria/alumno_detalle.php?user_id=${user_id}`);
      const u = r.user;
      modal.open({
        title: 'Editar alumno',
        bodyHTML: `
          <form id="formEdit" class="space-y-3">
            <input type="hidden" name="user_id" value="${u.id}">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="text-sm text-gray-600">Nombre</label>
                <input name="name" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" value="${u.name||''}" required>
              </div>
              <div>
                <label class="text-sm text-gray-600">DNI</label>
                <input name="dni" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" value="${u.dni||''}">
              </div>
              <div>
                <label class="text-sm text-gray-600">Email</label>
                <input name="email" type="email" class="w-full mt-1 px-3 py-2 border rounded-xl" value="${u.email||''}">
              </div>
              <div>
                <label class="text-sm text-gray-600">Celular</label>
                <input name="phone" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" value="${u.phone||''}">
              </div>
            </div>
          </form>`,
        primaryLabel: 'Guardar',
        onPrimary: async ()=>{
          const fd = new FormData($('#formEdit'));
          try{
            const res = await fetch('../backend/secretaria/alumno_actualizar.php',{method:'POST', body:fd});
            const d = await res.json();
            if(!res.ok || !d.ok) return modal.err(d.msg || 'No se pudo actualizar');
            modal.close(); modal.ok('Datos actualizados');
            await renderLista();
          }catch{ modal.err('Error de red'); }
        }
      });
    }catch{ modal.err('No se pudo cargar datos'); }
  }

  // --------- Baja de matrícula (manteniendo historial) ---------
  function openBajaModal(enrollment_id, name){
    modal.open({
      title: 'Quitar del aula',
      bodyHTML: `<div class="text-sm">
        ¿Deseas quitar a <b>${name}</b> de esta aula?<br>
        <span class="text-gray-600">La matrícula quedará en historial.</span>
      </div>`,
      primaryLabel: 'Confirmar',
      onPrimary: async ()=>{
        const fd = new FormData(); fd.append('enrollment_id', enrollment_id);
        try{
          const res = await fetch('../backend/secretaria/matricula_baja.php',{method:'POST', body:fd});
          const d = await res.json();
          if(!res.ok || !d.ok) return modal.err(d.msg || 'No se pudo dar de baja');
          modal.close(); modal.ok('Alumno retirado');
          await renderLista();
        }catch{ modal.err('Error de red'); }
      }
    });
  }

  // botón de layout
  $('#btnAdd')?.addEventListener('click', openMatricularModal);

  // primer render
  await renderLista();

  // re-render al cambiar sede/aula
  document.addEventListener('contextChanged', renderLista);
})();
