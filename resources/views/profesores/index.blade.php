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
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($profesores as $i => $p)
                @php
                  // Soporta propiedades con nombres distintos (del query o del modelo)
                  $id        = $p->teacher_id ?? $p->id ?? null;
                  $nombre    = $p->profesor ?? $p->teacher_name ?? '';
                  $clasif    = $p->clasificacion ?? 'No asignado';
                  $materias  = $p->materias ?? '—';
                  $horas     = $p->horas_semanales ?? $p->hours ?? 0;

                  // Limitar programas a 5 como en el PHP puro
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

                  <td class="text-center">
                    <div class="btn-group" role="group">
                      <a href="{{ route('profesores.show', $id) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>

                      @can('editar profesores')
                        <a href="{{ route('profesores.edit', $id) }}" class="btn btn-success btn-sm" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                        {{-- Asignar materias (botón amarillo) --}}
                        <a href="{{ route('profesores.asignar-materias', $id) }}" class="btn btn-warning btn-sm" title="Asignar materias">
  <i class="bi bi-journal-text"></i>
</a>

                      @endcan

                      @can('eliminar profesores')
                        <form action="{{ route('profesores.destroy', $id) }}"
                              method="POST"
                              id="formEliminarProfesor-{{ $id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button"
                                  class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarProfesor('{{ $id }}', this)"
                                  title="Eliminar">
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

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- Confirmación de borrado --}}
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
</script>

{{-- Flashes --}}
@if (session('success'))
<script>
Swal.fire({
  icon: 'success',
  title: @json(session('success')),
  showConfirmButton: false,
  timer: 6500,
  timerProgressBar: true,
  position: 'center'
});
</script>
@endif

@if (session('error'))
<script>
Swal.fire({
  icon: 'error',
  title: 'Ups',
  text: @json(session('error')),
  confirmButtonColor: '#E43636',
  position: 'center'
});
</script>
@endif

@if ($errors->any())
<script>
Swal.fire({
  icon: 'warning',
  title: 'Revisa los datos',
  html: `{!! implode('<br>', $errors->all()) !!}`,
  position: 'center'
});
</script>
@endif

{{-- DataTables --}}
<script>
$(function () {
  const dt = $("#tablaProfesores").DataTable({
    pageLength: 10,
    language: {
      emptyTable: "No hay información",
      info: "Mostrando _START_ a _END_ de _TOTAL_ Profesores",
      infoEmpty: "Mostrando 0 a 0 de 0 Profesores",
      infoFiltered: "(Filtrado de _MAX_ total Profesores)",
      lengthMenu: "Mostrar _MENU_ Profesores",
      search: "Buscador:",
      zeroRecords: "Sin resultados encontrados",
      paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" }
    },
    responsive: true, lengthChange: true, autoWidth: false,
    buttons: [
      { extend:'collection', text:'Opciones', orientation:'landscape', buttons:['copy','pdf','csv','excel','print'] },
      { extend:'colvis', text:'Visor de columnas', collectionLayout:'fixed three-column' }
    ]
  });
  dt.buttons().container().appendTo('#tablaProfesores_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
