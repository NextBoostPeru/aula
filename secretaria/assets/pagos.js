// public/secretaria/assets/pagos.js
(async function () {
  // Helpers base
  const $  = (sel, el=document) => el.querySelector(sel);
  const $$ = (sel, el=document) => Array.from(el.querySelectorAll(sel));
  const api = async (url, opts={}) => {
    const res  = await fetch(url, opts);
    const ct   = res.headers.get('content-type') || '';
    const text = await res.text();
    const isJSON = ct.includes('application/json');
    if (!res.ok) {
      try { throw new Error(isJSON ? (JSON.parse(text).msg || text) : text); }
      catch { throw new Error(text || `HTTP ${res.status}`); }
    }
    return isJSON ? JSON.parse(text) : text;
  };

  const rootHTML = `
    <div class="bg-white rounded-2xl shadow p-4 space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="text-sm text-gray-600">Alumno</label><br/>
          <select id="selAlumno" class="px-3 py-2 border rounded-xl">
            <option value="">Selecciona alumno...</option>
          </select>
        </div>
        <button id="btnVer" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border hover:bg-gray-50">Ver pagos</button>
        <button id="btnNuevaCuota" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border hover:bg-gray-50">Nueva cuota</button>
      </div>
      <div id="pagosBox" class="space-y-6"></div>
    </div>`;

  // Cargar alumnos por aula (endpoint existente)
  async function loadAlumnos() {
    const aula = $('#selAula')?.value;
    const sel  = $('#selAlumno');
    if (!aula || !sel) return;
    sel.innerHTML = '<option value="">Selecciona alumno...</option>';
    try {
      const r = await api(`../../backend/secretaria/estudiantes_por_aula.php?aula_id=${encodeURIComponent(aula)}`);
      (r.items || []).forEach(a => {
        sel.insertAdjacentHTML(
          'beforeend',
          `<option value="${a.enrollment_id}">${a.name} — DNI ${a.dni} (enr:${a.enrollment_id})</option>`
        );
      });
    } catch {}
  }

  // Pintar estado de pagos
  async function cargarPagos() {
    const enr = $('#selAlumno')?.value;
    const box = $('#pagosBox');
    if (!enr) { box.innerHTML = '<div class="text-red-600">Selecciona un alumno.</div>'; return; }

    box.innerHTML = `<div class="bg-white rounded-xl border p-4 text-gray-600">Cargando…</div>`;
    try {
      const res = await fetch(`../../backend/secretaria/pagos_estado_alumno.php?enrollment_id=${encodeURIComponent(enr)}`);
      const txt = await res.text();
      let r; try { r = JSON.parse(txt); } catch {
        box.innerHTML = `<div class="text-red-600">Respuesta no válida:<pre class="text-xs whitespace-pre-wrap">${txt}</pre></div>`;
        return;
      }
      if (!res.ok || !r.ok) {
        box.innerHTML = `<div class="text-red-600">${(r&&r.msg)||'Error al cargar pagos'}</div>`; return;
      }

      const cuotas = r.cuotas || [];
      const hist   = r.historial || [];

      const rowsC = cuotas.map(c => {
        const saldo = Number(c.saldo ?? (Number(c.monto||0) - Number(c.amount_paid||0)));
        return `
          <tr>
            <td class="px-3 py-2">#${c.nro}</td>
            <td class="px-3 py-2">${c.vence_en || ''}</td>
            <td class="px-3 py-2">
              <div>S/ ${Number(c.monto||0).toFixed(2)}</div>
              <div class="text-xs text-gray-500">Saldo: S/ ${saldo.toFixed(2)}</div>
            </td>
            <td class="px-3 py-2">
              ${c.status==='pagado'
                ? '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-green-100 text-green-700 text-xs">Pagado</span>'
                : c.status==='vencido'
                ? '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-red-100 text-red-700 text-xs">Vencido</span>'
                : '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-yellow-100 text-yellow-700 text-xs">Pendiente</span>'}
            </td>
            <td class="px-3 py-2">
              ${c.status!=='pagado' ? `
                <div class="flex items-center gap-2">
                  <input type="text" class="px-2 py-1 border rounded-lg text-xs" placeholder="Ref/nota" data-ref-for="${c.id}">
                  <button class="px-2 py-1 rounded-lg border hover:bg-gray-50 text-xs"
                          data-pay="${c.id}" data-saldo="${saldo.toFixed(2)}">
                    Registrar pago
                  </button>
                </div>` : '<span class="text-xs text-gray-400">—</span>'}
            </td>
          </tr>`;
      }).join('');

      const rowsH = hist.map(p => `
        <tr>
          <td class="px-3 py-2">${p.fecha}</td>
          <td class="px-3 py-2">S/ ${Number(p.monto).toFixed(2)}</td>
          <td class="px-3 py-2">${p.metodo}</td>
          <td class="px-3 py-2"><code class="text-xs">${p.ref||''}</code></td>
        </tr>
      `).join('');

      box.innerHTML = `
        <div>
          <h3 class="font-semibold mb-2">Curso: ${r.curso || '-'}</h3>
          <div class="overflow-auto bg-white rounded-2xl shadow">
            <table class="min-w-full text-sm">
              <thead class="border-b"><tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Cuota</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Vence</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Monto / Saldo</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Estado</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Acción</th>
              </tr></thead>
              <tbody class="divide-y">${rowsC || '<tr><td colspan="5" class="px-3 py-2 text-gray-500">Sin cuotas</td></tr>'}</tbody>
            </table>
          </div>
        </div>
        <div>
          <h3 class="font-semibold mb-2 mt-6">Historial de pagos</h3>
          <div class="overflow-auto bg-white rounded-2xl shadow">
            <table class="min-w-full text-sm">
              <thead class="border-b"><tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Fecha</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Monto</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Método</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Ref</th>
              </tr></thead>
              <tbody class="divide-y">${rowsH || '<tr><td colspan="4" class="px-3 py-2 text-gray-500">Sin movimientos</td></tr>'}</tbody>
            </table>
          </div>
        </div>`;

      if (window.feather) feather.replace();

      // === Modal: registrar pago (parcial/total) + módulo + método + fecha ===
      $$('#pagosBox [data-pay]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const qid   = btn.getAttribute('data-pay');
          const saldo = parseFloat(btn.getAttribute('data-saldo') || '0') || 0;
          const refInput = $(`#pagosBox [data-ref-for="${qid}"]`);
          const enrollmentId = $('#selAlumno')?.value;
          if (!enrollmentId) return modal.err('Selecciona un alumno primero.');

          // Módulos del curso de esta matrícula
          let mods = [];
          try {
            const rmods = await api(`../../backend/secretaria/enrollment_modulos.php?enrollment_id=${encodeURIComponent(enrollmentId)}`);
            if (rmods.ok) mods = rmods.items || [];
          } catch {}
          const modOptions = mods.length
            ? mods.map(m => `<option value="${m.id}">${m.titulo}</option>`).join('')
            : '<option value="">(No hay módulos)</option>';

          modal.open({
            title: 'Registrar pago de cuota (parcial o total)',
            bodyHTML: `
              <form id="formPago" class="space-y-3 text-sm">
                <div>
                  <label class="text-gray-600">Saldo de la cuota</label>
                  <input type="text" readonly value="S/ ${saldo.toFixed(2)}"
                         class="w-full mt-1 px-3 py-2 border rounded-xl bg-gray-50">
                </div>
                <div>
                  <label class="text-gray-600">Monto a pagar</label>
                  <input name="amount" type="number" step="0.01" min="0.01" max="${saldo.toFixed(2)}"
                         value="${saldo.toFixed(2)}"
                         class="w-full mt-1 px-3 py-2 border rounded-xl" required>
                  <p class="text-xs text-gray-500 mt-1">No puede exceder el saldo.</p>
                </div>
                <div>
                  <label class="text-gray-600">Módulo</label>
                  <select name="curso_modulo_id" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
                    ${modOptions}
                  </select>
                </div>
                <div>
                  <label class="text-gray-600">Método</label>
                  <select name="metodo" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
                    <option value="yape">Yape</option>
                    <option value="efectivo" selected>Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="otros">Otros</option>
                  </select>
                </div>
                <div>
                  <label class="text-gray-600">Fecha del pago</label>
                  <input name="paid_at" id="paid_at_input" type="datetime-local"
                         class="w-full mt-1 px-3 py-2 border rounded-xl" required>
                  <p class="text-xs text-gray-500 mt-1">Se guardará en hora de Perú.</p>
                </div>
                <div>
                  <label class="text-gray-600">Referencia / Nota</label>
                  <input name="ref" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" placeholder="(opcional)">
                  <p class="text-xs text-gray-500 mt-1">Si lo dejas vacío, se genera boleta: B-YYYYMMDD-#cuota-#pago</p>
                </div>
              </form>`,
            primaryLabel: 'Registrar pago',
            onPrimary: async () => {
              const form = $('#formPago');
              if (!form) return modal.err('No se pudo abrir el formulario.');
              const fd = new FormData(form);

              const amount = parseFloat(fd.get('amount') || '0');
              const curso_modulo_id = fd.get('curso_modulo_id');
              const metodo = fd.get('metodo');
              const paid_at = fd.get('paid_at');

              if (!(amount > 0)) return modal.err('Ingresa un monto válido.');
              if (amount > saldo + 1e-9) return modal.err(`El monto no puede exceder S/ ${saldo.toFixed(2)}.`);
              if (!curso_modulo_id || Number(curso_modulo_id) <= 0) return modal.err('Selecciona el módulo.');
              if (!metodo) return modal.err('Selecciona el método de pago.');
              if (!paid_at) return modal.err('Selecciona la fecha del pago.');

              fd.append('quota_id', qid);

              try {
                await api('../../backend/secretaria/pagos_marcar_pagado.php', { method: 'POST', body: fd });
                modal.close(); modal.ok('Pago registrado');
                await cargarPagos();
              } catch (e) {
                modal.err('No se pudo registrar el pago');
              }
            }
          });

          // Prefill referencia escrita en la fila
          if (refInput && refInput.value) $('#formPago [name="ref"]').value = refInput.value;

          // Setear ahora por defecto (datetime-local "YYYY-MM-DDTHH:MM")
          (function setPeruNow(){
            const pad = n => String(n).padStart(2,'0');
            const now = new Date(); // hora del navegador
            const y = now.getFullYear(), m = pad(now.getMonth()+1), d = pad(now.getDate());
            const hh = pad(now.getHours()), mm = pad(now.getMinutes());
            const val = `${y}-${m}-${d}T${hh}:${mm}`;
            const el = $('#paid_at_input'); if (el && !el.value) el.value = val;
          })();
        });
      });

    } catch {
      box.innerHTML = '<div class="text-red-600">No se pudo cargar pagos.</div>';
    }
  }

  // Montaje UI
  $('#content').innerHTML = rootHTML;
  if (window.feather) feather.replace();

  // Acciones
  $('#btnVer')?.addEventListener('click', cargarPagos);

  $('#btnNuevaCuota')?.addEventListener('click', () => {
    const enr = $('#selAlumno')?.value;
    if (!enr) return modal.err('Selecciona un alumno primero.');
    modal.open({
      title: 'Crear nueva cuota',
      bodyHTML: `
        <form id="formCuota" class="space-y-3">
          <div>
            <label class="text-sm text-gray-600">Número de cuota</label>
            <input name="nro" type="number" min="1" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
          </div>
          <div>
            <label class="text-sm text-gray-600">Fecha de vencimiento</label>
            <input name="due_date" type="date" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
          </div>
          <div>
            <label class="text-sm text-gray-600">Monto</label>
            <input name="amount" type="number" step="0.01" min="0.01" class="w-full mt-1 px-3 py-2 border rounded-xl" required>
          </div>
        </form>`,
      primaryLabel: 'Crear',
      onPrimary: async () => {
        const form = $('#formCuota');
        const fd = new FormData(form);
        fd.append('enrollment_id', $('#selAlumno')?.value || '');
        try {
          await api('../../backend/secretaria/pagos_crear_cuota.php', { method: 'POST', body: fd });
          modal.close(); modal.ok('Cuota creada'); await cargarPagos();
        } catch { modal.err('No se pudo crear la cuota'); }
      }
    });
  });

  // Inicialización
  await loadAlumnos();
  document.addEventListener('contextChanged', async () => {
    await loadAlumnos();
    $('#pagosBox').innerHTML = '';
  });
})();
