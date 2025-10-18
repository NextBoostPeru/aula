const ABSOLUTE_URL_REGEX = /^(?:[a-z]+:)?\/\//i;
const API_BASE_URL = new URL('../backend/', window.location.href);
const ADMIN_API_BASE_URL = new URL('./admin/', API_BASE_URL);

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
  const text = await response.text();

  if (!response.ok) {
    let message = text || response.statusText || 'Error en la solicitud';
    try {
      const parsed = JSON.parse(text);
      if (parsed && typeof parsed === 'object' && parsed.msg) {
        message = parsed.msg;
      }
    } catch (error) {
      // ignore parse error
    }
    throw new Error(message);
  }

  if (!text) return null;

  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return JSON.parse(text);
  }

  try {
    return JSON.parse(text);
  } catch (error) {
    return text;
  }
}

window.api = api;

window.adminApi = async (endpoint, opt = {}) => {
  const { searchParams, ...options } = opt;
  const url = normalizeApiInput(endpoint, ADMIN_API_BASE_URL);
  if (url.origin !== ADMIN_API_BASE_URL.origin) {
    throw new Error('Endpoint fuera del rango permitido.');
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

window.formatCurrency = (value) => {
  const amount = Number.isFinite(value) ? value : 0;
  return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount);
};

window.formatDateTime = (input) => {
  if (!input) return '';
  const date = new Date(input.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return input;
  return date.toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' });
};

window.page = {
  setTitle(text) {
    const el = $('#title');
    if (el) el.textContent = text || '';
  },
  setSubtitle(text) {
    const el = $('#subtitle');
    if (el) el.textContent = text || '';
  },
  setActions(html) {
    const el = $('#actions');
    if (!el) return;
    el.innerHTML = html || '';
    if (window.feather) window.feather.replace();
  },
  clearActions() {
    this.setActions('');
  },
  showLoading(container, message = 'Cargando...') {
    if (!container) return;
    container.innerHTML = `
      <div class="bg-white rounded-2xl p-6 shadow flex items-center gap-3 text-slate-500">
        <i data-feather="loader" class="animate-spin"></i>
        <span>${escapeHTML(message)}</span>
      </div>`;
    if (window.feather) window.feather.replace();
  },
  showInfo(container, message) {
    if (!container) return;
    container.innerHTML = `<div class="bg-slate-100 text-slate-600 rounded-2xl p-6 text-sm">${escapeHTML(message)}</div>`;
  },
  showError(container, message) {
    if (!container) return;
    container.innerHTML = `<div class="bg-red-100 text-red-700 rounded-2xl p-6 text-sm">${escapeHTML(message)}</div>`;
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
      primary.textContent = '';
      primary.classList.add('hidden');
      primary.onclick = null;
    }
    const modalEl = $('#modal');
    if (modalEl) {
      modalEl.classList.remove('hidden');
      modalEl.classList.add('flex');
      if (window.feather) window.feather.replace();
    }
  },
  close() {
    const modalEl = $('#modal');
    if (modalEl) {
      modalEl.classList.add('hidden');
      modalEl.classList.remove('flex');
    }
  },
  info(message) {
    this.open({
      title: 'Información',
      bodyHTML: `<p class="text-sm">${escapeHTML(message || '')}</p>`
    });
  },
  error(message) {
    this.open({
      title: 'Error',
      bodyHTML: `<div class="text-sm text-red-700">${escapeHTML(message || 'Ocurrió un error inesperado')}</div>`
    });
  }
};

