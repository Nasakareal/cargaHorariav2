@extends('adminlte::page')

@section('title', 'Crear Materia')

@section('content_header')
  <h1 class="text-center w-100">Creación de una nueva materia</h1>
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

      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Llene los datos</h3>
        </div>

        <div class="card-body">
          <form action="{{ route('materias.store') }}" method="POST" autocomplete="off">
            @csrf

            @php
              $listaProgramas      = $programas ?? $programs ?? collect();
              $listaCuatrimestres  = $cuatrimestres ?? $terms ?? collect();
            @endphp

            {{-- Nombre, Horas consecutivas, Programa --}}
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="subject_name">Nombre de la materia</label>
                  <input type="text"
                         name="subject_name"
                         id="subject_name"
                         class="form-control"
                         value="{{ old('subject_name') }}"
                         required>
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label for="max_consecutive_class_hours">Horas consecutivas</label>
                  <input type="number"
                         name="max_consecutive_class_hours"
                         id="max_consecutive_class_hours"
                         class="form-control"
                         min="1"
                         step="1"
                         value="{{ old('max_consecutive_class_hours') }}"
                         required>
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label for="program_id">Programa</label>
                  <select name="program_id" id="program_id" class="form-control" required>
                    <option value="" disabled {{ old('program_id') ? '' : 'selected' }}>— Seleccione un programa —</option>
                    @foreach ($listaProgramas as $p)
                      @php
                        // Admite tanto arrays como objetos
                        $pid   = is_array($p) ? ($p['program_id'] ?? $p['id'] ?? null) : ($p->program_id ?? $p->id ?? null);
                        $pname = is_array($p) ? ($p['program_name'] ?? $p['name'] ?? '') : ($p->program_name ?? $p->name ?? '');
                      @endphp
                      <option value="{{ $pid }}" {{ (string)old('program_id') === (string)$pid ? 'selected' : '' }}>
                        {{ $pname }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            {{-- Cuatrimestre, Horas semanales, Unidades --}}
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="term_id">Cuatrimestre</label>
                  <select name="term_id" id="term_id" class="form-control" required>
                    <option value="" disabled {{ old('term_id') ? '' : 'selected' }}>— Seleccione un cuatrimestre —</option>
                    @foreach ($listaCuatrimestres as $t)
                      @php
                        $tid   = is_array($t) ? ($t['term_id'] ?? $t['id'] ?? null) : ($t->term_id ?? $t->id ?? null);
                        $tname = is_array($t) ? ($t['term_name'] ?? $t['name'] ?? '') : ($t->term_name ?? $t->name ?? '');
                      @endphp
                      <option value="{{ $tid }}" {{ (string)old('term_id') === (string)$tid ? 'selected' : '' }}>
                        {{ $tname }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label for="weekly_hours">Horas semanales</label>
                  <input type="number"
                         name="weekly_hours"
                         id="weekly_hours"
                         class="form-control"
                         min="1"
                         step="1"
                         value="{{ old('weekly_hours') }}"
                         required>
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label for="unidades">Unidades</label>
                  <input type="number"
                         name="unidades"
                         id="unidades"
                         class="form-control"
                         min="1"
                         step="1"
                         value="{{ old('unidades') }}"
                         required>
                </div>
              </div>
            </div>

            <hr>

            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-primary">Registrar</button>
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
