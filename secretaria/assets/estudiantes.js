// public/secretaria/assets/estudiantes.js
(async function(){
  const box = $('#tablaEstudiantes');

  async function renderLista(){
    const aula = $('#selAula')?.value;
    if(!aula){ box.innerHTML='Elige sede y aula.'; return; }
    box.innerHTML = `<div class="bg-white rounded-xl border p-4 text-gray-600">Cargando…</div>`;
    try{
      const r = await apiSecretaria('estudiantes_por_aula.php', { searchParams: { aula_id: aula } });
      const rows = (r.items||[]).map(x=>{
        const editPayload = encodeDataAttr({ uid: x.user_id });
        const bajaPayload = encodeDataAttr({ enr: x.enrollment_id, name: x.name });
        return `
        <tr>
          <td class="px-3 py-2">${escapeHTML(x.user_id ?? '')}</td>
          <td class="px-3 py-2">${escapeHTML(x.name ?? '')}</td>
          <td class="px-3 py-2">${escapeHTML(x.dni ?? '')}</td>
          <td class="px-3 py-2">${escapeHTML(x.email ?? '')}</td>
          <td class="px-3 py-2 text-xs text-gray-500">enr:${escapeHTML(x.enrollment_id ?? '')}</td>
          <td class="px-3 py-2">
            <div class="flex flex-wrap gap-2">
              <button class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs"
                      data-edit="${editPayload}">Editar</button>
              <button class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs text-red-600"
                      data-baja="${bajaPayload}">Quitar del aula</button>
            </div>
          </td>
        </tr>`;
      }).join('');
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
        btn.addEventListener('click', ()=>{
          const data = decodeDataAttr(btn.dataset.edit);
          if (data?.uid) openEditarModal(data.uid);
        });
      });
      $$('#tablaEstudiantes [data-baja]').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const data = decodeDataAttr(btn.dataset.baja);
          if (!data) return;
          openBajaModal(data.enr, data.name);
        });
      });

    }catch(error){
      console.error('No se pudo cargar la lista de estudiantes', error);
      box.innerHTML = `<div class="text-red-600 text-sm">Error cargando estudiantes</div>`;
    }
  }

  async function openMatricularModal(){
    const aulaId = $('#selAula')?.value;
    if(!aulaId) return modal.err('Selecciona sede y aula.');

    // cursos del aula
    let cursos=[];
    try{
      const r = await apiSecretaria('aula_cursos.php', { searchParams: { aula_id: aulaId } });
      cursos = r.items || [];
    }catch(error){
      console.error('No se pudieron obtener los cursos del aula', error);
    }
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
              ${cursos.map(c=>`<option value="${escapeHTML(c.aula_curso_id ?? '')}">${escapeHTML(c.titulo ?? '')}</option>`).join('')}
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
            const d = await apiSecretaria('alumno_crear.php', { method:'POST', body:fd });
            if(!d.ok) return modal.err(d.msg || 'No se pudo crear el alumno');
            userId = d.user_id; $('#fUserId').value = userId;
            $('#auxMsg').innerHTML = d.temp_password
              ? `<span class="text-green-700">Alumno creado. Contraseña: <b>${escapeHTML(d.temp_password)}</b></span>`
              : '<span class="text-green-700">Alumno creado.</span>';
          }catch(error){
            console.error('Error creando alumno', error);
            return modal.err('Error de red al crear alumno');
          }
        }

        const enrFd = new FormData();
        enrFd.append('user_id', userId);
        enrFd.append('aula_curso_id', $('#fAulaCurso').value);
        try{
          const d = await apiSecretaria('matricular.php', { method:'POST', body: enrFd });
          if(!d.ok) return modal.err(d.msg || 'No se pudo matricular');
          modal.close(); modal.ok('Matriculado correctamente');
          await renderLista();
        }catch(error){
          console.error('Error matriculando alumno', error);
          modal.err('Error de red al matricular');
        }
      }
    });

    $('#btnBuscar')?.addEventListener('click', async ()=>{
      const q = $('#qBuscar').value.trim();
      if(!q) return;
      try{
        const r = await apiSecretaria('alumno_buscar.php', { searchParams: { q } });
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
        $('#auxMsg').innerHTML = `<span class="text-green-700">Usuario existente. Se usará ID ${escapeHTML(u.id)}</span>`;
      }catch(error){
        console.error('Error buscando alumno', error);
        $('#auxMsg').innerHTML='<span class="text-red-700">Error al buscar</span>';
      }
    });
  }

  // --------- Editar datos de alumno ---------
  async function openEditarModal(user_id){
    try{
      const r = await apiSecretaria('alumno_detalle.php', { searchParams: { user_id } });
      const u = r.user;
      if(!u) throw new Error('Sin datos de usuario');
      modal.open({
        title: 'Editar alumno',
        bodyHTML: `
          <form id="formEdit" class="space-y-3">
            <input type="hidden" name="user_id" value="${escapeHTML(u.id ?? '')}">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="text-sm text-gray-600">Nombre</label>
                <input name="name" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" value="${escapeHTML(u.name||'')}" required>
              </div>
              <div>
                <label class="text-sm text-gray-600">DNI</label>
                <input name="dni" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" value="${escapeHTML(u.dni||'')}">
              </div>
              <div>
                <label class="text-sm text-gray-600">Email</label>
                <input name="email" type="email" class="w-full mt-1 px-3 py-2 border rounded-xl" value="${escapeHTML(u.email||'')}">
              </div>
              <div>
                <label class="text-sm text-gray-600">Celular</label>
                <input name="phone" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" value="${escapeHTML(u.phone||'')}">
              </div>
            </div>
          </form>`,
        primaryLabel: 'Guardar',
        onPrimary: async ()=>{
          const fd = new FormData($('#formEdit'));
          try{
            const d = await apiSecretaria('alumno_actualizar.php',{method:'POST', body:fd});
            if(!d.ok) return modal.err(d.msg || 'No se pudo actualizar');
            modal.close(); modal.ok('Datos actualizados');
            await renderLista();
          }catch(error){
            console.error('Error actualizando alumno', error);
            modal.err('Error de red');
          }
        }
      });
    }catch(error){
      console.error('No se pudo cargar datos del alumno', error);
      modal.err('No se pudo cargar datos');
    }
  }

  // --------- Baja de matrícula (manteniendo historial) ---------
  function openBajaModal(enrollment_id, name){
    modal.open({
      title: 'Quitar del aula',
      bodyHTML: `<div class="text-sm">
        ¿Deseas quitar a <b>${escapeHTML(name || '')}</b> de esta aula?<br>
        <span class="text-gray-600">La matrícula quedará en historial.</span>
      </div>`,
      primaryLabel: 'Confirmar',
      onPrimary: async ()=>{
        const fd = new FormData(); fd.append('enrollment_id', enrollment_id);
        try{
          const d = await apiSecretaria('matricula_baja.php',{method:'POST', body:fd});
          if(!d.ok) return modal.err(d.msg || 'No se pudo dar de baja');
          modal.close(); modal.ok('Alumno retirado');
          await renderLista();
        }catch(error){
          console.error('Error retirando alumno del aula', error);
          modal.err('Error de red');
        }
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
