@extends('adminlte::page')

@section('title', 'Crear Profesor')

@section('content_header')
  <h1 class="text-center w-100">Creación de un nuevo profesor</h1>
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
          <form action="{{ route('profesores.store') }}" method="POST" autocomplete="off">
            @csrf

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="teacher_name">Nombre del profesor</label>
                  <input type="text"
                         name="teacher_name"
                         id="teacher_name"
                         class="form-control"
                         value="{{ old('teacher_name') }}"
                         required>
                </div>
              </div>
            </div>

            <hr>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-primary">Registrar</button>
                  <a href="{{ route('profesores.index') }}" class="btn btn-secondary">Cancelar</a>
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
