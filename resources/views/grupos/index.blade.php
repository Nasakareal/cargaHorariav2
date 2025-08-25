@extends('adminlte::page')

@section('title', 'Listado de Grupos')

@section('content_header')
  <h1 class="text-center w-100">Listado de Grupos</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">

        {{-- CABECERA --}}
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Grupos registrados</h3>

          <div class="card-tools d-flex align-items-center">
            @can('crear grupos')
              {{-- NUEVO: Botón Cargar Excel --}}
              <button type="button"
                      class="btn btn-outline-primary btn-sm mr-2"
                      data-toggle="modal"
                      data-target="#modalImportExcel">
                <i class="bi bi-file-earmark-spreadsheet"></i> Cargar Excel
              </button>
            @endcan

            @can('crear grupos')
              <a href="{{ route('grupos.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-square"></i> Añadir nuevo grupo
              </a>
            @endcan
          </div>
        </div>

        {{-- CUERPO --}}
        <div class="card-body">

          {{-- NUEVO: Mensajes flash --}}
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger" style="white-space: pre-line;">{{ session('error') }}</div>
          @endif

          <table id="tablaGrupos" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Grupo</th>
                <th>Programa</th>
                <th>Área</th>
                <th class="text-center">Cuatr.</th>
                <th>Turno</th>
                <th class="text-center">Volumen</th>
                <th class="text-center">Materias</th>
                <th class="text-center">Asignadas</th>
                <th class="text-center">Faltantes</th>
                <th class="text-center no-export">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($grupos as $i => $g)
                @php
                  $id     = $g->group_id ?? $g->id ?? null;
                  $grupo  = $g->group_name ?? '';
                  $prog   = $g->program_name ?? '—';
                  $area   = $g->area ?? '—';
                  $term   = $g->term_name ?? '—';
                  $turno  = $g->shift_name ?? '—';
                  $vol    = (int)($g->volume ?? 0);
                  $tot    = (int)($g->total_materias ?? 0);
                  $asig   = (int)($g->materias_asignadas ?? 0);
                  $falt   = (int)($g->materias_no_cubiertas ?? max($tot - $asig, 0));
                  $orderTerm = is_numeric($term) ? (int)$term : $term;
                @endphp
                <tr>
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td>{{ $grupo }}</td>
                  <td>{{ $prog }}</td>
                  <td>{{ $area }}</td>
                  <td class="text-center" data-order="{{ $orderTerm }}">{{ $term }}</td>
                  <td>{{ $turno }}</td>
                  <td class="text-center">{{ $vol }}</td>
                  <td class="text-center">{{ $tot }}</td>
                  <td class="text-center">{{ $asig }}</td>
                  <td class="text-center {{ $falt > 0 ? 'text-danger font-weight-bold' : 'text-success' }}">
                    {{ $falt }}
                  </td>

                  <td class="text-center no-export">
                    <div class="btn-group" role="group">
                      <a href="{{ route('grupos.show', $id) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>

                      @can('editar grupos')
                        <a href="{{ route('grupos.edit', $id) }}" class="btn btn-success btn-sm" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                      @endcan

                      @can('eliminar grupos')
                        <form action="{{ route('grupos.destroy', $id) }}" method="POST" id="formEliminarGrupo-{{ $id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarGrupo('{{ $id }}', this)" title="Eliminar">
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
        </div> {{-- /card-body --}}

      </div>
    </div>
  </div>
</div>

{{-- NUEVO: Modal Importar Excel (Bootstrap 4 / AdminLTE 3) --}}
@can('crear grupos')
<div class="modal fade" id="modalImportExcel" tabindex="-1" role="dialog" aria-labelledby="modalImportExcelLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form action="{{ route('grupos.excel.import') }}" method="POST" enctype="multipart/form-data" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title" id="modalImportExcelLabel">Importar grupos desde Excel/CSV</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <p class="mb-2">
          El archivo debe tener <strong>exactamente 9 columnas</strong> y se
          <strong>omiten las primeras 3 filas</strong>.
        </p>
        <ul class="small mb-3">
          <li>0: No.</li>
          <li>1: Área</li>
          <li>2: Abreviatura</li>
          <li>3: Programa</li>
          <li>4: Nivel educativo</li>
          <li>5: Cuatrimestre (número)</li>
          <li>6: Sufijo (A, B, ...)</li>
          <li>7: Turno</li>
          <li>8: Volumen</li>
        </ul>

        <div class="mb-2">
          <a href="{{ route('grupos.excel.plantilla') }}" class="btn btn-link p-0">Descargar plantilla (CSV)</a>
        </div>

        <div class="form-group mb-0">
          <label for="file">Archivo (.xlsx o .csv)</label>
          <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" required>
          @error('file')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-upload"></i> Importar
        </button>
      </div>
    </form>
  </div>
</div>
@endcan
@endsection

{{-- SOLO estilos de Buttons (AdminLTE ya trae DataTables core) --}}
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
function confirmarEliminarGrupo(id, btn){
  const form = document.getElementById('formEliminarGrupo-' + id);
  if(!form){ console.error('No existe formEliminarGrupo-', id); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar Grupo',
    text: '¿Desea eliminar este grupo?',
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
    data = data.replace(/<br\s*\/?>/gi, '\n').replace(/\u00A0/g, ' ');
    return $('<div>').html(data).text().replace(/[ \t]+\n/g, '\n').replace(/[ \t]{2,}/g,' ').trim();
  }

  const dt = $("#tablaGrupos").DataTable({
    pageLength: 10,
    lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todas']],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    responsive: true, lengthChange: true, autoWidth: false,
    dom: 'Blfrtip',
    order: [[2,'asc'], [4,'asc'], [1,'asc']],
    columnDefs: [
      { targets: 0,  orderable: false, searchable: false },
      { targets: -1, orderable: false, searchable: false },
      { targets: 4,  className: 'text-center' },
      { targets: [6,7,8,9], className: 'text-center' },
    ],
    buttons: [
      {
        extend:'collection',
        text:'Opciones',
        buttons: [
          { extend:'copyHtml5', text:'Copiar',
            exportOptions:{ columns: ':not(.no-export)', stripHtml:true,
              modifier:{ search:'applied', order:'applied', page:'current' },
              format:{ body:limpiar, header:limpiar } }
          },
          { extend:'csvHtml5', text:'CSV', filename:'Grupos',
            exportOptions:{ columns: ':not(.no-export)', stripHtml:true,
              modifier:{ search:'applied', order:'applied', page:'current' },
              format:{ body:limpiar, header:limpiar } }
          },
          { extend:'excelHtml5', text:'Excel', filename:'Grupos',
            exportOptions:{ columns: ':not(.no-export)', stripHtml:true,
              modifier:{ search:'applied', order:'applied', page:'current' },
              format:{ body:limpiar, header:limpiar } }
          },
          { extend:'pdfHtml5', text:'PDF', filename:'Grupos', title:'Listado de Grupos',
            orientation:'landscape', pageSize:'LEGAL',
            exportOptions:{ columns: ':not(.no-export)', stripHtml:true,
              modifier:{ search:'applied', order:'applied', page:'current' },
              format:{ body:limpiar, header:limpiar } },
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
          { extend:'print', text:'Imprimir', title:'Listado de Grupos',
            exportOptions:{ columns: ':not(.no-export)', stripHtml:true,
              modifier:{ search:'applied', order:'applied', page:'current' },
              format:{ body:limpiar, header:limpiar } }
          }
        ]
      },
      { extend:'colvis', text:'Visor de columnas', collectionLayout:'fixed three-column' }
    ]
  });

  dt.buttons().container().appendTo('#tablaGrupos_wrapper .col-md-6:eq(0)');

  @if ($errors->has('file'))
    $('#modalImportExcel').modal('show');
  @endif
});
</script>
@endsection
