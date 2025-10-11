// public/secretaria/assets/caja.js
(async function(){
  const $  = (s,el=document)=>el.querySelector(s);
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

  const getSedeId = ()=>{
    const u = new URL(location.href);
    const sid = u.searchParams.get('sede_id');
    return sid ? parseInt(sid,10) : null;
  };
  const SEDE_ID = getSedeId();

  const pad = n=>String(n).padStart(2,'0');
  const today = (()=>{ const d=new Date(); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}` })();
  const thisYear = (new Date()).getFullYear();
  const thisMonth = (new Date()).getMonth()+1;

  $('#content').innerHTML = `
    <div class="space-y-6">
      <div class="bg-white rounded-2xl shadow p-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="text-sm text-gray-600">Fecha</label><br>
          <input id="fechaCaja" type="date" value="${today}" class="px-3 py-2 border rounded-xl">
        </div>
        <button id="btnVerCaja" class="px-3 py-2 rounded-xl border hover:bg-gray-50">Ver pagos del día</button>
        <button id="btnExportar" class="px-3 py-2 rounded-xl border hover:bg-gray-50">Exportar cierre (CSV)</button>
        <button id="btnCerrarCaja" class="px-3 py-2 rounded-xl border hover:bg-gray-50 bg-black text-white">Cerrar día</button>
      </div>

      <div id="totalesBox" class="grid grid-cols-1 sm:grid-cols-5 gap-3"></div>

      <div class="bg-white rounded-2xl shadow overflow-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Hora</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Alumno</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Curso / Módulo</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Método</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Ref</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Monto</th>
            </tr>
          </thead>
          <tbody id="tbodyCaja" class="divide-y"></tbody>
        </table>
      </div>

      <div id="cierreInfo" class="text-sm text-gray-600"></div>

      <!-- Reportes de cierres -->
      <div class="bg-white rounded-2xl shadow p-4 space-y-3">
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="text-sm text-gray-600">Mes</label><br>
            <select id="repMes" class="px-3 py-2 border rounded-xl">
              ${Array.from({length:12},(_,i)=>`<option value="${i+1}" ${i+1===thisMonth?'selected':''}>${pad(i+1)}</option>`).join('')}
            </select>
          </div>
          <div>
            <label class="text-sm text-gray-600">Año</label><br>
            <select id="repAnio" class="px-3 py-2 border rounded-xl">
              ${[thisYear-1,thisYear,thisYear+1].map(y=>`<option value="${y}" ${y===thisYear?'selected':''}>${y}</option>`).join('')}
            </select>
          </div>
          <button id="btnVerReportes" class="px-3 py-2 rounded-xl border hover:bg-gray-50">Ver cierres</button>
        </div>

        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="border-b bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Fecha</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Total</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Efectivo</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Yape</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Transferencia</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Otros</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Boletas</th>
              </tr>
            </thead>
            <tbody id="tbodyReportes" class="divide-y"></tbody>
          </table>
        </div>
      </div>
    </div>
  `;

  function renderTotales(t){
    const cards = [
      {label:'Total', val:t.total},
      {label:'Efectivo', val:t.efectivo},
      {label:'Yape', val:t.yape},
      {label:'Transferencia', val:t.transferencia},
      {label:'Otros', val:t.otros},
    ];
    $('#totalesBox').innerHTML = cards.map(c=>`
      <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-xs text-gray-600">${c.label}</div>
        <div class="text-lg font-semibold">S/ ${Number(c.val||0).toFixed(2)}</div>
      </div>`).join('');
  }

  async function cargarCaja(){
    const f = $('#fechaCaja').value || today;

    if (!SEDE_ID) {
      $('#tbodyCaja').innerHTML = `<tr><td colspan="6" class="px-3 py-4 text-center text-red-500">No hay sede seleccionada.</td></tr>`;
      $('#totalesBox').innerHTML = '';
      $('#cierreInfo').innerHTML = '';
      return;
    }

    const qs = new URLSearchParams({ fecha: f, sede_id: SEDE_ID });
    const r = await api(`../backend/secretaria/caja_listar.php?${qs.toString()}`);

    const tb = $('#tbodyCaja');
    const items = r.items || [];
    tb.innerHTML = items.length ? items.map(it => {
      const hora = (it.paid_at||'').split(' ')[1] || (it.paid_at||'');
      const alumno = it.alumno || '-';
      const curso  = it.curso || '';
      const modulo = it.modulo ? ` — <span class="text-gray-500">${it.modulo}</span>` : '';
      return `
        <tr>
          <td class="px-3 py-2">${hora}</td>
          <td class="px-3 py-2">${alumno}</td>
          <td class="px-3 py-2">${curso}${modulo}</td>
          <td class="px-3 py-2 capitalize">${it.method}</td>
          <td class="px-3 py-2"><code class="text-xs">${it.reference||''}</code></td>
          <td class="px-3 py-2 text-right">S/ ${Number(it.amount).toFixed(2)}</td>
        </tr>`;
    }).join('') : `<tr><td colspan="6" class="px-3 py-4 text-center text-gray-500">Sin pagos para la fecha</td></tr>`;

    renderTotales({
      total: r.totales?.total || 0,
      efectivo: r.totales?.efectivo || 0,
      yape: r.totales?.yape || 0,
      transferencia: r.totales?.transferencia || 0,
      otros: r.totales?.otros || 0,
    });

    const c = r.closure;
    $('#cierreInfo').innerHTML = c ? `
      <div class="bg-green-50 border border-green-200 rounded-xl p-3">
        <div class="font-medium text-green-800">Cierre realizado</div>
        <div>Fecha: <b>${c.fecha}</b> — Sede: <b>${c.sede_id||'-'}</b> — Boletas: <b>${c.receipts_count}</b></div>
        <div>Total: <b>S/ ${Number(c.total).toFixed(2)}</b></div>
      </div>
    ` : `<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3">
           <div class="font-medium text-yellow-800">Sin cierre</div>
           <div>Genera el cierre cuando termines el día.</div>
         </div>`;
  }

  async function cargarReportes(){
    if (!SEDE_ID) {
      $('#tbodyReportes').innerHTML = `<tr><td colspan="7" class="px-3 py-4 text-center text-red-500">No hay sede seleccionada.</td></tr>`;
      return;
    }
    const y = $('#repAnio').value;
    const m = $('#repMes').value;
    const qs = new URLSearchParams({ year:y, month:m, sede_id: SEDE_ID });
    const r = await api(`../backend/secretaria/cierres_listar.php?${qs.toString()}`);
    const items = r.items || [];
    const tb = $('#tbodyReportes');
    if (!items.length) {
      tb.innerHTML = `<tr><td colspan="7" class="px-3 py-4 text-center text-gray-500">Sin cierres para el periodo</td></tr>`;
      return;
    }
    const rows = items.map(c => `
      <tr>
        <td class="px-3 py-2">${c.fecha}</td>
        <td class="px-3 py-2 text-right">S/ ${Number(c.total).toFixed(2)}</td>
        <td class="px-3 py-2 text-right">S/ ${Number(c.efectivo_total).toFixed(2)}</td>
        <td class="px-3 py-2 text-right">S/ ${Number(c.yape_total).toFixed(2)}</td>
        <td class="px-3 py-2 text-right">S/ ${Number(c.transferencia_total).toFixed(2)}</td>
        <td class="px-3 py-2 text-right">S/ ${Number(c.otros_total).toFixed(2)}</td>
        <td class="px-3 py-2 text-right">${c.receipts_count}</td>
      </tr>`).join('');
    tb.innerHTML = rows;
  }

  // Eventos
  $('#btnVerCaja').addEventListener('click', cargarCaja);

  $('#btnExportar').addEventListener('click', ()=>{
    if (!SEDE_ID) return modal.err('No hay sede seleccionada.');
    const f = $('#fechaCaja').value || today;
    const url = `../backend/secretaria/caja_exportar.php?fecha=${encodeURIComponent(f)}&sede_id=${SEDE_ID}`;
    window.open(url, '_blank');
  });

  $('#btnCerrarCaja').addEventListener('click', async ()=>{
    if (!SEDE_ID) return modal.err('No hay sede seleccionada.');
    const f = $('#fechaCaja').value || today;
    modal.open({
      title: 'Cerrar día',
      bodyHTML: `
        <form id="formCierre" class="space-y-3 text-sm">
          <div>Vas a cerrar la caja del <b>${f}</b> (sede ${SEDE_ID}).</div>
          <div>
            <label class="text-gray-600">Notas (opcional)</label>
            <input name="notes" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" placeholder="Observaciones...">
          </div>
        </form>`,
      primaryLabel: 'Confirmar cierre',
      onPrimary: async ()=>{
        const form = $('#formCierre');
        const fd = new FormData(form);
        fd.append('fecha', f);
        fd.append('sede_id', String(SEDE_ID));
        try{
          await api(`../backend/secretaria/caja_cerrar.php`, { method:'POST', body: fd });
          modal.close(); modal.ok('Cierre generado');
          await cargarCaja();
          await cargarReportes();
        }catch(e){ modal.err(e.message || 'No se pudo cerrar el día'); }
      }
    });
  });

  $('#btnVerReportes').addEventListener('click', cargarReportes);

  // Carga inicial
  await cargarCaja();
  await cargarReportes();
})();
