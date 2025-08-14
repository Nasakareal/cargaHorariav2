@extends('adminlte::page')

@section('title', 'Renombrar paquete')

@section('content_header')
  <h1 class="text-center w-100">Renombrar paquete — {{ $fecha }}</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-success">
        <div class="card-header">
          <h3 class="card-title">Cuatrimestre</h3>
        </div>
        <div class="card-body">
          <form action="{{ route('configuracion.horarios-pasados.update', $fecha) }}" method="POST">
            @csrf @method('PUT')

            <div class="mb-3">
              <label class="form-label">Nombre del cuatrimestre (en inglés)</label>
              <input type="text" name="quarter_name_en" class="form-control" required
                     value="{{ old('quarter_name_en', $quarterName) }}">
              @error('quarter_name_en') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-success">Guardar</button>
              <a href="{{ route('configuracion.horarios-pasados.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
