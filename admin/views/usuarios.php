<section class="space-y-6" data-tab="usuarios">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-sm font-semibold text-slate-700">Distribución por rol</h3>
      <span id="usersTotal" class="text-xs text-slate-500"></span>
    </div>
    <div id="rolesBreakdown" class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4"></div>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-sm font-semibold text-slate-700">Usuarios recientes</h3>
      <span class="text-xs text-slate-500">10 últimos registros</span>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 text-slate-600 uppercase text-xs">
          <tr>
            <th class="text-left px-4 py-3 font-medium">Nombre</th>
            <th class="text-left px-4 py-3 font-medium">Correo</th>
            <th class="text-left px-4 py-3 font-medium">Rol</th>
            <th class="text-left px-4 py-3 font-medium">Estado</th>
            <th class="text-left px-4 py-3 font-medium">Registrado</th>
          </tr>
        </thead>
        <tbody id="recentUsers" class="divide-y divide-slate-100"></tbody>
      </table>
    </div>
  </div>
</section>
