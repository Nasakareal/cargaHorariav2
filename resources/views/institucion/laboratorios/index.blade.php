@extends('adminlte::page')

@section('title', 'Listado de Laboratorios')

@section('content_header')
  <h1 class="text-center w-100">Listado de Laboratorios</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Laboratorios registrados</h3>

          <div class="card-tools">
            @can('crear laboratorios')
              <a href="{{ route('institucion.laboratorios.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-square"></i> Añadir nuevo laboratorio
              </a>
            @endcan
          </div>
        </div>

        <div class="card-body">
          <table id="tablaLaboratorios" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Laboratorio</th>
                <th>Áreas</th>
                <th class="text-center">Grupos</th>
                <th class="text-center">Actualizado</th>
                <th class="text-center no-export">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($laboratorios as $l)
                @php
                  $id        = $l->lab_id;
                  $nombre    = $l->lab_name ?? '—';
                  $areasList = collect(explode(',', (string)($l->area ?? '')))
                                ->map(fn($a) => trim($a))
                                ->filter()
                                ->values()
                                ->all();
                  $grupos    = (int)($l->grupos_count ?? 0);
                  $actualiz  = $l->fyh_actualizacion ?? $l->fyh_creacion ?? null;
                @endphp
                <tr>
                  {{-- # se rellena en DataTables --}}
                  <td class="text-center"></td>

                  {{-- Laboratorio --}}
                  <td data-order="{{ $nombre }}">{{ $nombre }}</td>

                  {{-- Áreas como badges --}}
                  <td>
                    @forelse($areasList as $a)
                      <span class="badge badge-secondary mr-1">{{ $a }}</span>
                    @empty
                      <span class="text-muted">—</span>
                    @endforelse
                  </td>

                  {{-- Grupos: asegura orden numérico aunque tenga <a> --}}
                  <td class="text-center" data-order="{{ $grupos }}">
                    @if($grupos > 0)
                      <a href="{{ route('institucion.laboratorios.show', $id) }}" title="Ver grupos">{{ $grupos }}</a>
                    @else
                      0
                    @endif
                  </td>

                  {{-- Última actualización/creación --}}
                  <td class="text-center" data-order="{{ $actualiz ?? '' }}">
                    {{ $actualiz ?? '—' }}
                  </td>

                  {{-- Acciones --}}
                  <td class="text-center no-export">
                    <div class="btn-group" role="group">
                      <a href="{{ route('institucion.laboratorios.show', $id) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>
                      @can('editar laboratorios')
                        <a href="{{ route('institucion.laboratorios.edit', $id) }}" class="btn btn-success btn-sm" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                      @endcan
                      @can('ver horarios')
                        <a href="{{ route('institucion.laboratorios.horario', $id) }}" class="btn btn-warning btn-sm" title="Horario">
                          <i class="bi bi-calendar2-week"></i>
                        </a>
                      @endcan
                      @can('eliminar laboratorios')
                        <form action="{{ route('institucion.laboratorios.destroy', $id) }}" method="POST" id="formEliminarLab-{{ $id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarLab('{{ $id }}', this)" title="Eliminar">
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
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
function confirmarEliminarLab(id, btn){
  const form = document.getElementById('formEliminarLab-' + id);
  if(!form){ console.error('No existe formEliminarLab-', id); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar laboratorio',
    text: '¿Desea eliminar este laboratorio?',
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

  const dt = $("#tablaLaboratorios").DataTable({
    pageLength: 10,
    lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todos']],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    responsive: true, lengthChange: true, autoWidth: false,
    dom: 'Blfrtip',
    order: [[1,'asc']],
    columnDefs: [
      { targets: 0, orderable: false, searchable: false },
      { targets: -1, orderable: false, searchable: false },
      { targets: [3,4], className: 'text-center' },
      { targets: 3, type: 'num' },
    ],
    buttons: [
      {
        extend:'collection',
        text:'Opciones',
        buttons: [
          { extend:'copyHtml5', text:'Copiar', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'csvHtml5',  text:'CSV',    filename:'Laboratorios', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'excelHtml5',text:'Excel',  filename:'Laboratorios', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'pdfHtml5',  text:'PDF',    filename:'Laboratorios', title:'Listado de Laboratorios', orientation:'landscape', pageSize:'LEGAL',
            exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} },
            customize: function (doc) {
              doc.pageMargins = [24,24,24,24];
              doc.defaultStyle.fontSize = 9;
              doc.styles.tableHeader = { bold:true, alignment:'center' };
            }
          },
          { extend:'print',     text:'Imprimir', title:'Listado de Laboratorios', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } }
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

  dt.buttons().container().appendTo('#tablaLaboratorios_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
