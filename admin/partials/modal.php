<div id="modal" class="fixed inset-0 bg-black/40 hidden items-center justify-center px-4 z-50">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 id="modalTitle" class="text-lg font-semibold">Mensaje</h3>
      <button class="text-slate-500 hover:text-slate-700" onclick="window.modal.close()">
        <i data-feather="x"></i>
      </button>
    </div>
    <div id="modalBody" class="px-5 py-6 text-sm text-slate-600 space-y-4"></div>
    <div class="px-5 py-4 border-t bg-slate-50 flex justify-end gap-3">
      <button class="px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-100" onclick="window.modal.close()">Cerrar</button>
      <button id="modalPrimary" class="hidden px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-black"></button>
    </div>
  </div>
</div>
