@extends('adminlte::page')

@section('title', 'Editar Materia')

@section('content_header')
  <h1 class="text-center w-100">Modificar materia: {{ $materia->subject_name }}</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      {{-- Errores en línea (además lanzamos SweetAlert abajo) --}}
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
        <div class="card-header">
          <h3 class="card-title">Llene los datos</h3>
        </div>

        <div class="card-body">
          <form action="{{ route('materias.update', $materia->subject_id) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')

            {{-- Nombre, Horas consecutivas, Programa --}}
            <div class="row">
              {{-- Nombre --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="subject_name">Nombre de la materia</label>
                  <input type="text"
                         name="subject_name"
                         id="subject_name"
                         class="form-control"
                         value="{{ old('subject_name', $materia->subject_name) }}"
                         required>
                </div>
              </div>

              {{-- Horas consecutivas --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="max_consecutive_class_hours">Horas consecutivas (máximo)</label>
                  <input type="number"
                         name="max_consecutive_class_hours"
                         id="max_consecutive_class_hours"
                         class="form-control"
                         min="1"
                         step="1"
                         value="{{ old('max_consecutive_class_hours', $materia->max_consecutive_class_hours) }}"
                         required>
                </div>
              </div>

              {{-- Programa --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="program_id">Programa</label>
                  <select name="program_id" id="program_id" class="form-control" required>
                    <option value="" disabled>— Seleccione un programa —</option>
                    @foreach ($programas as $p)
                      <option value="{{ $p->program_id }}"
                        {{ (string)old('program_id', $materia->program_id) === (string)$p->program_id ? 'selected' : '' }}>
                        {{ $p->program_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            {{-- Cuatrimestre, Horas semanales, Unidades --}}
            <div class="row">
              {{-- Cuatrimestre --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="term_id">Cuatrimestre</label>
                  <select name="term_id" id="term_id" class="form-control" required>
                    <option value="" disabled>— Seleccione un cuatrimestre —</option>
                    @foreach ($terms as $t)
                      <option value="{{ $t->term_id }}"
                        {{ (string)old('term_id', $materia->term_id) === (string)$t->term_id ? 'selected' : '' }}>
                        {{ $t->term_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>

              {{-- Horas semanales --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="weekly_hours">Horas semanales</label>
                  <input type="number"
                         name="weekly_hours"
                         id="weekly_hours"
                         class="form-control"
                         min="1"
                         step="1"
                         value="{{ old('weekly_hours', $materia->weekly_hours) }}"
                         required>
                </div>
              </div>

              {{-- Unidades --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="unidades">Unidades</label>
                  <input type="number"
                         name="unidades"
                         id="unidades"
                         class="form-control"
                         min="1"
                         step="1"
                         value="{{ old('unidades', $materia->unidades) }}"
                         required>
                </div>
              </div>
            </div>

            {{-- Estado (opcional; si lo manejas) --}}
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="estado">Estado</label>
                  <select name="estado" id="estado" class="form-control">
                    @php $est = old('estado', $materia->estado ?? 'ACTIVO'); @endphp
                    <option value="ACTIVO"   {{ $est === 'ACTIVO'   ? 'selected' : '' }}>ACTIVO</option>
                    <option value="INACTIVO" {{ $est === 'INACTIVO' ? 'selected' : '' }}>INACTIVO</option>
                  </select>
                </div>
              </div>
            </div>

            <hr>

            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-primary">Actualizar</button>
                  <a href="{{ route('materias.index') }}" class="btn btn-secondary">Cancelar</a>
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
