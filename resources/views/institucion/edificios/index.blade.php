@extends('adminlte::page')

@section('title', 'Listado de Edificios')

@section('content_header')
  <h1 class="text-center w-100">Listado de Edificios</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Edificios registrados</h3>

          <div class="card-tools">
            @can('crear edificios')
              <a href="{{ route('institucion.edificios.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-square"></i> Añadir nuevo edificio
              </a>
            @endcan
          </div>
        </div>

        <div class="card-body">
          <table id="tablaEdificios" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Nombre completo</th>
                <th class="text-center">Plantas</th>
                <th>Áreas</th>
                <th class="text-center">Salones</th>
                <th class="text-center no-export">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($edificios as $e)
                @php
                  // Fallback seguro: si no hay row_id, usamos building_name como identificador de ruta
                  $rid       = $e->row_id ?? $e->building_name; 
                  $formKey   = md5((string)$rid); // para identificar el form en el DOM
                  $token     = $e->building_token ?: '—';
                  $nombre    = $e->building_name ?? '—';
                  $baja      = (int)($e->planta_baja ?? 0) === 1;
                  $alta      = (int)($e->planta_alta ?? 0) === 1;
                  $areasStr  = trim((string)($e->areas ?? ''));
                  $areasList = $areasStr !== '' ? explode(',', $areasStr) : [];
                  $salones   = (int)($e->classrooms_count ?? 0);
                @endphp
                <tr>
                  {{-- # se rellena en DataTables --}}
                  <td class="text-center"></td>

                  {{-- Nombre completo (EDIFICIO-A, etc.) --}}
                  <td data-order="{{ $nombre }}">{{ $nombre }}</td>

                  {{-- Plantas: badges y orden BAJA < ALTA < ninguna --}}
                  <td class="text-center" data-order="{{ $baja ? 0 : ($alta ? 1 : 2) }}">
                    @if($baja)
                      <span class="badge badge-primary">BAJA</span>
                    @endif
                    @if($alta)
                      <span class="badge badge-info">ALTA</span>
                    @endif
                    @if(!$baja && !$alta)
                      <span class="text-muted">—</span>
                    @endif
                  </td>

                  {{-- Áreas como badges --}}
                  <td>
                    @forelse($areasList as $a)
                      <span class="badge badge-secondary mr-1">{{ trim($a) }}</span>
                    @empty
                      <span class="text-muted">—</span>
                    @endforelse
                  </td>

                  {{-- # Salones --}}
                  <td class="text-center" data-order="{{ $salones }}">{{ $salones }}</td>

                  {{-- Acciones --}}
                  <td class="text-center no-export">
                    <div class="btn-group" role="group">
                      <a href="{{ route('institucion.edificios.show', $rid) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>
                      @can('editar edificios')
                        <a href="{{ route('institucion.edificios.edit', $rid) }}" class="btn btn-success btn-sm" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                      @endcan
                      @can('eliminar edificios')
                        <form action="{{ route('institucion.edificios.destroy', $rid) }}" method="POST" id="formEliminarEdificio-{{ $formKey }}">
                          @csrf
                          @method('DELETE')
                          <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarEdificio('formEliminarEdificio-{{ $formKey }}', this)" title="Eliminar">
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

@section('css')
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- Extensiones DataTables Buttons (sin repetir el core) --}}
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
function confirmarEliminarEdificio(formId, btn){
  const form = document.getElementById(formId);
  if(!form){ console.error('No existe', formId); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar edificio',
    text: '¿Desea eliminar este edificio?',
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
  function limpiar(data){
    if(typeof data !== 'string') return data;
    return $('<div>').html(data).text().trim();
  }

  const dt = $("#tablaEdificios").DataTable({
    pageLength: 10,
    lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todos']],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    responsive: true, lengthChange: true, autoWidth: false,
    dom: 'Blfrtip',
    order: [[1,'asc'], [2,'asc']],
    columnDefs: [
      { targets: 0,  orderable: false, searchable: false },
      { targets: -1, orderable: false, searchable: false },
      { targets: [1,3,5], className: 'text-center' },
      { targets: 5, type: 'num' },
    ],
    buttons: [
      {
        extend:'collection',
        text:'Opciones',
        buttons: [
          { extend:'copyHtml5', text:'Copiar', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'csvHtml5',  text:'CSV',    filename:'Edificios', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'excelHtml5',text:'Excel',  filename:'Edificios', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'pdfHtml5',  text:'PDF',    filename:'Edificios', title:'Listado de Edificios', orientation:'landscape', pageSize:'LEGAL',
            exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} },
            customize: function (doc) {
              doc.pageMargins = [24,24,24,24];
              doc.defaultStyle.fontSize = 9;
              doc.styles.tableHeader = { bold:true, alignment:'center' };
            }
          },
          { extend:'print',     text:'Imprimir', title:'Listado de Edificios', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } }
        ]
      },
      { extend:'colvis', text:'Visor de columnas', collectionLayout:'fixed three-column' }
    ],
    drawCallback: function(){
      const api = this.api();
      const start = api.page.info().start;
      api.column(0, {search:'applied', order:'applied'}).nodes().each(function(cell, i){
        cell.innerHTML = start + i + 1;
      });
    }
  });

  dt.buttons().container().appendTo('#tablaEdificios_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
