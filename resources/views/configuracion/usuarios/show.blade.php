{{-- resources/views/configuracion/usuarios/show.blade.php --}}
@extends('adminlte::page')

@section('title', 'Detalle de Usuario')

@section('content_header')
    <h1 class="text-center w-100">Detalle de usuario</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-info"><!-- usa info en lugar de primary/success -->
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Información del usuario</h3>

          <div class="btn-group">
            <a href="{{ route('configuracion.usuarios.index') }}" class="btn btn-secondary btn-sm">
              <i class="fas fa-arrow-left"></i> Volver
            </a>

            @can('editar usuarios')
            <a href="{{ route('configuracion.usuarios.edit', $usuario->id_usuario) }}" class="btn btn-success btn-sm">
              <i class="fas fa-edit"></i> Editar
            </a>
            @endcan

            @can('eliminar usuarios')
            <form action="{{ route('configuracion.usuarios.destroy', $usuario->id_usuario) }}"
                  method="POST" id="formEliminar-{{ $usuario->id_usuario }}" class="d-inline">
              @csrf
              @method('DELETE')
              <button type="button" class="btn btn-danger btn-sm"
                      onclick="confirmarEliminar('{{ $usuario->id_usuario }}', this)">
                <i class="fas fa-trash"></i> Eliminar
              </button>
            </form>
            @endcan
          </div>
        </div>

        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <strong>ID:</strong>
              <div>#{{ $usuario->id_usuario }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Nombres:</strong>
              <div>{{ $usuario->nombres }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Rol:</strong>
              <div>
                @php
                  $rolNombre = $usuario->rol_nombre ?? optional($usuario->roles->first())->name;
                @endphp
                {{ $rolNombre ?? 'Sin rol' }}
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Email:</strong>
              <div>{{ $usuario->email }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Áreas:</strong>
              <div>
                @php
                  $areas = $usuario->area ? array_filter(array_map('trim', explode(',', $usuario->area))) : [];
                @endphp
                @forelse ($areas as $a)
                  <span class="badge badge-info">{{ $a }}</span>
                @empty
                  <span class="text-muted">Sin áreas</span>
                @endforelse
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Estado:</strong>
              <div>
                @php $estado = strtoupper((string)$usuario->estado); @endphp
                <span class="badge {{ $estado === 'ACTIVO' ? 'badge-success' : 'badge-secondary' }}">
                  {{ $estado }}
                </span>
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Creación:</strong>
              <div>{{ optional($usuario->fyh_creacion)->format('Y-m-d H:i') }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Última actualización:</strong>
              <div>{{ optional($usuario->fyh_actualizacion)->format('Y-m-d H:i') ?? '—' }}</div>
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
    position: 'center'
  }).then((r)=>{
    if(r.isConfirmed){ form.submit(); }
    else { btn.disabled = false; }
  });
}
</script>

{{-- Flash en show (por si llegas aquí desde otra acción con mensaje) --}}
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
