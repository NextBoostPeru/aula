(async () => {
  if (!document.querySelector('[data-tab="resumen"]')) return;

  page.setTitle('Resumen general');
  page.setSubtitle('Indicadores clave de ventas y actividad');
  page.setActions('');

  const cardsContainer = $('#summaryCards');
  const trendContainer = $('#monthlyTrend');
  const monthlyTotal = $('#monthlyTotal');
  const topSedesContainer = $('#topSedes');
  const recentTbody = $('#recentSales');
  const viewSalesBtn = $('#viewAllSales');

  if (viewSalesBtn) {
    viewSalesBtn.addEventListener('click', () => {
      window.location.href = './index.php?tab=ventas';
    });
  }

  try {
    page.showLoading(trendContainer, 'Cargando métricas');
    page.showLoading(topSedesContainer, 'Preparando resumen');

    const data = await adminApi('dashboard_metrics.php');
    if (!data?.ok) throw new Error(data?.msg || 'No se pudo obtener el resumen');

    renderCards(cardsContainer, data.cards || []);
    renderMonthly(trendContainer, data.monthly || []);
    renderTopSedes(topSedesContainer, data.sedes || []);
    renderRecent(recentTbody, data.recent || []);

    if (monthlyTotal) {
      const total = (data.monthly || []).reduce((acc, item) => acc + (item.total || 0), 0);
      monthlyTotal.textContent = total ? `Total período: ${formatCurrency(total)}` : '';
    }
  } catch (error) {
    console.error(error);
    page.showError(trendContainer, error.message);
    page.showError(topSedesContainer, error.message);
    if (cardsContainer) cardsContainer.innerHTML = '';
    if (recentTbody) recentTbody.innerHTML = '';
  }

  function renderCards(container, cards) {
    if (!container) return;
    if (!cards.length) {
      container.innerHTML = '<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 text-sm text-slate-500">Sin datos disponibles.</div>';
      return;
    }

    const html = cards.map((card) => `
      <article class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex flex-col gap-2">
        <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">${escapeHTML(card.label || '')}</div>
        <div class="text-2xl font-semibold text-slate-900">${escapeHTML(card.value || '')}</div>
        ${card.delta ? `<div class="text-xs ${card.delta.startsWith('-') ? 'text-red-600' : 'text-emerald-600'}">${escapeHTML(card.delta)}</div>` : ''}
      </article>
    `).join('');

    container.innerHTML = html;
  }

  function renderMonthly(container, items) {
    if (!container) return;
    if (!items.length) {
      container.innerHTML = '<p class="text-sm text-slate-500">Aún no hay registros suficientes para mostrar.</p>';
      return;
    }

    container.innerHTML = items.map((item) => {
      const pct = Math.min(100, Math.round(item.share || 0));
      return `
        <div class="space-y-1">
          <div class="flex items-center justify-between text-xs text-slate-500">
            <span>${escapeHTML(item.label || item.period || '')}</span>
            <span class="font-semibold text-slate-700">${formatCurrency(item.total || 0)}</span>
          </div>
          <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-slate-900" style="width:${pct}%"></div>
          </div>
        </div>`;
    }).join('');
  }

  function renderTopSedes(container, items) {
    if (!container) return;
    if (!items.length) {
      container.innerHTML = '<p class="text-sm text-slate-500">No se encontraron ventas recientes por sede.</p>';
      return;
    }

    container.innerHTML = items.map((item) => `
      <div class="flex items-center justify-between text-sm">
        <div>
          <p class="font-medium text-slate-700">${escapeHTML(item.nombre || 'Sede')}</p>
          <p class="text-xs text-slate-500">${item.cobros ?? 0} cobros</p>
        </div>
        <span class="text-sm font-semibold text-slate-900">${formatCurrency(item.total || 0)}</span>
      </div>`).join('');
  }

  function renderRecent(tbody, rows) {
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Aún no hay cobros registrados.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map((row) => `
      <tr class="hover:bg-slate-50">
        <td class="px-4 py-3 whitespace-nowrap">${escapeHTML(row.fecha || '')}</td>
        <td class="px-4 py-3">${escapeHTML(row.alumno || '')}</td>
        <td class="px-4 py-3">${escapeHTML(row.curso || '')}</td>
        <td class="px-4 py-3">${escapeHTML(row.sede || '')}</td>
        <td class="px-4 py-3 text-right font-semibold">${formatCurrency(row.monto || 0)}</td>
      </tr>`).join('');
  }
})();
