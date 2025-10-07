<div id="modal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4 z-50">
  <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl">
    <div class="flex items-center justify-between p-4 border-b">
      <h3 id="modalTitle" class="text-lg font-semibold">Título</h3>
      <button id="modalClose" class="p-2 hover:bg-gray-100 rounded-lg"><i data-feather="x"></i></button>
    </div>
    <div id="modalBody" class="p-4"></div>
    <div class="p-4 border-t flex items-center justify-end gap-2">
      <button id="modalCancel" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Cerrar</button>
      <button id="modalPrimary" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 hidden">Aceptar</button>
    </div>
  </div>
</div>
