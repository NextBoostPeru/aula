(function(){
  const root = $('#attendanceRoot');
  if (!root) return;

  page.clearActions();
  page.setTitle('Asistencias');
  page.showLoading(root);

  const badge = (status) => {
    switch (status) {
      case 'asistio':
        return '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-green-100 text-green-700 border-green-200 text-xs">Asistió</span>';
      case 'tarde':
        return '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-yellow-100 text-yellow-700 border-yellow-200 text-xs">Tarde</span>';
      case 'falta':
        return '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-red-100 text-red-700 border-red-200 text-xs">Falta</span>';
      case 'justificado':
        return '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-blue-100 text-blue-700 border-blue-200 text-xs">Justificado</span>';
      case 'no_aplica':
        return '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-gray-100 text-gray-600 border-gray-200 text-xs">No aplica</span>';
      case 'pendiente':
        return '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-orange-100 text-orange-700 border-orange-200 text-xs">Pendiente</span>';
      default:
        return '<span class="inline-flex px-2 py-0.5 rounded-lg border bg-gray-100 text-gray-600 border-gray-200 text-xs">Programada</span>';
    }
  };

  studentApi('attendance_my.php')
    .then((resp) => {
      const groups = Array.isArray(resp?.data) ? resp.data : [];
      if (!groups.length) {
        page.showInfo(root, 'No hay registros de asistencia disponibles.');
        return;
      }

      const html = groups.map((group) => {
        const curso = group.curso || {};
        const cursoTitulo = escapeHTML(curso.titulo ?? '');
        const sede = escapeHTML(curso.sede ?? '-');
        const aula = escapeHTML(curso.aula ?? '-');
        const modulo = group.modulo || null;

        let moduloNombre = 'Sin módulo activo';
        let moduloTexto = 'Sin módulo activo';
        if (modulo) {
          const partes = [];
          if (typeof modulo.numero === 'number' && !Number.isNaN(modulo.numero)) {
            partes.push(`Módulo ${escapeHTML(String(modulo.numero))}`);
          }
          if (!partes.length && modulo.id) {
            partes.push(`Módulo ${escapeHTML(String(modulo.id))}`);
          }
          if (modulo.titulo) {
            partes.push(escapeHTML(modulo.titulo));
          }
          moduloNombre = partes.length ? partes.join(' · ') : 'Módulo';

          const inicio = modulo.start_date ? escapeHTML(modulo.start_date) : '';
          if (modulo.estado === 'registrado') {
            moduloTexto = inicio
              ? `Asistencias registradas desde <span class="font-medium">${inicio}</span>`
              : 'Asistencias registradas del módulo';
          } else {
            moduloTexto = inicio
              ? `Módulo activo desde <span class="font-medium">${inicio}</span>`
              : 'Módulo activo';
          }
        }
        const clases = Array.isArray(group.clases) ? group.clases : [];

        const rows = clases.map((clase) => {
          const nro = escapeHTML(clase.nro ?? '');
          const fecha = clase.date ? escapeHTML(clase.date) : '-';
          return `
            <tr>
              <td class="px-3 py-2">Clase ${nro}</td>
              <td class="px-3 py-2">${fecha}</td>
              <td class="px-3 py-2">${badge(clase.status)}</td>
            </tr>`;
        }).join('');

        return `
          <div class="bg-white rounded-2xl shadow p-4 space-y-3">
            <div>
              <h3 class="text-base font-semibold">${cursoTitulo}</h3>
              <div class="text-xs text-gray-500">Sede: ${sede} · Aula: ${aula}</div>
              <div class="text-sm font-medium text-gray-700 mt-2">${moduloNombre}</div>
              <div class="text-xs text-gray-500">${moduloTexto}</div>
            </div>
            <div class="overflow-auto">
              <table class="min-w-full text-sm">
                <thead class="border-b">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Sesión</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Fecha</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Estado</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  ${rows || '<tr><td colspan="3" class="px-3 py-2 text-gray-500">No hay clases disponibles</td></tr>'}
                </tbody>
              </table>
            </div>
          </div>`;
      }).join('');

      root.innerHTML = html;
      if (window.feather) window.feather.replace();
    })
    .catch((error) => {
      console.error('No se pudieron cargar las asistencias', error);
      page.showError(root, 'No se pudieron cargar las asistencias. Inténtalo de nuevo.');
    });
})();
