(function(){
  const root = $('#modulesRoot');
  if (!root) return;

  page.clearActions();
  page.setTitle('Módulos');
  page.showLoading(root);

  studentApi('modules_all_my.php')
    .then((resp) => {
      const groups = Array.isArray(resp?.data) ? resp.data : [];
      if (!groups.length) {
        page.showInfo(root, 'No hay módulos disponibles por ahora.');
        return;
      }

      const html = groups.map((group) => {
        const curso = group.curso || {};
        const modulos = Array.isArray(group.modulos) ? group.modulos : [];
        const rows = modulos.map((mod) => {
          const numero = escapeHTML(mod.numero ?? '');
          const titulo = escapeHTML(mod.titulo ?? '');
          const video = mod.video_url ? `<a href="${escapeHTML(mod.video_url)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border hover:bg-gray-50"><i data-feather="play"></i><span class="hidden sm:inline">Video</span></a>` : '<span class="text-xs text-gray-400">—</span>';
          const pdf = mod.pdf_url ? `<a href="${escapeHTML(mod.pdf_url)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border hover:bg-gray-50"><i data-feather="file-text"></i><span class="hidden sm:inline">PDF</span></a>` : '<span class="text-xs text-gray-400">—</span>';
          const slides = mod.slides_url ? `<a href="${escapeHTML(mod.slides_url)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border hover:bg-gray-50"><i data-feather="sliders"></i><span class="hidden sm:inline">Diapositivas</span></a>` : '<span class="text-xs text-gray-400">—</span>';
          const isActive = group.active_modulo_id === mod.id;
          const estado = isActive
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-lg border bg-green-100 text-green-700 border-green-200 text-xs">Activo</span>'
            : '<span class="text-xs text-gray-400">—</span>';

          return `
            <tr class="${isActive ? 'bg-indigo-50' : ''}">
              <td class="px-3 py-2">#${numero}</td>
              <td class="px-3 py-2">${titulo}</td>
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">${video}${pdf}${slides}</div>
              </td>
              <td class="px-3 py-2">${estado}</td>
            </tr>`;
        }).join('');

        const cursoTitulo = escapeHTML(curso.titulo ?? '');
        const sede = escapeHTML(curso.sede ?? '-');
        const aula = escapeHTML(curso.aula ?? '-');

        return `
          <div class="bg-white rounded-2xl shadow p-4 space-y-3">
            <div>
              <h3 class="text-base font-semibold">${cursoTitulo}</h3>
              <div class="text-xs text-gray-500">Sede: ${sede} · Aula: ${aula}</div>
            </div>
            <div class="overflow-auto">
              <table class="min-w-full text-sm">
                <thead class="border-b">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">#</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Módulo</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Materiales</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Estado</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  ${rows || '<tr><td colspan="4" class="px-3 py-2 text-gray-500">Sin módulos registrados</td></tr>'}
                </tbody>
              </table>
            </div>
          </div>`;
      }).join('');

      root.innerHTML = html;
      if (window.feather) window.feather.replace();
    })
    .catch((error) => {
      console.error('No se pudieron cargar los módulos', error);
      page.showError(root, 'No se pudieron cargar los módulos. Intenta nuevamente.');
    });
})();
