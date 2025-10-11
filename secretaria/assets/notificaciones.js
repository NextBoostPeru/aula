// public/secretaria/assets/notificaciones.js
(async function(){
  const rootHTML = `
    <div class="bg-white rounded-2xl shadow p-4 space-y-6">
      <div class="space-y-2">
        <h3 class="font-semibold">Enviar a un alumno</h3>
        <div class="flex flex-wrap items-end gap-3">
          <select id="selAlumnoNoti" class="px-3 py-2 border rounded-xl">
            <option value="">Selecciona alumno...</option>
          </select>
          <button id="btnEnviarAlumno" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border hover:bg-gray-50">Enviar</button>
        </div>
      </div>
      <div class="space-y-2">
        <h3 class="font-semibold">Enviar a toda el aula</h3>
        <button id="btnEnviarAula" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border hover:bg-gray-50">Enviar</button>
      </div>
    </div>`;

  async function loadAlumnos(){
    const aula = $('#selAula')?.value;
    const sel  = $('#selAlumnoNoti');
    if(!aula || !sel) return;
    sel.innerHTML = '<option value="">Selecciona alumno...</option>';
    try{
      const r = await api(`../backend/secretaria/estudiantes_por_aula.php?aula_id=${aula}`);
      (r.items||[]).forEach(a=>{
        sel.insertAdjacentHTML('beforeend', `<option value="${a.user_id}">${a.name} — DNI ${a.dni}</option>`);
      });
    }catch{}
  }

  function openFormNoti({title, onSubmit}) {
    modal.open({
      title,
      bodyHTML: `
        <form id="formNoti" class="space-y-3">
          <div>
            <label class="text-sm text-gray-600">Título</label>
            <input name="title" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" required />
          </div>
          <div>
            <label class="text-sm text-gray-600">Mensaje</label>
            <textarea name="desc" rows="4" class="w-full mt-1 px-3 py-2 border rounded-xl" required></textarea>
          </div>
          <div>
            <label class="text-sm text-gray-600">Tipo</label>
            <select name="type" class="w-full mt-1 px-3 py-2 border rounded-xl">
              <option value="sistema">Sistema</option>
              <option value="pago">Pago</option>
              <option value="general">General</option>
            </select>
          </div>
        </form>`,
      primaryLabel: 'Enviar',
      onPrimary: onSubmit
    });
  }

  // montar vista
  $('#content').innerHTML = rootHTML;
  feather.replace();

  // eventos
  $('#btnEnviarAlumno')?.addEventListener('click', ()=>{
    const uid = $('#selAlumnoNoti')?.value;
    if(!uid) return modal.err('Selecciona un alumno.');
    openFormNoti({
      title:'Notificar a alumno',
      onSubmit: async ()=>{
        const f = $('#formNoti'); const fd = new FormData(f); fd.append('user_id', uid);
        try{
          await api('../backend/secretaria/notificar_usuario.php',{method:'POST', body:fd});
          modal.close(); modal.ok('Notificación enviada');
        }catch(e){ modal.err('No se pudo enviar'); }
      }
    });
  });

  $('#btnEnviarAula')?.addEventListener('click', ()=>{
    const aula = $('#selAula')?.value;
    if(!aula) return modal.err('Selecciona un aula.');
    openFormNoti({
      title:'Notificar a aula',
      onSubmit: async ()=>{
        const f = $('#formNoti'); const fd = new FormData(f); fd.append('aula_id', aula);
        try{
          await api('../backend/secretaria/notificar_aula.php',{method:'POST', body:fd});
          modal.close(); modal.ok('Notificación enviada');
        }catch(e){ modal.err('No se pudo enviar'); }
      }
    });
  });

  // cargar alumnos al inicio
  await loadAlumnos();

  // re-cargar alumnos ante cambios de sede/aula
  document.addEventListener('contextChanged', async ()=>{
    await loadAlumnos();
  });
})();
