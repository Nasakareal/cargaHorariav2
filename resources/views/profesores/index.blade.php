@extends('adminlte::page')

@section('title', 'Listado de Profesores')

@section('content_header')
  <h1 class="text-center w-100">Listado de Profesores</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Profesores registrados</h3>
          @can('crear profesores')
            <div class="card-tools">
              <a href="{{ route('profesores.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-square"></i> Añadir nuevo profesor
              </a>
            </div>
          @endcan
        </div>

        <div class="card-body">
          <table id="tablaProfesores" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Nombres</th>
                <th class="text-center">Clasificación</th>
                <th>Materias</th>
                <th class="text-center">Horas Semanales</th>
                <th>Programas</th>
                <th class="text-center no-export">Acciones</th> {{-- no se exporta --}}
              </tr>
            </thead>
            <tbody>
              @foreach ($profesores as $i => $p)
                @php
                  $id       = $p->teacher_id ?? $p->id ?? null;
                  $nombre   = $p->profesor ?? $p->teacher_name ?? '';
                  $clasif   = $p->clasificacion ?? 'No asignado';
                  $materias = $p->materias ?? '—';
                  $horas    = $p->horas_semanales ?? $p->hours ?? 0;

                  $programasTexto = 'No asignado';
                  if (!empty($p->programas)) {
                      $progs = explode(', ', $p->programas);
                      $programasTexto = implode(', ', array_slice($progs, 0, 5));
                      if (count($progs) > 5) $programasTexto .= ', ...';
                  }
                @endphp
                <tr>
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td>{{ $nombre }}</td>
                  <td class="text-center">{{ $clasif }}</td>
                  <td>{{ $materias }}</td>
                  <td class="text-center">{{ $horas }}</td>
                  <td>{{ $programasTexto }}</td>
                  <td class="text-center no-export">
                    <div class="btn-group" role="group">
                      <a href="{{ route('profesores.show', $id) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>

                      @can('editar profesores')
                        <a href="{{ route('profesores.edit', $id) }}" class="btn btn-success btn-sm" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <a href="{{ route('profesores.asignar-materias', $id) }}" class="btn btn-warning btn-sm" title="Asignar materias">
                          <i class="bi bi-journal-text"></i>
                        </a>
                      @endcan

                      @can('eliminar profesores')
                        <form action="{{ route('profesores.destroy', $id) }}" method="POST" id="formEliminarProfesor-{{ $id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarProfesor('{{ $id }}', this)" title="Eliminar">
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
function confirmarEliminarProfesor(id, btn){
  const form = document.getElementById('formEliminarProfesor-' + id);
  if(!form){ console.error('No existe formEliminarProfesor-', id); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar Profesor',
    text: '¿Desea eliminar este profesor?',
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
  // Limpieza básica para exportaciones
  function limpiar(data){
    if(typeof data !== 'string') return data;
    data = data.replace(/<br\s*\/?>/gi, '\n').replace(/\u00A0/g, ' ');
    return $('<div>').html(data).text().replace(/[ \t]+\n/g, '\n').replace(/[ \t]{2,}/g,' ').trim();
  }

  const dt = $("#tablaProfesores").DataTable({
    pageLength: 10,
    lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todas']],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    responsive: true, lengthChange: true, autoWidth: false,

    dom: 'Blfrtip',

    // ✅ Orden por Nombres y desactivar orden/búsqueda en # y Acciones
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
            extend:'csvHtml5', text:'CSV', filename:'Profesores',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            }
          },
          {
            extend:'excelHtml5', text:'Excel', filename:'Profesores',
            exportOptions:{
              columns: ':not(.no-export)',
              stripHtml: true,
              modifier: { search:'applied', order:'applied', page:'current' },
              format: { body:limpiar, header:limpiar }
            }
          },
          {
            extend:'pdfHtml5', text:'PDF', filename:'Profesores', title:'Listado de Profesores',
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
            extend:'print', text:'Imprimir', title:'Listado de Profesores',
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

  dt.buttons().container().appendTo('#tablaProfesores_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
