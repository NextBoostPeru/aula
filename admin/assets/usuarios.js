(async () => {
  if (!document.querySelector('[data-tab="usuarios"]')) return;

  page.setTitle('Usuarios y roles');
  page.setSubtitle('Supervisa la distribución y últimos accesos');
  page.setActions('');

  const rolesContainer = $('#rolesBreakdown');
  const totalLabel = $('#usersTotal');
  const recentBody = $('#recentUsers');

  try {
    page.showLoading(rolesContainer, 'Cargando información de usuarios');
    if (recentBody) {
      recentBody.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Consultando usuarios...</td></tr>';
    }

    const data = await adminApi('users_summary.php');
    if (!data?.ok) throw new Error(data?.msg || 'No se pudo obtener la información');

    renderRoles(rolesContainer, data.roles || []);
    renderRecent(recentBody, data.recientes || []);
    if (totalLabel) {
      totalLabel.textContent = data.total ? `${data.total} usuarios en total` : '';
    }
  } catch (error) {
    console.error(error);
    if (rolesContainer) rolesContainer.innerHTML = `<div class="bg-red-100 text-red-700 rounded-2xl p-4 text-sm">${escapeHTML(error.message)}</div>`;
    if (recentBody) recentBody.innerHTML = `<tr><td colspan="5" class="px-4 py-6 text-center text-sm text-red-600">${escapeHTML(error.message)}</td></tr>`;
  }

  function renderRoles(container, items) {
    if (!container) return;
    if (!items.length) {
      container.innerHTML = '<div class="bg-slate-100 rounded-2xl p-5 text-sm text-slate-500">No hay información disponible.</div>';
      return;
    }

    container.innerHTML = items.map((item) => `
      <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col gap-2">
        <div class="text-xs uppercase tracking-wide text-slate-500">${escapeHTML(item.rol || 'Rol')}</div>
        <div class="text-2xl font-semibold text-slate-900">${escapeHTML(String(item.cantidad ?? 0))}</div>
        <div class="text-xs text-slate-500">${item.participacion ?? 0}% del total</div>
      </article>`).join('');
  }

  function renderRecent(tbody, rows) {
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No se registran usuarios recientes.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map((row) => `
      <tr class="hover:bg-slate-50">
        <td class="px-4 py-3">
          <div class="font-medium text-slate-800">${escapeHTML(row.nombre || '')}</div>
          <div class="text-xs text-slate-500">${escapeHTML(row.dni || row.username || '')}</div>
        </td>
        <td class="px-4 py-3">${escapeHTML(row.email || '')}</td>
        <td class="px-4 py-3 capitalize">${escapeHTML(row.rol || '')}</td>
        <td class="px-4 py-3">${row.estado === 1 ? '<span class="inline-flex items-center gap-2 text-emerald-600"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Activo</span>' : '<span class="inline-flex items-center gap-2 text-slate-500"><span class="w-2 h-2 rounded-full bg-slate-400"></span>Inactivo</span>'}</td>
        <td class="px-4 py-3">${escapeHTML(row.registrado || '')}</td>
      </tr>`).join('');
  }
})();
