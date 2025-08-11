@extends('adminlte::page')

@section('title', 'Editar Rol')

@section('content_header')
    <h1 class="text-center w-100">Edición de rol</h1>
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

      <div class="card card-outline card-success">
        <div class="card-header d-flex justify-content-between items-center">
          <h3 class="card-title">Actualiza los datos del rol</h3>
        </div>

        <div class="card-body">
          <form action="{{ route('configuracion.roles.update', $rol->id) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="row">
              {{-- Nombre del rol --}}
              <div class="col-md-6">
                <div class="form-group">
                  <label for="name">Nombre del rol</label>
                  <input type="text"
                         name="name"
                         id="name"
                         class="form-control"
                         value="{{ old('name', $rol->name) }}"
                         required>
                </div>
              </div>
            </div>

            <hr>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-success">
                    Guardar cambios
                  </button>
                  <a href="{{ route('configuracion.roles.index') }}" class="btn btn-secondary">
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

{{-- SweetAlert para errores de validación en esta pantalla --}}
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
