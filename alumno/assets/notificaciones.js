(function(){
  const root = $('#notificationsRoot');
  if (!root) return;

  page.setTitle('Notificaciones');
  page.setActions(`
    <button id="btnMarkAll" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border hover:bg-gray-50">
      <i data-feather="check-square"></i> Marcar todas como leídas
    </button>
  `);

  const renderList = (items) => {
    if (!items.length) {
      page.showInfo(root, 'No tienes notificaciones por ahora.');
      return;
    }

    const html = items.map((n) => {
      const icon = n.type === 'pago' ? 'credit-card' : (n.type === 'sistema' ? 'info' : 'bell');
      const title = escapeHTML(n.title ?? '');
      const desc = escapeHTML(n.desc ?? '');
      const date = escapeHTML(n.date ?? '');
      const read = !!n.read;
      const markBtn = read
        ? ''
        : `<div class="mt-2">
            <button class="text-xs text-indigo-600 hover:underline" data-read-id="${escapeHTML(String(n.id))}">
              Marcar como leída
            </button>
          </div>`;

      return `
        <div class="bg-white rounded-2xl shadow p-4 flex items-start gap-3 ${read ? '' : 'border-l-4 border-indigo-600'}">
          <div class="mt-0.5">
            <i data-feather="${icon}"></i>
          </div>
          <div class="flex-1">
            <div class="flex items-center justify-between">
              <h4 class="font-medium">${title}</h4>
              <span class="text-xs text-gray-500">${date}</span>
            </div>
            <p class="text-sm text-gray-700 mt-1">${desc}</p>
            ${markBtn}
          </div>
        </div>`;
    }).join('');

    root.innerHTML = html;
    if (window.feather) window.feather.replace();

    root.querySelectorAll('[data-read-id]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-read-id');
        if (!id) return;
        const fd = new FormData();
        fd.append('ids[]', id);
        try {
          await studentApi('notifications_mark_read.php', { method: 'POST', body: fd });
          await loadNotifications();
          await pageRefreshNotifications();
        } catch (error) {
          console.error('No se pudo marcar la notificación', error);
          modal.err('No se pudo marcar la notificación como leída.');
        }
      });
    });
  };

  const loadNotifications = async () => {
    page.showLoading(root, 'Cargando notificaciones...');
    try {
      const resp = await studentApi('notifications_list.php');
      if (!resp?.ok) throw new Error(resp?.msg || 'No se pudieron obtener las notificaciones.');
      const items = Array.isArray(resp.items) ? resp.items : [];
      renderList(items);
      if (typeof resp.unread === 'number') {
        page.updateNotifDot(resp.unread);
      }
    } catch (error) {
      console.error('No se pudieron cargar las notificaciones', error);
      page.showError(root, error.message || 'No se pudieron cargar las notificaciones.');
    }
  };

  $('#btnMarkAll')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('all', '1');
    try {
      await studentApi('notifications_mark_read.php', { method: 'POST', body: fd });
      await loadNotifications();
      await pageRefreshNotifications();
    } catch (error) {
      console.error('No se pudieron marcar todas las notificaciones', error);
      modal.err('No se pudieron marcar todas como leídas.');
    }
  });

  loadNotifications();
})();
