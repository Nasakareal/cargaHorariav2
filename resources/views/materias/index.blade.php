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
                        <a href="{{ route('materias.edit', $id) }}" class="btn btn-success btn-sm" title="Editar">
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
    // mantenemos tu UX: buscador + 10 por página
    pageLength: 10,
    lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todas']],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    responsive: true, lengthChange: true, autoWidth: false,
    // añadimos los botones al layout
    dom: 'Blfrtip',
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
              // SOLO lo filtrado y la página actual
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
});
</script>
@endsection
