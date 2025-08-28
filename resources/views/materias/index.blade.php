@extends('adminlte::page')

@section('title', 'Listado de Materias')

@section('content_header')
  <h1 class="text-center w-100">Listado de Materias</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Materias registradas</h3>

          @can('crear materias')
            <div class="card-tools">
              <a href="{{ route('materias.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-square"></i> Añadir nueva materia
              </a>
            </div>
          @endcan
        </div>

        @php use Illuminate\Support\Str; @endphp

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
          <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-filter mr-1"></i> Programas
            </button>

            <div class="dropdown-menu p-3" style="min-width:320px; max-height: 360px; overflow:auto;">
              <div class="input-group input-group-sm mb-2">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input id="buscarProgramas" type="text" class="form-control" placeholder="Buscar programa...">
              </div>

              <div class="mb-2 d-flex justify-content-between align-items-center">
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-link p-0" id="progSelectAll">Seleccionar todo</button>
                  <span class="mx-2 text-muted">·</span>
                  <button type="button" class="btn btn-link p-0" id="progSelectNone">Ninguno</button>
                  <span class="mx-2 text-muted">·</span>
                  <button type="button" class="btn btn-link p-0" id="progInvert">Invertir</button>
                </div>
                <small class="text-muted" id="progResumen"></small>
              </div>

              <div id="listaProgramas">
                @foreach ($programas as $p)
                  @php $slug = Str::slug($p->program_name ?? 'sin-programa','_'); @endphp
                  <div class="custom-control custom-checkbox prog-item mb-1">
                    <input type="checkbox"
                           class="custom-control-input prog-check"
                           id="prog_{{ $slug }}"
                           value="{{ $p->program_name }}"
                           checked>
                    <label class="custom-control-label" for="prog_{{ $slug }}">
                      {{ $p->program_name }}
                    </label>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

          <span class="badge badge-light ml-2" id="progChip">Mostrando: todos</span>
        </div>



        <div class="card-body">
          <table id="tablaMaterias" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th class="text-center">Materia</th>
                <th class="text-center">Horas consecutivas</th>
                <th class="text-center">Horas semanales</th>
                <th class="text-center">Programa</th>
                <th class="text-center">Cuatrimestre</th>
                <th class="text-center">Unidades</th>
                <th class="text-center no-export">Acciones</th>
              </tr>
            </thead>

            <tbody>
              @foreach ($materias as $i => $m)
                @php
                  $id        = $m->subject_id ?? $m->id ?? null;
                  $nombre    = $m->subject_name ?? '';
                  $hcons     = $m->max_consecutive_class_hours ?? $m->hours_consecutive ?? '—';
                  $hsem      = $m->weekly_hours ?? 0;
                  $unidades  = $m->unidades ?? '';
                  $programasTexto = $m->programas ?? '';

                  $cuatrosTexto = 'No asignado';
                  if (!empty($m->cuatrimestres)) {
                      $cuts = explode(', ', $m->cuatrimestres);
                      $cuatrosTexto = implode(', ', array_slice($cuts, 0, 5));
                      if (count($cuts) > 5) $cuatrosTexto .= ', ...';
                  }
                @endphp

                <tr>
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td class="text-center">{{ $nombre }}</td>
                  <td class="text-center">{{ $hcons }}</td>
                  <td class="text-center">{{ $hsem }}</td>
                  <td class="text-center">{{ $programasTexto }}</td>
                  <td class="text-center">{{ $cuatrosTexto }}</td>
                  <td class="text-center">{{ $unidades }}</td>

                  <td class="text-center no-export">
                    <div class="btn-group" role="group">
                      <a href="{{ route('materias.show', $id) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>

                      @can('editar materias')
                        <a href="{{ route('materias.edit', $m->subject_id) }}?return_to={{ urlencode(request()->fullUrl()) }}" class="btn btn-success btn-sm">
                           <i class="bi bi-pencil"></i>
                        </a>

                      @endcan

                      @can('eliminar materias')
                        <form action="{{ route('materias.destroy', $id) }}" method="POST" id="formEliminarMateria-{{ $id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarMateria('{{ $id }}', this)" title="Eliminar">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      @endcan
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection

{{-- CSS DataTables + Buttons (solo estilos de Buttons, el core ya lo pone AdminLTE) --}}
@section('css')
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- SOLO extensiones necesarias (sin repetir DataTables core) --}}
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
function confirmarEliminarMateria(id, btn){
  const form = document.getElementById('formEliminarMateria-' + id);
  if(!form){ console.error('No existe formEliminarMateria-', id); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar Materia',
    text: '¿Desea eliminar esta materia?',
    icon: 'warning',
    showDenyButton: true,
    confirmButtonText: 'Eliminar',
    confirmButtonColor: '#E43636',
    denyButtonColor: '#007bff',
    denyButtonText: 'Cancelar',
    position: 'center'
  }).then((r)=>{
    if(r.isConfirmed){ form.submit(); }
    else { btn.disabled = false; }
  });
}

$(function () {
  // Limpia HTML en exportaciones
  function limpiar(data){
    if(typeof data !== 'string') return data;
    data = data.replace(/<br\s*\/?>/gi, '\n').replace(/\u00A0/g, ' ');
    return $('<div>').html(data).text().replace(/[ \t]+\n/g, '\n').replace(/[ \t]{2,}/g,' ').trim();
  }

  const dt = $("#tablaMaterias").DataTable({
    pageLength: 10,
    lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todas']],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    responsive: true, lengthChange: true, autoWidth: false,
    dom: 'Blfrtip',
    // <<< NUEVO: guarda estado de DataTables (búsqueda, página, colVis, etc.)
    stateSave: true,
    buttons: [
      {
        extend:'collection',
        text:'Opciones',
        buttons: [
          {
            extend:'copyHtml5', text:'Copiar',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            }
          },
          {
            extend:'csvHtml5', text:'CSV', filename:'Materias',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            }
          },
          {
            extend:'excelHtml5', text:'Excel', filename:'Materias',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            }
          },
          {
            extend:'pdfHtml5', text:'PDF', filename:'Materias', title:'Listado de Materias',
            orientation:'landscape', pageSize:'LEGAL',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            },
            customize: function (doc) {
              doc.pageMargins = [24,24,24,24];
              doc.defaultStyle.fontSize = 9;
              doc.styles.tableHeader = { bold:true, alignment:'center' };
              const t = doc.content.find(c => c.table);
              if (t && t.table && t.table.body && t.table.body[0]) {
                t.table.widths = Array(t.table.body[0].length).fill('*');
              }
            }
          },
          {
            extend:'print', text:'Imprimir', title:'Listado de Materias',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            }
          }
        ]
      },
      { extend:'colvis', text:'Visor de columnas', collectionLayout:'fixed three-column' }
    ]
  });

  // ubicar contenedor de botones como te gusta
  dt.buttons().container().appendTo('#tablaMaterias_wrapper .col-md-6:eq(0)');

  /* ========= Filtro bonito por Programas (checklist en dropdown) ========= */

  const $lista = $('#listaProgramas');
  const $checks = () => $lista.find('.prog-check');
  const $buscar = $('#buscarProgramas');
  const $resumen = $('#progResumen');
  const $chip = $('#progChip');

  // Conjunto de EXCLUIDOS (desmarcados). Por defecto vacío => se muestran todos.
  const excluidos = new Set();

  // <<< NUEVO: clave de almacenamiento para este índice
  const STORAGE_KEY = 'materiasProgExcluidos_v1';

  function totalProgramas(){ return $checks().length; }
  function seleccionados(){ return $checks().filter(':checked').length; }

  function refrescarResumen(){
    const sel = seleccionados();
    const tot = totalProgramas();
    $resumen.text(`Mostrando ${sel}/${tot}`);
    $chip.text(sel === tot ? 'Mostrando: todos' : `Mostrando: ${sel}/${tot}`);
  }

  // Guarda el set de excluidos en localStorage
  function saveFiltro(){
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(excluidos)));
    } catch(e) { /* nada */ }
  }

  // Restaura el set de excluidos desde localStorage
  function restoreFiltro(){
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return false;
      const arr = JSON.parse(raw);
      if (!Array.isArray(arr)) return false;

      excluidos.clear();
      arr.forEach(v => excluidos.add(String(v)));

      // Sincroniza checks: excluido => desmarcado, resto marcado
      $checks().each(function(){
        const val = (this.value || '').trim();
        $(this).prop('checked', !excluidos.has(val));
      });

      refrescarResumen();
      dt.draw();
      return true;
    } catch(e){ return false; }
  }

  // Búsqueda dentro del dropdown
  $buscar.on('input', function(){
    const q = (this.value || '').toLowerCase();
    $lista.find('.prog-item').each(function(){
      const txt = $(this).text().toLowerCase();
      $(this).toggle(txt.includes(q));
    });
  });

  // Botones rápidos
  $('#progSelectAll').on('click', function(){
    $checks().prop('checked', true).trigger('change');
  });
  $('#progSelectNone').on('click', function(){
    $checks().prop('checked', false).trigger('change');
  });
  $('#progInvert').on('click', function(){
    $checks().each(function(){
      $(this).prop('checked', !$(this).prop('checked'));
    }).trigger('change');
  });

  // Filtro DataTables con función personalizada (exclusión)
  $.fn.dataTable.ext.search.push(function(settings, data){
    if (settings.nTable && settings.nTable.id !== 'tablaMaterias') return true;
    const programa = (data[4] || '').trim(); // columna Programa
    return !excluidos.has(programa);
  });

  // Manejo de checks
  $lista.on('change', '.prog-check', function(){
    const val = (this.value || '').trim();
    if (!val) return;

    if (this.checked) { excluidos.delete(val); }
    else { excluidos.add(val); }

    refrescarResumen();
    saveFiltro();       // <<< NUEVO: persistimos el filtro
    dt.draw();
  });

  // Estado inicial
  // Intentar restaurar desde localStorage; si no hay nada, todos marcados
  const ok = restoreFiltro();
  if (!ok) {
    $checks().prop('checked', true);
    excluidos.clear();
    refrescarResumen();
    dt.draw();
  }
});
</script>
@endsection

