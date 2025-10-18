// public/secretaria/assets/matriculas.js
(async function(){
  const $  = (s,el=document)=>el.querySelector(s);
  const $$ = (s,el=document)=>Array.from(el.querySelectorAll(s));
  const api = async (url, opts={})=>{
    const res = await fetch(url, opts);
    const ct = res.headers.get('content-type')||'';
    const text = await res.text();
    const isJSON = ct.includes('application/json');
    if(!res.ok){
      try{ throw new Error(isJSON ? (JSON.parse(text).msg||text) : text); }
      catch{ throw new Error(text||`HTTP ${res.status}`); }
    }
    return isJSON ? JSON.parse(text) : text;
  };

  const pad = n=>String(n).padStart(2,'0');
  const todayDate = (()=>{ const d=new Date(); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}` })();
  const nowLocal  = (()=>{ const d=new Date(); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}` })();

  // --- Vista LISTA (inicio igual a tu captura) ---
  function renderLista(){
    $('#content').innerHTML = `
      <div class="space-y-6">
        <div class="bg-white rounded-2xl shadow p-4 flex items-center justify-between">
          <div class="font-medium">Estudiantes</div>
          <button id="btnNueva" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border hover:bg-gray-50">
            <i data-feather="user-plus"></i> Agregar/Matricular
          </button>
        </div>
        <div class="bg-white rounded-2xl shadow p-8 text-gray-500">
          (Aquí puedes listar las matrículas si luego agregas un endpoint de listado)
        </div>
      </div>
    `;
    feather.replace();
    $('#btnNueva').addEventListener('click', renderFormulario);
  }

  // --- Vista FORMULARIO ---
  async function renderFormulario(){
    $('#content').innerHTML = `
      <div class="bg-white rounded-2xl shadow p-4 space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">Nueva matrícula</h2>
          <div class="flex gap-2">
            <button id="btnVolver" class="px-3 py-2 rounded-xl border hover:bg-gray-50">Volver</button>
            <button form="formMatricula" type="submit" class="px-3 py-2 rounded-xl bg-black text-white">Guardar</button>
          </div>
        </div>

        <form id="formMatricula" class="space-y-4">
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-gray-600">Sede</label>
              <select id="fmSede" name="sede_id" class="w-full px-3 py-2 border rounded-xl" required></select>
            </div>
            <div>
              <label class="text-sm text-gray-600">Aula / Curso</label>
              <select id="fmAulaCurso" name="aula_curso_id" class="w-full px-3 py-2 border rounded-xl" required>
                <option value="">Selecciona…</option>
              </select>
            </div>
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div><label class="text-sm text-gray-600">Nombres</label>
              <input name="nombres" class="w-full px-3 py-2 border rounded-xl" required>
            </div>
            <div><label class="text-sm text-gray-600">Apellidos</label>
              <input name="apellidos" class="w-full px-3 py-2 border rounded-xl" required>
            </div>
            <div><label class="text-sm text-gray-600">DNI</label>
              <input name="dni" class="w-full px-3 py-2 border rounded-xl" required>
            </div>
            <div><label class="text-sm text-gray-600">Correo</label>
              <input name="correo" type="email" class="w-full px-3 py-2 border rounded-xl">
            </div>
            <div><label class="text-sm text-gray-600">Dirección</label>
              <input name="direccion" class="w-full px-3 py-2 border rounded-xl">
            </div>
            <div><label class="text-sm text-gray-600">Celular</label>
              <input name="celular" class="w-full px-3 py-2 border rounded-xl">
            </div>
          </div>

          <div class="grid md:grid-cols-3 gap-4">
            <div><label class="text-sm text-gray-600">Especialidad (texto)</label>
              <input name="especialidad" class="w-full px-3 py-2 border rounded-xl" placeholder="p.ej. Aux. Educación Sáb. Tarde">
            </div>
            <div><label class="text-sm text-gray-600">Inicio de clases</label>
              <input name="fecha_inicio" type="date" class="w-full px-3 py-2 border rounded-xl">
            </div>
            <div><label class="text-sm text-gray-600">Fecha de matrícula</label>
              <input name="fecha_matricula" type="date" value="${todayDate}" class="w-full px-3 py-2 border rounded-xl" required>
            </div>
          </div>

          <div class="grid md:grid-cols-3 gap-4">
            <div><label class="text-sm text-gray-600">Asesora referida</label>
              <input name="asesora" class="w-full px-3 py-2 border rounded-xl">
            </div>
            <div><label class="text-sm text-gray-600">N° de boleta</label>
              <input name="nro_boleta" class="w-full px-3 py-2 border rounded-xl">
            </div>
            <div><label class="text-sm text-gray-600">Fecha de Yape/Transf</label>
              <input name="fecha_yape" type="date" class="w-full px-3 py-2 border rounded-xl">
            </div>
          </div>

          <div class="grid md:grid-cols-3 gap-4">
            <div><label class="text-sm text-gray-600">Monto matrícula (S/)</label>
              <input name="monto_matricula" type="number" step="0.01" min="0" class="w-full px-3 py-2 border rounded-xl" required>
            </div>
            <div><label class="text-sm text-gray-600">Método de pago</label>
              <select name="metodo" class="w-full px-3 py-2 border rounded-xl">
                <option value="efectivo">Efectivo</option>
                <option value="yape">Yape</option>
                <option value="transferencia">Transferencia</option>
                <option value="otros">Otros</option>
              </select>
            </div>
            <div><label class="text-sm text-gray-600">Referencia</label>
              <input name="ref" class="w-full px-3 py-2 border rounded-xl" placeholder="Código o nota">
            </div>
          </div>

          <div>
            <label class="text-sm text-gray-600">Fecha/hora del pago</label>
            <input name="paid_at" type="datetime-local" value="${nowLocal}" class="w-full md:w-64 px-3 py-2 border rounded-xl" required>
            <p class="text-xs text-gray-500 mt-1">Se guarda en hora Perú (America/Lima).</p>
          </div>

          <!-- Evidencias -->
          <div class="grid md:grid-cols-3 gap-4">
            <div>
              <div class="text-sm font-medium">Evidencia de conversación</div>
              <input id="chat_files" name="chat_files[]" type="file" accept="image/*" multiple class="mt-1">
              <div id="prev_chat" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
            <div>
              <div class="text-sm font-medium">Evidencia de yape/transferencia</div>
              <input id="pago_files" name="pago_files[]" type="file" accept="image/*" multiple class="mt-1">
              <div id="prev_pago" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
            <div>
              <div class="text-sm font-medium">Foto de boleta generada</div>
              <input id="boleta_files" name="boleta_files[]" type="file" accept="image/*" multiple class="mt-1">
              <div id="prev_boleta" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
          </div>
        </form>
      </div>
    `;

    feather.replace();

    // Copiar sedes del selector global si existe
    (function copySedes(){
      const main = $('#selSede');      // del panel izquierdo
      const fm   = $('#fmSede');
      if (main && fm) {
        fm.innerHTML = main.innerHTML; // copia opciones
        fm.value = main.value || '';
      }
    })();

    async function loadAulas(){
      const sede = $('#fmSede').value;
      const sel = $('#fmAulaCurso');
      sel.innerHTML = '<option value="">Cargando…</option>';
      if (!sede) { sel.innerHTML = '<option value="">Selecciona sede…</option>'; return; }
      try{
        const r = await api(`../backend/secretaria/aulas_cursos_por_sede.php?sede_id=${encodeURIComponent(sede)}`);
        const items = r.items || [];
        sel.innerHTML = '<option value="">Selecciona…</option>';
        items.forEach(it=>{
          sel.insertAdjacentHTML('beforeend', `<option value="${it.id}">${it.curso} — ${it.aula}</option>`);
        });
      }catch(e){
        sel.innerHTML = '<option value="">No disponible</option>';
      }
    }
    $('#fmSede').addEventListener('change', loadAulas);
    await loadAulas();

    function previewFiles(input, boxSel){
      const box = $(boxSel); box.innerHTML='';
      const files = input.files || [];
      [...files].forEach(f=>{
        const url = URL.createObjectURL(f);
        const img = document.createElement('img');
        img.src = url; img.className = 'w-24 h-24 object-cover rounded-lg border';
        box.appendChild(img);
      });
    }
    $('#chat_files')  .addEventListener('change', e=>previewFiles(e.target,'#prev_chat'));
    $('#pago_files')  .addEventListener('change', e=>previewFiles(e.target,'#prev_pago'));
    $('#boleta_files').addEventListener('change', e=>previewFiles(e.target,'#prev_boleta'));

    $('#btnVolver').addEventListener('click', renderLista);

    $('#formMatricula').addEventListener('submit', async (ev)=>{
      ev.preventDefault();
      const fd = new FormData(ev.target);
      try{
        await api(`../backend/secretaria/matriculas_crear.php`, { method:'POST', body: fd });
        modal.ok('Matrícula registrada');
        renderLista(); // volver a la lista
      }catch(e){
        modal.err(e.message || 'No se pudo registrar la matrícula');
      }
    });
  }

  // Monta vista inicial (lista)
  renderLista();
})();
