{{-- resources/views/configuracion/eliminar_materias/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Quitar materias a profesores')

@section('content_header')
    <h1 class="text-center w-100">Quitar materias a profesores</h1>
@endsection

@section('content')
<div class="container-xl">
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-primary">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">Profesores</h3>
                </div>

                <div class="card-body">
                    <table id="example1" class="table table-striped table-bordered table-hover table-sm">
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th>Profesor</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($profesores as $i => $p)
                                <tr>
                                    <td class="text-center">{{ ($profesores->firstItem() ?? 1) + $i }}</td>
                                    <td>{{ $p->teacher_name }}</td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            @can('eliminar materias')
                                                <a href="{{ route('configuracion.eliminar-materias.edit', $p->teacher_id) }}"
                                                   class="btn btn-danger btn-sm">
                                                    <i class="bi bi-scissors"></i> Quitar
                                                </a>
                                            @endcan

                                            {{-- (Opcional) acceso directo a asignar para alternar flujos --}}
                                            @can('asignar materias')
                                                <a href="{{ route('profesores.asignar-materias', $p->teacher_id) }}"
                                                   class="btn btn-success btn-sm">
                                                    <i class="bi bi-plus-square"></i> Asignar
                                                </a>
                                            @endcan

                                            {{-- (Opcional) ver perfil del profesor --}}
                                            <a href="{{ route('profesores.show', $p->teacher_id) }}"
                                               class="btn btn-info btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
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

{{-- Alertas post-redirect (flash) --}}
@if (session('success'))
<script>
Swal.fire({
  icon: 'success',
  title: @json(session('success')),
  showConfirmButton: false,
  timer: 8000,
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

{{-- DataTables (solo para mejorar la UI del listado actual) --}}
<script>
$(function () {
  const dt = $("#example1").DataTable({
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
  dt.buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
