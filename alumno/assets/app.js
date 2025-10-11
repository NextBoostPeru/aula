const ABSOLUTE_URL_REGEX = /^(?:[a-z]+:)?\/\//i;
const API_BASE_URL = new URL('../backend/', window.location.href);

window.$ = (sel, ctx = document) => ctx.querySelector(sel);
window.$$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

function normalizeApiInput(input, base) {
  if (input instanceof URL) return input;
  if (typeof input === 'string') {
    if (ABSOLUTE_URL_REGEX.test(input)) {
      throw new Error('Las peticiones deben permanecer en el mismo origen.');
    }
    const normalized = input.replace(/^\/+/, '');
    return new URL(normalized, base || window.location.href);
  }
  throw new TypeError('Entrada de API no válida');
}

function withDefaultHeaders(options) {
  const headers = new Headers(options.headers || {});
  if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');
  if (!headers.has('Accept')) headers.set('Accept', 'application/json, text/plain, */*');
  return headers;
}

async function api(input, opt = {}) {
  const url = normalizeApiInput(input, window.location.href);
  if (url.origin !== window.location.origin) {
    throw new Error('Solo se permiten peticiones al mismo origen.');
  }

  const options = { ...opt };
  options.method = options.method || 'GET';
  options.credentials = options.credentials || 'include';
  options.headers = withDefaultHeaders(options);

  const response = await fetch(url.toString(), options);
  const bodyText = await response.text();

  if (!response.ok) {
    let message = bodyText || response.statusText || 'Error en la solicitud';
    try {
      const parsed = JSON.parse(bodyText);
      if (parsed && typeof parsed === 'object' && parsed.msg) {
        message = parsed.msg;
      }
    } catch (e) {
      // ignore JSON parse errors
    }
    throw new Error(message);
  }

  if (!bodyText) return null;

  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return JSON.parse(bodyText);
  }

  try {
    return JSON.parse(bodyText);
  } catch (e) {
    return bodyText;
  }
}

window.api = api;

window.studentApi = async (endpoint, opt = {}) => {
  const { searchParams, ...options } = opt;
  const url = normalizeApiInput(endpoint, API_BASE_URL);
  if (url.origin !== API_BASE_URL.origin) {
    throw new Error('Endpoint fuera de rango permitido.');
  }
  if (searchParams && typeof searchParams === 'object') {
    Object.entries(searchParams).forEach(([key, value]) => {
      if (value === undefined || value === null || value === '') return;
      url.searchParams.set(key, value);
    });
  }
  return api(url, options);
};

const HTML_ESCAPE_LOOKUP = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;'
};

window.escapeHTML = (value) => {
  const str = value == null ? '' : String(value);
  return str.replace(/[&<>"']/g, (ch) => HTML_ESCAPE_LOOKUP[ch]);
};

const actionsEl = $('#actions');
const titleEl = $('#title');
const notifDot = $('#notifDot');

function withFeatherUpdate(callback) {
  callback();
  if (window.feather) {
    window.feather.replace();
  }
}

window.page = {
  setTitle(text) {
    if (titleEl) titleEl.textContent = text || '';
  },
  setActions(html) {
    if (!actionsEl) return;
    withFeatherUpdate(() => {
      actionsEl.innerHTML = html || '';
    });
  },
  clearActions() {
    this.setActions('');
  },
  showLoading(container, message = 'Cargando...') {
    if (!container) return;
    withFeatherUpdate(() => {
      container.innerHTML = `
        <div class="bg-white rounded-2xl p-6 shadow flex items-center gap-3 text-gray-600">
          <i data-feather="loader" class="animate-spin"></i> ${escapeHTML(message)}
        </div>`;
    });
  },
  showInfo(container, message) {
    if (!container) return;
    container.innerHTML = `
      <div class="bg-white rounded-2xl p-6 shadow text-sm text-gray-700">${escapeHTML(message)}</div>`;
  },
  showError(container, message) {
    if (!container) return;
    container.innerHTML = `
      <div class="bg-white rounded-2xl p-6 shadow text-sm text-red-700">${escapeHTML(message)}</div>`;
  },
  updateNotifDot(unread) {
    if (!notifDot) return;
    notifDot.classList.toggle('hidden', !unread);
  }
};

window.modal = {
  open({ title, bodyHTML, primaryLabel, onPrimary }) {
    $('#modalTitle').textContent = title || 'Mensaje';
    $('#modalBody').innerHTML = bodyHTML || '';
    const primary = $('#modalPrimary');
    if (primaryLabel && typeof onPrimary === 'function') {
      primary.textContent = primaryLabel;
      primary.classList.remove('hidden');
      primary.onclick = () => onPrimary();
    } else {
      primary.classList.add('hidden');
      primary.onclick = null;
    }
    const modalEl = $('#modal');
    if (modalEl) {
      modalEl.classList.remove('hidden');
      modalEl.classList.add('flex');
    }
    if (window.feather) window.feather.replace();
  },
  close() {
    const modalEl = $('#modal');
    if (!modalEl) return;
    modalEl.classList.add('hidden');
    modalEl.classList.remove('flex');
  },
  ok(msg) {
    this.open({
      title: 'Listo',
      bodyHTML: `<div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-xl p-3">${escapeHTML(msg || 'Listo')}</div>`
    });
  },
  err(msg) {
    this.open({
      title: 'Error',
      bodyHTML: `<div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl p-3">${escapeHTML(msg || 'Ocurrió un error')}</div>`
    });
  }
};

$('#modalClose')?.addEventListener('click', () => modal.close());
$('#modalCancel')?.addEventListener('click', () => modal.close());

document.addEventListener('keydown', (ev) => {
  if (ev.key === 'Escape') modal.close();
});

async function ensureSession() {
  try {
    const data = await studentApi('session_check.php');
    if (!data || !data.ok || !data.auth) {
      window.location.href = '../index.html';
    }
  } catch (error) {
    window.location.href = '../index.html';
  }
}

async function refreshNotifDot() {
  try {
    const data = await studentApi('notifications_list.php', { searchParams: { limit: 1 } });
    if (data && typeof data.unread === 'number') {
      page.updateNotifDot(data.unread);
    }
  } catch (error) {
    // ignore errors
  }
}

window.pageRefreshNotifications = refreshNotifDot;

ensureSession().then(refreshNotifDot);
