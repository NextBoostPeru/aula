<section class="space-y-6" data-tab="ventas">
  <form id="salesFilters" class="bg-white rounded-2xl shadow-sm border border-slate-200 px-5 py-4 grid grid-cols-1 md:grid-cols-5 gap-4">
    <div>
      <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Sede</label>
      <select id="filterSede" name="sede_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Método</label>
      <select id="filterMethod" name="method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todos</option>
        <option value="efectivo">Efectivo</option>
        <option value="yape">Yape</option>
        <option value="transferencia">Transferencia</option>
        <option value="otros">Otros</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Desde</label>
      <input type="date" id="filterFrom" name="from" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Hasta</label>
      <input type="date" id="filterTo" name="to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Buscar</label>
      <input type="search" id="filterSearch" name="q" placeholder="Alumno, referencia..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
    </div>
    <div class="md:col-span-5 flex flex-wrap gap-2 justify-end">
      <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900 text-white text-sm">
        <i data-feather="refresh-cw"></i>
        Aplicar filtros
      </button>
      <button type="button" id="resetFilters" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-600">
        <i data-feather="x"></i>
        Limpiar
      </button>
    </div>
  </form>

  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-sm font-semibold text-slate-700">Pagos registrados</h3>
      <span id="salesSummary" class="text-xs text-slate-500"></span>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 text-slate-600 uppercase text-xs">
          <tr>
            <th class="text-left px-4 py-3 font-medium">Fecha</th>
            <th class="text-left px-4 py-3 font-medium">Alumno</th>
            <th class="text-left px-4 py-3 font-medium">Curso</th>
            <th class="text-left px-4 py-3 font-medium">Sede</th>
            <th class="text-left px-4 py-3 font-medium">Método</th>
            <th class="text-left px-4 py-3 font-medium">Referencia</th>
            <th class="text-right px-4 py-3 font-medium">Monto</th>
          </tr>
        </thead>
        <tbody id="salesTable" class="divide-y divide-slate-100"></tbody>
      </table>
    </div>
    <div id="salesPagination" class="px-5 py-4 border-t bg-slate-50 flex flex-wrap gap-2 items-center justify-between text-xs text-slate-500"></div>
  </div>
</section>
