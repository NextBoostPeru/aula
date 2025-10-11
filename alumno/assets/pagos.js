(function(){
  const root = $('#paymentsRoot');
  if (!root) return;

  page.clearActions();
  page.setTitle('Pagos');
  page.showLoading(root);

  const formatMoney = (value) => {
    const num = Number(value);
    if (Number.isFinite(num)) return num.toFixed(2);
    return '0.00';
  };

  Promise.all([
    studentApi('payments_status.php').catch(() => ({ cuotas: [] })),
    studentApi('payments_history.php').catch(() => ({ history: [] }))
  ])
    .then(([estado, historial]) => {
      const cuotas = Array.isArray(estado?.cuotas) ? estado.cuotas : [];
      const history = Array.isArray(historial?.history) ? historial.history : [];

      const estadoRows = cuotas.map((cuota) => {
        const curso = escapeHTML(cuota.curso ?? '-');
        const nro = escapeHTML(cuota.nro ?? cuota.cuota ?? '');
        const vence = escapeHTML(cuota.vence_en ?? cuota.fecha_vencimiento ?? '');
        const monto = formatMoney(cuota.monto);
        const status = cuota.status;
        let badge = '<span class="inline-flex items-center px-2 py-0.5 rounded-lg border bg-yellow-100 text-yellow-700 border-yellow-200 text-xs">Pendiente</span>';
        if (status === 'pagado') {
          badge = '<span class="inline-flex items-center px-2 py-0.5 rounded-lg border bg-green-100 text-green-700 border-green-200 text-xs">Pagado</span>';
        } else if (status === 'vencido') {
          badge = '<span class="inline-flex items-center px-2 py-0.5 rounded-lg border bg-red-100 text-red-700 border-red-200 text-xs">Vencido</span>';
        }
        return `
          <tr>
            <td class="px-3 py-2">${curso}</td>
            <td class="px-3 py-2">#${nro}</td>
            <td class="px-3 py-2">${vence}</td>
            <td class="px-3 py-2">S/ ${monto}</td>
            <td class="px-3 py-2">${badge}</td>
          </tr>`;
      }).join('');

      const historyRows = history.map((pago) => {
        const fecha = escapeHTML(pago.fecha ?? '');
        const monto = formatMoney(pago.monto);
        const metodo = escapeHTML(pago.metodo ?? '-');
        const ref = escapeHTML(pago.ref ?? '-');
        const curso = escapeHTML(pago.curso ?? '-');
        const cuota = escapeHTML(pago.cuota_nro ?? pago.cuota ?? '-');
        return `
          <tr>
            <td class="px-3 py-2">${fecha}</td>
            <td class="px-3 py-2">S/ ${monto}</td>
            <td class="px-3 py-2">${metodo}</td>
            <td class="px-3 py-2"><code class="text-xs">${ref}</code></td>
            <td class="px-3 py-2">${curso}</td>
            <td class="px-3 py-2">#${cuota}</td>
          </tr>`;
      }).join('');

      root.innerHTML = `
        <div class="space-y-6">
          <div>
            <h3 class="font-semibold mb-2">Estado de cuotas</h3>
            <div class="overflow-auto bg-white rounded-2xl shadow">
              <table class="min-w-full text-sm">
                <thead class="border-b">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Curso</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Cuota</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Vence</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Monto</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Estado</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  ${estadoRows || '<tr><td colspan="5" class="px-3 py-2 text-gray-500">Sin cuotas registradas</td></tr>'}
                </tbody>
              </table>
            </div>
          </div>
          <div>
            <h3 class="font-semibold mb-2">Historial de pagos</h3>
            <div class="overflow-auto bg-white rounded-2xl shadow">
              <table class="min-w-full text-sm">
                <thead class="border-b">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Fecha</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Monto</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Método</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Referencia</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Curso</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Cuota</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  ${historyRows || '<tr><td colspan="6" class="px-3 py-2 text-gray-500">Sin pagos registrados</td></tr>'}
                </tbody>
              </table>
            </div>
          </div>
        </div>`;

      if (window.feather) window.feather.replace();
    })
    .catch((error) => {
      console.error('No se pudo cargar la información de pagos', error);
      page.showError(root, 'No se pudo cargar la información de pagos. Intenta nuevamente.');
    });
})();
