@extends('adminlte::page')

@section('title', 'Crear Usuario')

@section('content_header')
    <h1 class="text-center w-100">Creación de un nuevo usuario</h1>
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

      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Llene los datos</h3>
        </div>

        <div class="card-body">
          <form action="{{ route('configuracion.usuarios.store') }}" method="POST" autocomplete="off">
            @csrf

            <div class="row">
              {{-- Rol (Spatie) --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="rol_id">Nombre del rol</label>
                  <select name="rol_id" id="rol_id" class="form-control" required>
                    <option value="" disabled selected>Seleccione un rol...</option>
                    @foreach ($roles as $rol)
                      <option value="{{ $rol->id }}" {{ old('rol_id') == $rol->id ? 'selected' : '' }}>
                        {{ $rol->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>

              {{-- Nombres --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="nombres">Nombres del usuario</label>
                  <input type="text" name="nombres" id="nombres" class="form-control" value="{{ old('nombres') }}" required>
                </div>
              </div>

              {{-- Email --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="email">Email</label>
                  <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
                </div>
              </div>
            </div>

            <div class="row">
              {{-- Password --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="password">Password</label>
                  <input type="password" name="password" id="password" class="form-control" required>
                </div>
              </div>

              {{-- Password confirmation --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="password_confirmation">Repetir Password</label>
                  <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                </div>
              </div>

                {{-- Áreas (múltiple con checkboxes) --}}
                <div class="col-md-12">
                  <div class="form-group">
                    <label>Áreas (puedes seleccionar varias)</label>
                    <div class="d-flex flex-wrap" style="gap: .75rem">
                      @php
                        $oldAreas = old('areas', []);
                      @endphp
                      @foreach ($areas as $a)
                        <div class="form-check">
                          <input
                            class="form-check-input"
                            type="checkbox"
                            name="areas[]"
                            id="area_{{ $a }}"
                            value="{{ $a }}"
                            {{ in_array($a, $oldAreas) ? 'checked' : '' }}
                          >
                          <label class="form-check-label" for="area_{{ $a }}">
                            {{ $a }}
                          </label>
                        </div>
                      @endforeach
                    </div>
                    @error('areas') <small class="text-danger">{{ $message }}</small> @enderror
                  </div>
                </div>
            </div>

            <hr>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-primary">
                    Registrar
                  </button>
                  <a href="{{ route('configuracion.usuarios.index') }}" class="btn btn-secondary">
                    Cancelar
                  </a>
                </div>
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
@endsection
