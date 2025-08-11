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
                <th class="text-center">Programas</th>
                <th class="text-center">Cuatrimestre</th>
                <th class="text-center">Unidades</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>

            <tbody>
              @foreach ($materias as $i => $m)
                @php
                  $id        = $m->subject_id ?? $m->id ?? null;
                  $nombre    = $m->subject_name ?? '';
                  $hcons     = $m->max_consecutive_class_hours ?? $m->hours_consecutive ?? '—';
                  $hsem      = $m->weekly_hours ?? 0;
                  $unidades    = $m->unidades ?? '';

                  // Limitar a 5 como hicimos en profesores
                  $programasTexto = 'No asignado';
                  if (!empty($m->programas)) {
                      $progs = explode(', ', $m->programas);
                      $programasTexto = implode(', ', array_slice($progs, 0, 5));
                      if (count($progs) > 5) $programasTexto .= ', ...';
                  }

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

                  <td class="text-center">
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
                        <form action="{{ route('materias.destroy', $id) }}"
                              method="POST"
                              id="formEliminarMateria-{{ $id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button"
                                  class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarMateria('{{ $id }}', this)"
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
  const dt = $("#tablaMaterias").DataTable({
    pageLength: 10,
    language: {
      emptyTable: "No hay información",
      info: "Mostrando _START_ a _END_ de _TOTAL_ Materias",
      infoEmpty: "Mostrando 0 a 0 de 0 Materias",
      infoFiltered: "(Filtrado de _MAX_ total Materias)",
      lengthMenu: "Mostrar _MENU_ Materias",
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
  dt.buttons().container().appendTo('#tablaMaterias_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
