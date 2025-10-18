<section class="space-y-6" data-tab="resumen">
  <div id="summaryCards" class="grid grid-cols-1 sm:grid-cols-2 gap-4"></div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-700">Ingresos mensuales</h3>
        <span id="monthlyTotal" class="text-xs text-slate-500"></span>
      </div>
      <div id="monthlyTrend" class="p-5 text-sm text-slate-600 space-y-3"></div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-700">Sedes con más ventas</h3>
        <span class="text-xs text-slate-500">Últimos 90 días</span>
      </div>
      <div id="topSedes" class="p-5 text-sm text-slate-600 space-y-3"></div>
    </div>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-sm font-semibold text-slate-700">Cobros recientes</h3>
      <button id="viewAllSales" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs hover:bg-slate-200">
        <i data-feather="external-link"></i>
        Ver módulo de ventas
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 text-slate-600 uppercase text-xs">
          <tr>
            <th class="text-left px-4 py-3 font-medium">Fecha</th>
            <th class="text-left px-4 py-3 font-medium">Alumno</th>
            <th class="text-left px-4 py-3 font-medium">Curso</th>
            <th class="text-left px-4 py-3 font-medium">Sede</th>
            <th class="text-right px-4 py-3 font-medium">Monto</th>
          </tr>
        </thead>
        <tbody id="recentSales" class="divide-y divide-slate-100"></tbody>
      </table>
    </div>
  </div>
</section>
