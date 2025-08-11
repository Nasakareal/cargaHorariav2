{{-- resources/views/configuracion/roles/show.blade.php --}}
@extends('adminlte::page')

@section('title', 'Detalle de Rol')

@section('content_header')
    <h1 class="text-center w-100">Detalle de rol</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-info">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Información del rol</h3>

          <div class="btn-group">
            <a href="{{ route('configuracion.roles.index') }}" class="btn btn-secondary btn-sm">
              <i class="bi bi-arrow-left"></i> Volver
            </a>

            @can('editar roles')
              {{-- Botón amarillo para gestionar permisos del rol --}}
              <a href="{{ route('configuracion.roles.permissions', $rol->id) }}" class="btn btn-warning btn-sm">
                <i class="bi bi-shield-lock"></i> Permisos
              </a>

              <a href="{{ route('configuracion.roles.edit', $rol->id) }}" class="btn btn-success btn-sm">
                <i class="bi bi-pencil"></i> Editar
              </a>
            @endcan

            @can('eliminar roles')
              <form action="{{ route('configuracion.roles.destroy', $rol->id) }}" method="POST" id="formEliminarRol-{{ $rol->id }}" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="button" class="btn btn-danger btn-sm"
                        onclick="confirmarEliminarRol('{{ $rol->id }}', this)">
                  <i class="bi bi-trash"></i> Eliminar
                </button>
              </form>
            @endcan
          </div>
        </div>

        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <strong>ID:</strong>
              <div>#{{ $rol->id }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Nombre:</strong>
              <div>{{ $rol->name }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Usuarios con este rol:</strong>
              <div>
                {{ $rol->users_count ?? 0 }}
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Creación:</strong>
              <div>{{ optional($rol->created_at)->format('Y-m-d H:i') }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Última actualización:</strong>
              <div>{{ optional($rol->updated_at)->format('Y-m-d H:i') ?? '—' }}</div>
            </div>

            <div class="col-md-12 mb-2">
              <strong>Permisos:</strong>
              <div>
                @php $perms = $rol->permissions ?? collect(); @endphp
                @forelse ($perms as $p)
                  <span class="badge badge-info">{{ $p->name }}</span>
                @empty
                  <span class="text-muted">Sin permisos</span>
                @endforelse
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmarEliminarRol(id, btn){
  const form = document.getElementById('formEliminarRol-' + id);
  if(!form){ console.error('No existe formEliminarRol-', id); return; }

  btn.disabled = true;

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

{{-- Flashes (por si llegas desde otra acción) --}}
@if (session('success'))
<script>
Swal.fire({ icon:'success', title:@json(session('success')), position:'center', timer:2500, showConfirmButton:false });
</script>
@endif
@if (session('error'))
<script>
Swal.fire({ icon:'error', title:'Ups', text:@json(session('error')), position:'center' });
</script>
@endif
@endsection
