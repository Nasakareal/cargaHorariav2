{{-- resources/views/configuracion/usuarios/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Listado de Usuarios')

@section('content_header')
    <h1 class="text-center w-100">Listado de Usuarios</h1>
@endsection

@section('content')
<div class="container-xl">
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Usuarios registrados</h3>
                    @can('crear usuarios')
                        <div class="card-tools">
                            <a href="{{ route('configuracion.usuarios.create') }}" class="btn btn-primary">
                                <i class="bi bi-plus-square"></i> Crear nuevo usuario
                            </a>
                        </div>
                    @endcan
                </div>

                <div class="card-body">
                    <table id="example1" class="table table-striped table-bordered table-hover table-sm">
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th>Nombres</th>
                                <th>Rol</th>
                                <th>Email</th>
                                <th>Área</th>
                                <th>Creación</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($usuarios as $i => $usuario)
                                <tr>
                                    <td class="text-center">{{ $i + 1 }}</td>
                                    <td>{{ $usuario->nombres }}</td>
                                    <td>{{ $usuario->rol_nombre }}</td>
                                    <td>{{ $usuario->email }}</td>
                                    <td>{{ $usuario->area }}</td>
                                    <td>{{ optional($usuario->fyh_creacion)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $usuario->estado }}</td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('configuracion.usuarios.show', $usuario->id_usuario) }}" class="btn btn-info btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            @can('editar usuarios')
                                                <a href="{{ route('configuracion.usuarios.edit', $usuario->id_usuario) }}" class="btn btn-success btn-sm">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan

                                            @can('eliminar usuarios')
                                              <form action="{{ route('configuracion.usuarios.destroy', $usuario->id_usuario) }}"
                                                    method="POST"
                                                    id="formEliminar-{{ $usuario->id_usuario }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="confirmarEliminar('{{ $usuario->id_usuario }}', this)">
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
function confirmarEliminar(id, btn){
  const form = document.getElementById('formEliminar-' + id);
  if(!form){ console.error('No existe formEliminar-', id); return; }

  btn.disabled = true;

  if (typeof Swal === 'undefined') {
    if (confirm('¿Desea eliminar este Usuario?')) form.submit();
    else btn.disabled = false;
    return;
  }

  Swal.fire({
    title: 'Eliminar Usuario',
    text: '¿Desea eliminar este Usuario?',
    icon: 'warning',
    showDenyButton: true,
    confirmButtonText: 'Eliminar',
    confirmButtonColor: '#E43636',
    denyButtonColor: '#007bff',
    denyButtonText: 'Cancelar',
  }).then((r)=>{
    if(r.isConfirmed){
      form.submit();
    }else{
      btn.disabled = false;
    }
  });
}
</script>

{{-- Alertas post-redirect (flash) --}}
@if (session('success'))
<script>
Swal.fire({
  icon: 'success',
  title: @json(session('success')),
  showConfirmButton: false,
  timer: 10000,
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
  const dt = $("#example1").DataTable({
    pageLength: 5,
    language: {
      emptyTable: "No hay información",
      info: "Mostrando _START_ a _END_ de _TOTAL_ Usuarios",
      infoEmpty: "Mostrando 0 a 0 de 0 Usuarios",
      infoFiltered: "(Filtrado de _MAX_ total Usuarios)",
      lengthMenu: "Mostrar _MENU_ Usuarios",
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
