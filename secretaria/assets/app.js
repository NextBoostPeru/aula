// utilidades
window.$ = (sel, ctx=document) => ctx.querySelector(sel);
window.$$ = (sel, ctx=document) => [...ctx.querySelectorAll(sel)];
window.api = async (url, opt={}) => {
  const r = await fetch(url, opt);
  const t = await r.text();
  if (!r.ok) throw new Error(t);
  try { return JSON.parse(t); } catch { throw new Error(t); }
};
window.modal = {
  open({title, bodyHTML, primaryLabel, onPrimary}){
    $('#modalTitle').textContent = title || 'Mensaje';
    $('#modalBody').innerHTML = bodyHTML || '';
    const p = $('#modalPrimary');
    if (primaryLabel && typeof onPrimary==='function'){
      p.textContent = primaryLabel; p.classList.remove('hidden');
      p.onclick = onPrimary;
    } else { p.classList.add('hidden'); p.onclick = null; }
    const m = $('#modal'); m.classList.remove('hidden'); m.classList.add('flex');
    feather.replace();
  },
  close(){ const m=$('#modal'); m.classList.add('hidden'); m.classList.remove('flex'); },
  ok(msg){ this.open({title:'Listo', bodyHTML:`<div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-xl p-3">${msg||'OK'}</div>`}); },
  err(msg){ this.open({title:'Error', bodyHTML:`<div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl p-3">${msg||'Ocurrió un error'}</div>`}); }
};

// ------- Persistencia de contexto (Sede/Aula/Tab) -------
const CTX_KEY = 'sec_ctx_v1';   // { sedeId, aulaId }
const TAB_KEY = 'sec_tab_v1';   // 'estudiantes' | 'modulos' | ...

function saveCtx(obj){ try { localStorage.setItem(CTX_KEY, JSON.stringify(obj||{})); } catch {} }
function loadCtx(){ try { return JSON.parse(localStorage.getItem(CTX_KEY)||'{}'); } catch { return {}; } }
function saveTab(tab){ try { localStorage.setItem(TAB_KEY, tab||'estudiantes'); } catch {} }
function loadTab(){ try { return localStorage.getItem(TAB_KEY)||'estudiantes'; } catch { return 'estudiantes'; } }

function getContext(){
  return { sedeId: $('#selSede')?.value || '', aulaId: $('#selAula')?.value || '' };
}
function notifyContext(){
  const detail = getContext();
  document.dispatchEvent(new CustomEvent('contextChanged', { detail }));
}

// ------- Carga de filtros -------
async function loadSedes(){
  const selSede = $('#selSede');
  if (!selSede) return;
  selSede.innerHTML='<option value="">Selecciona...</option>';
  try {
    const r = await api('../backend/secretaria/sedes_mias.php');
    (r.sedes||[]).forEach(s=>selSede.insertAdjacentHTML('beforeend', `<option value="${s.id}">${s.nombre}</option>`));
  } catch {}
}

async function loadAulas(){
  const selAula = $('#selAula'); if(!selAula) return;
  selAula.innerHTML='<option value="">Selecciona...</option>';
  const sede = $('#selSede')?.value; if(!sede) return;
  try {
    const r=await api(`../backend/secretaria/aulas_por_sede.php?sede_id=${sede}`);
    (r.aulas||[]).forEach(a=>selAula.insertAdjacentHTML('beforeend', `<option value="${a.id}">${a.nombre}</option>`));
  } catch {}
}

function updateSidebarEnabled(){
  const ok = !!($('#selSede')?.value && $('#selAula')?.value);
  $$('#sidebarNav .sidebar-btn').forEach(b=>{
    const should = (b.id === 'btnMatricularAtajo') || b.matches('a');
    if (!should) return;
    b.classList.toggle('opacity-50', !ok);
    b.classList.toggle('cursor-not-allowed', !ok);
    b.toggleAttribute('aria-disabled', !ok);
  });
}

// ------- Eventos globales del modal -------
$('#modalClose')?.addEventListener('click', ()=>modal.close());
$('#modalCancel')?.addEventListener('click', ()=>modal.close());

// ------- Cambios en filtros (guardar en localStorage y NOT recargar) -------
$('#selSede')?.addEventListener('change', async ()=>{
  // al cambiar Sede, resetea Aula
  saveCtx({ sedeId: $('#selSede').value, aulaId: '' });
  await loadAulas();
  updateSidebarEnabled();
  notifyContext();
});

$('#selAula')?.addEventListener('change', ()=>{
  const sedeId = $('#selSede')?.value || '';
  const aulaId = $('#selAula')?.value || '';
  saveCtx({ sedeId, aulaId });     // ¡guardar!
  updateSidebarEnabled();
  notifyContext();
});

// ------- Atajo Matricular -------
$('#btnMatricularAtajo')?.addEventListener('click', ()=>{
  const {sedeId, aulaId} = getContext();
  if (!sedeId || !aulaId) return;
  const btn = $('#btnAdd'); btn?.click();
});

// ------- Guardar tab al navegar (para restaurar tras reload) -------
$$('#sidebarNav a[href*="?tab="]').forEach(a=>{
  a.addEventListener('click', ()=> {
    const url = new URL(a.href, location.href);
    const tab = url.searchParams.get('tab') || 'estudiantes';
    saveTab(tab);
  });
});

// ------- Init -------
(async function init(){
  try {
    const s = await api('../backend/session_check.php');
    if(!s.ok || !s.auth || s.user.role!=='secretaria'){ location.href='../login.html'; return; }
  } catch { location.href='../index.html'; return; }

  // 1) Cargar sedes
  await loadSedes();

  // 2) Restaurar Sede/Aula desde localStorage
  const ctx = loadCtx();             // {sedeId, aulaId}
  if (ctx.sedeId) {
    const opt = $(`#selSede option[value="${ctx.sedeId}"]`);
    if (opt) $('#selSede').value = ctx.sedeId;
  }
  // cargar aulas de esa sede
  await loadAulas();
  if (ctx.aulaId) {
    const optA = $(`#selAula option[value="${ctx.aulaId}"]`);
    if (optA) $('#selAula').value = ctx.aulaId;
  }

  updateSidebarEnabled();

  // 3) Notificar contexto inicial a las vistas
  notifyContext();

  // 4) (opcional) Si entras sin ?tab= en la URL, puedes redirigir al último tab usado:
  //    *No toca tu router actual.* Solo por comodidad si quieres:
  // if (!new URLSearchParams(location.search).get('tab')) {
  //   const lastTab = loadTab();
  //   if (lastTab && lastTab !== 'estudiantes') {
  //     location.search = '?tab=' + encodeURIComponent(lastTab);
  //   }
  // }
})();
