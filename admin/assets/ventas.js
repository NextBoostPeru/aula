(() => {
  if (!document.querySelector('[data-tab="ventas"]')) return;

  const filtersForm = $('#salesFilters');
  const tableBody = $('#salesTable');
  const pagination = $('#salesPagination');
  const summary = $('#salesSummary');
  const resetBtn = $('#resetFilters');
  const sedeSelect = $('#filterSede');

  page.setTitle('Ventas y cobranzas');
  page.setSubtitle('Historial completo de pagos registrados');
  page.setActions('');

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      filtersForm.reset();
      loadSales(1);
    });
  }

  if (filtersForm) {
    filtersForm.addEventListener('submit', (event) => {
      event.preventDefault();
      loadSales(1);
    });
  }

  loadSedes().finally(() => loadSales(1));

  async function loadSedes() {
    if (!sedeSelect) return;
    try {
      const data = await adminApi('sedes.php');
      if (!data?.ok) throw new Error(data?.msg || 'No fue posible cargar las sedes');
      const frag = document.createDocumentFragment();
      const allOption = document.createElement('option');
      allOption.value = '';
      allOption.textContent = 'Todas';
      frag.appendChild(allOption);
      (data.sedes || []).forEach((sede) => {
        const opt = document.createElement('option');
        opt.value = sede.id ?? '';
        opt.textContent = sede.nombre ? String(sede.nombre) : `Sede #${sede.id}`;
        frag.appendChild(opt);
      });
      sedeSelect.innerHTML = '';
      sedeSelect.appendChild(frag);
    } catch (error) {
      console.warn('No se pudieron cargar las sedes', error);
    }
  }

  async function loadSales(pageNumber = 1) {
    if (!tableBody) return;
    page.showLoading(tableBody.parentElement, 'Consultando pagos...');
    if (summary) summary.textContent = '';
    if (pagination) pagination.innerHTML = '';

    const formData = new FormData(filtersForm);
    const params = Object.fromEntries(Array.from(formData.entries()));
    params.page = pageNumber;

    try {
      const data = await adminApi('sales_list.php', { searchParams: params });
      if (!data?.ok) throw new Error(data?.msg || 'No se pudo obtener el listado');
      renderRows(data.items || []);
      renderPagination(data.meta || {}, params);
      if (summary) {
        const total = data.meta?.total ?? 0;
        const sum = data.meta?.sum ?? 0;
        summary.textContent = total ? `${total} pagos • ${formatCurrency(sum)}` : 'Sin resultados';
      }
    } catch (error) {
      console.error(error);
      tableBody.innerHTML = `<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-red-600">${escapeHTML(error.message)}</td></tr>`;
    }
  }

  function renderRows(items) {
    if (!items.length) {
      tableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">No se encontraron pagos con los filtros actuales.</td></tr>';
      return;
    }

    tableBody.innerHTML = items.map((item) => `
      <tr class="hover:bg-slate-50">
        <td class="px-4 py-3 whitespace-nowrap">${escapeHTML(item.fecha || '')}</td>
        <td class="px-4 py-3">
          <div class="font-medium text-slate-800">${escapeHTML(item.alumno?.nombre || '')}</div>
          <div class="text-xs text-slate-500">${escapeHTML(item.alumno?.dni || item.alumno?.username || '')}</div>
        </td>
        <td class="px-4 py-3">${escapeHTML(item.curso || '')}</td>
        <td class="px-4 py-3">${escapeHTML(item.sede || '')}</td>
        <td class="px-4 py-3 capitalize">${escapeHTML(item.metodo || '')}</td>
        <td class="px-4 py-3">${escapeHTML(item.referencia || '')}</td>
        <td class="px-4 py-3 text-right font-semibold">${formatCurrency(item.monto || 0)}</td>
      </tr>`).join('');
  }

  function renderPagination(meta, params) {
    if (!pagination) return;
    const totalPages = meta.pages ?? 0;
    if (!totalPages) {
      pagination.innerHTML = '<span>Sin paginación</span>';
      return;
    }

    const current = meta.page ?? 1;
    const buttons = [];

    const makeButton = (label, targetPage, disabled = false) => `
      <button
        class="px-3 py-1.5 rounded-lg border border-slate-300 ${disabled ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 hover:bg-slate-100'}"
        data-page="${targetPage}"
        ${disabled ? 'disabled' : ''}
      >${label}</button>`;

    buttons.push(makeButton('Anterior', current - 1, current <= 1));
    buttons.push(`<span class="text-slate-500">Página ${current} de ${totalPages}</span>`);
    buttons.push(makeButton('Siguiente', current + 1, current >= totalPages));

    const summaryHtml = meta.total
      ? `<span class="text-slate-500">${meta.total} registros encontrados</span>`
      : '<span class="text-slate-500">Sin registros</span>';

    pagination.innerHTML = `
      <div class="flex items-center gap-2">${buttons.join('')}</div>
      ${summaryHtml}`;

    pagination.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', (event) => {
        const target = Number(event.currentTarget.dataset.page);
        if (Number.isNaN(target) || target < 1 || target > totalPages) return;
        loadSales(target);
      });
    });
  }
})();
