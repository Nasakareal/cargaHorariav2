@extends('adminlte::page')

@section('title', 'Listado de Salones')

@section('content_header')
  <h1 class="text-center w-100">Listado de Salones</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Salones registrados</h3>

          <div class="card-tools">
            @can('crear salones')
              <a href="{{ route('institucion.salones.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-square"></i> Añadir nuevo salón
              </a>
            @endcan
          </div>
        </div>

        <div class="card-body">
          <table id="tablaSalones" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Salón</th>
                <th>Edificio</th>
                <th class="text-center">Planta</th>
                <th class="text-center">Capacidad</th>
                <th class="text-center">Grupos</th>
                <th class="text-center">Estado</th>
                <th class="text-center no-export">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($salones as $s)
                @php
                  $id        = $s->classroom_id;
                  $token     = $s->building_token ?: '—';
                  $salonNum  = is_numeric($s->classroom_name) ? (int)$s->classroom_name : $s->classroom_name;
                  $nombre    = $s->classroom_name . '('. $token .')';
                  $edificio  = $token;
                  $plantaRaw = strtoupper(trim($s->floor ?? ''));
                  $planta    = in_array($plantaRaw, ['ALTA','BAJA']) ? $plantaRaw : '—';
                  $plantaOrd = $planta === 'BAJA' ? 0 : ($planta === 'ALTA' ? 1 : 2); // BAJA primero
                  $capacidad = (int)($s->capacity ?? 0);
                  $grupos    = (int)($s->grupos_count ?? 0);
                  $estadoTx  = strtoupper(trim($s->estado ?? ''));
                  $isActivo  = ($estadoTx === 'ACTIVO');
                @endphp
                <tr>
                  {{-- # se rellena en DataTables --}}
                  <td class="text-center"></td>

                  {{-- Salón: ordena por número (o texto si no es numérico) --}}
                  <td data-order="{{ $salonNum }}">{{ $nombre }}</td>

                  {{-- Edificio (token) --}}
                  <td class="text-center">{{ $edificio }}</td>

                  {{-- Planta: usa data-order para BAJA<ALTA<otros --}}
                  <td class="text-center" data-order="{{ $plantaOrd }}">
                    @if($planta === 'ALTA')
                      <span class="badge badge-info">ALTA</span>
                    @elseif($planta === 'BAJA')
                      <span class="badge badge-primary">BAJA</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>

                  <td class="text-center">{{ $capacidad }}</td>

                  {{-- Grupos: asegura orden numérico aunque tenga <a> --}}
                  <td class="text-center" data-order="{{ $grupos }}">
                    @if($grupos > 0)
                      <a href="{{ route('institucion.salones.show', $id) }}" title="Ver grupos">{{ $grupos }}</a>
                    @else
                      0
                    @endif
                  </td>

                  <td class="text-center {{ $isActivo ? 'text-success' : 'text-danger font-weight-bold' }}">
                    {{ $estadoTx ?: '—' }}
                  </td>

                  <td class="text-center no-export">
                    <div class="btn-group" role="group">
                      <a href="{{ route('institucion.salones.show', $id) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>
                      @can('editar salones')
                        <a href="{{ route('institucion.salones.edit', $id) }}" class="btn btn-success btn-sm" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                      @endcan
                      @can('ver horarios')
                        <a href="{{ route('institucion.salones.horario', $id) }}" class="btn btn-warning btn-sm" title="Horario">
                          <i class="bi bi-calendar2-week"></i>
                        </a>
                      @endcan
                      @can('eliminar salones')
                        <form action="{{ route('institucion.salones.destroy', $id) }}" method="POST" id="formEliminarSalon-{{ $id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarSalon('{{ $id }}', this)" title="Eliminar">
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
function confirmarEliminarSalon(id, btn){
  const form = document.getElementById('formEliminarSalon-' + id);
  if(!form){ console.error('No existe formEliminarSalon-', id); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar Salón',
    text: '¿Desea eliminar este salón?',
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

  const dt = $("#tablaSalones").DataTable({
    pageLength: 10,
    lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todos']],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    responsive: true, lengthChange: true, autoWidth: false,
    dom: 'Blfrtip',
    // Orden inicial: Edificio (2), Planta (3), Salón (1)
    order: [[2,'asc'], [3,'asc'], [1,'asc']],
    columnDefs: [
      { targets: 0, orderable: false, searchable: false }, // #
      { targets: -1, orderable: false, searchable: false }, // Acciones
      { targets: [3,4,5,6], className: 'text-center' }, // Planta, Capacidad, Grupos, Estado
      // Fuerza a tratar "Grupos" como numérico (por si falla el data-order)
      { targets: 5, type: 'num' },
    ],
    buttons: [
      {
        extend:'collection',
        text:'Opciones',
        buttons: [
          { extend:'copyHtml5', text:'Copiar', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'csvHtml5', text:'CSV', filename:'Salones', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'excelHtml5', text:'Excel', filename:'Salones', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } },
          { extend:'pdfHtml5', text:'PDF', filename:'Salones', title:'Listado de Salones', orientation:'landscape', pageSize:'LEGAL',
            exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} },
            customize: function (doc) {
              doc.pageMargins = [24,24,24,24];
              doc.defaultStyle.fontSize = 9;
              doc.styles.tableHeader = { bold:true, alignment:'center' };
            }
          },
          { extend:'print', text:'Imprimir', title:'Listado de Salones', exportOptions:{ columns: ':not(.no-export)', format:{body:limpiar} } }
        ]
      },
      { extend:'colvis', text:'Visor de columnas', collectionLayout:'fixed three-column' }
    ],
    // Renumera la primer columna después de ordenar/buscar/paginar
    drawCallback: function(settings){
      const api = this.api();
      const start = api.page.info().start;
      api.column(0, {search:'applied', order:'applied'}).nodes().each(function(cell, i){
        cell.innerHTML = start + i + 1;
      });
    }
  });

  dt.buttons().container().appendTo('#tablaSalones_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
