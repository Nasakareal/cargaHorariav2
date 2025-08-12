@extends('adminlte::page')

@section('title', 'Listado de Programas')

@section('content_header')
  <h1 class="text-center w-100">Listado de Programas</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h3 class="card-title mb-0">Programas registrados</h3>

          <div class="card-tools">
            <div class="btn-group" role="group">
              @can('crear programas')
                <a href="{{ route('programas.create') }}" class="btn btn-primary btn-sm">
                  <i class="bi bi-plus-square"></i> Nuevo programa
                </a>
              @endcan
            </div>
          </div>
        </div>

        <div class="card-body">
          <table id="tablaProgramas" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Programa</th>
                <th>Área</th>
                <th class="text-center">Grupos</th>
                <th class="text-center">Materias</th>
                <th class="text-center no-export">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($programas as $i => $p)
                @php
                  $id       = $p->program_id ?? $p->id ?? null;
                  $nombre   = $p->program_name ?? '';
                  $area     = $p->area ?? '—';
                  $grupos   = (int)($p->total_grupos   ?? 0);
                  $materias = (int)($p->total_materias ?? 0);
                @endphp
                <tr>
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td>{{ $nombre }}</td>
                  <td>{{ $area }}</td>
                  <td class="text-center">{{ $grupos }}</td>
                  <td class="text-center">{{ $materias }}</td>
                  <td class="text-center no-export">
                    <div class="btn-group" role="group">
                      <a href="{{ route('programas.show', $id) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>

                      @can('editar programas')
                        <a href="{{ route('programas.edit', $id) }}" class="btn btn-success btn-sm" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                      @endcan

                      @can('eliminar programas')
                        <form action="{{ route('programas.destroy', $id) }}" method="POST" id="formEliminarPrograma-{{ $id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarPrograma('{{ $id }}', this)" title="Eliminar">
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
function confirmarEliminarPrograma(id, btn){
  const form = document.getElementById('formEliminarPrograma-' + id);
  if(!form){ console.error('No existe formEliminarPrograma-', id); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar Programa',
    text: '¿Desea eliminar este programa?',
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

  const dt = $("#tablaProgramas").DataTable({
    pageLength: 10,
    lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todas']],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    responsive: true, lengthChange: true, autoWidth: false,

    dom: 'Blfrtip',

    order: [[1, 'asc']],
    columnDefs: [
      { targets: 0,  orderable: false, searchable: false }, // #
      { targets: -1, orderable: false, searchable: false }, // Acciones
    ],

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
            extend:'csvHtml5', text:'CSV', filename:'Programas',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            }
          },
          {
            extend:'excelHtml5', text:'Excel', filename:'Programas',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            }
          },
          {
            extend:'pdfHtml5', text:'PDF', filename:'Programas', title:'Listado de Programas',
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
            extend:'print', text:'Imprimir', title:'Listado de Programas',
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

  dt.buttons().container().appendTo('#tablaProgramas_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
