{{-- resources/views/configuracion/roles/permissions.blade.php --}}
@extends('adminlte::page')

@section('title', 'Permisos del Rol')

@section('content_header')
  <h1 class="text-center w-100">Permisos del rol</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card card-outline card-warning">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">
            Gestionar permisos para: <strong>{{ $rol->name }}</strong>
          </h3>

          <div class="btn-group">
            <a href="{{ route('configuracion.roles.index') }}" class="btn btn-secondary btn-sm">
              <i class="bi bi-arrow-left"></i> Volver
            </a>
          </div>
        </div>

        <div class="card-body">
          <form action="{{ route('configuracion.roles.assign-permissions', $rol->id) }}" method="POST" autocomplete="off" id="formPermisos">
            @csrf

            {{-- Controles rápidos --}}
            <div class="row mb-3">
              <div class="col-md-6 d-flex align-items-center" style="gap:.5rem">
                <button type="button" class="btn btn-outline-warning btn-sm" id="btnSelectAll">
                  <i class="bi bi-check2-square"></i> Seleccionar todos
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearAll">
                  <i class="bi bi-x-square"></i> Quitar todos
                </button>
              </div>
              <div class="col-md-6">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                  </div>
                  <input type="text" id="filtroPermisos" class="form-control" placeholder="Filtrar permisos...">
                </div>
              </div>
            </div>

            {{-- Lista de permisos --}}
            <div class="d-flex flex-wrap" style="gap:.75rem" id="listaPermisos">
              @php
                $seleccionados = old('permisos', $permisosSeleccionados ?? []);
              @endphp

              @forelse ($permisos as $perm)
                <div class="form-check permiso-chip" data-nombre="{{ Str::lower($perm->name) }}">
                  <input
                    class="form-check-input permiso-item"
                    type="checkbox"
                    name="permisos[]"
                    id="perm_{{ $perm->id }}"
                    value="{{ $perm->id }}"
                    {{ in_array($perm->id, $seleccionados) ? 'checked' : '' }}
                  >
                  <label class="form-check-label badge badge-warning text-dark" for="perm_{{ $perm->id }}" style="font-weight:500;">
                    {{ $perm->name }}
                  </label>
                </div>
              @empty
                <span class="text-muted">No hay permisos disponibles.</span>
              @endforelse
            </div>

            @error('permisos') <small class="text-danger d-block mt-2">{{ $message }}</small> @enderror

            <hr>
            <div class="d-flex justify-content-between">
              <small class="text-muted">Total permisos: {{ $permisos->count() }}</small>
              <div class="form-group mb-0">
                <button type="submit" class="btn btn-warning">
                  <i class="bi bi-save"></i> Guardar permisos
                </button>
                <a href="{{ route('configuracion.roles.index') }}" class="btn btn-secondary">
                  Cancelar
                </a>
              </div>
            </div>

          </form>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- SweetAlert para errores/éxitos si llegas con flash --}}
@if (session('success'))
<script>
Swal.fire({
  icon:'success',
  title:@json(session('success')),
  position:'center',
  timer:2500,
  showConfirmButton:false
});
</script>
@endif

@if (session('error'))
<script>
Swal.fire({
  icon:'error',
  title:'Ups',
  text:@json(session('error')),
  position:'center'
});
</script>
@endif

@if ($errors->any())
<script>
Swal.fire({
  icon:'warning',
  title:'Revisa los datos',
  html:`{!! implode('<br>', $errors->all()) !!}`,
  position:'center'
});
</script>
@endif

<script>
// Seleccionar / Quitar todos
document.getElementById('btnSelectAll')?.addEventListener('click', () => {
  document.querySelectorAll('.permiso-item').forEach(cb => cb.checked = true);
});
document.getElementById('btnClearAll')?.addEventListener('click', () => {
  document.querySelectorAll('.permiso-item').forEach(cb => cb.checked = false);
});

// Filtro por texto
document.getElementById('filtroPermisos')?.addEventListener('input', (e) => {
  const q = (e.target.value || '').toLowerCase().trim();
  document.querySelectorAll('.permiso-chip').forEach(chip => {
    const name = chip.getAttribute('data-nombre') || '';
    chip.style.display = name.includes(q) ? '' : 'none';
  });
});
</script>
@endsection
