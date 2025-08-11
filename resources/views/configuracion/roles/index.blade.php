@extends('adminlte::page')

@section('title', 'Listado de Roles')

@section('content_header')
    <h1 class="text-center w-100">Listado de Roles</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Roles registrados</h3>
          @can('crear roles')
            <div class="card-tools">
              <a href="{{ route('configuracion.roles.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-square"></i> Crear nuevo rol
              </a>
            </div>
          @endcan
        </div>

        <div class="card-body">
          <table id="tablaRoles" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Rol</th>
                <th class="text-center">Usuarios</th>
                <th>Creación</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($roles as $i => $rol)
                <tr>
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td>{{ $rol->name }}</td>
                  <td class="text-center">{{ $rol->users_count ?? 0 }}</td>
                  <td>{{ optional($rol->created_at)->format('Y-m-d H:i') }}</td>
                  <td class="text-center">
                    <div class="btn-group" role="group">
                      <a href="{{ route('configuracion.roles.show', $rol->id) }}" class="btn btn-info btn-sm">
                        <i class="bi bi-eye"></i>
                      </a>

                        @can('editar roles')
                        <a href="{{ route('configuracion.roles.edit', $rol->id) }}" class="btn btn-success btn-sm">
                          <i class="bi bi-pencil"></i>
                        </a>

                        {{-- Botón amarillo para gestionar permisos del rol --}}
                        <a href="{{ route('configuracion.roles.permissions', $rol->id) }}" class="btn btn-warning btn-sm" title="Permisos">
                          <i class="bi bi-shield-lock"></i>
                        </a>
                      @endcan

                      @can('eliminar roles')
                        <form action="{{ route('configuracion.roles.destroy', $rol->id) }}"
                              method="POST"
                              id="formEliminarRol-{{ $rol->id }}">
                          @csrf
                          @method('DELETE')
                          <button type="button"
                                  class="btn btn-danger btn-sm"
                                  onclick="confirmarEliminarRol('{{ $rol->id }}', this)">
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
function confirmarEliminarRol(id, btn){
  const form = document.getElementById('formEliminarRol-' + id);
  if(!form){ console.error('No existe formEliminarRol-', id); return; }

  btn.disabled = true;

  if (typeof Swal === 'undefined') {
    if (confirm('¿Desea eliminar este Rol?')) form.submit();
    else btn.disabled = false;
    return;
  }

  Swal.fire({
    title: 'Eliminar Rol',
    text: '¿Desea eliminar este Rol?',
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

{{-- Alertas post-redirect (flash) --}}
@if (session('success'))
<script>
Swal.fire({
  icon: 'success',
  title: @json(session('success')),
  showConfirmButton: false,
  timer: 2500,
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
  const dt = $("#tablaRoles").DataTable({
    pageLength: 10,
    language: {
      emptyTable: "No hay información",
      info: "Mostrando _START_ a _END_ de _TOTAL_ Roles",
      infoEmpty: "Mostrando 0 a 0 de 0 Roles",
      infoFiltered: "(Filtrado de _MAX_ total Roles)",
      lengthMenu: "Mostrar _MENU_ Roles",
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
  dt.buttons().container().appendTo('#tablaRoles_wrapper .col-md-6:eq(0)');
});
</script>
@endsection
