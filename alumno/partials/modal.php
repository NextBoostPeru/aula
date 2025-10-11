<div id="modal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4 z-50">
  <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl">
    <div class="flex items-center justify-between p-4 border-b">
      <h3 id="modalTitle" class="text-lg font-semibold">Mensaje</h3>
      <button id="modalClose" class="p-2 rounded-lg hover:bg-gray-100" aria-label="Cerrar">
        <i data-feather="x"></i>
      </button>
    </div>
    <div id="modalBody" class="p-4 text-sm text-gray-700"></div>
    <div class="p-4 border-t text-right space-x-2">
      <button id="modalCancel" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Cerrar</button>
      <button id="modalPrimary" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 hidden">Aceptar</button>
    </div>
  </div>
</div>
