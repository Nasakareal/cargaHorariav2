@extends('adminlte::page')

@section('title', 'Editar Grupo')

@section('content_header')
  <h1 class="text-center w-100">Edición de grupo</h1>
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
        <div class="card-header">
          <h3 class="card-title">Actualiza los datos</h3>
        </div>

        <div class="card-body">
          <form id="editForm" action="{{ route('grupos.update', $grupo->group_id) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="row">
              {{-- Nombre del grupo --}}
              <div class="col-md-6">
                <div class="form-group">
                  <label for="group_name">Nombre del grupo</label>
                  <input type="text"
                         name="group_name"
                         id="group_name"
                         class="form-control"
                         value="{{ old('group_name', $grupo->group_name) }}"
                         required>
                </div>
              </div>

              {{-- Programa educativo --}}
              <div class="col-md-6">
                <div class="form-group">
                  <label for="program_id">Programa educativo</label>
                  <select name="program_id" id="program_id" class="form-control" required>
                    <option value="">— Selecciona un programa —</option>
                    @foreach ($programas as $p)
                      <option value="{{ $p->program_id }}"
                        {{ (string)old('program_id', $grupo->program_id) === (string)$p->program_id ? 'selected' : '' }}>
                        {{ $p->program_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              {{-- Cuatrimestre (terms) --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="term_id">Cuatrimestre</label>
                  <select name="term_id" id="term_id" class="form-control" required>
                    <option value="">— Selecciona cuatrimestre —</option>
                    @foreach ($terminos as $t)
                      <option value="{{ $t->term_id }}"
                        {{ (string)old('term_id', $grupo->term_id) === (string)$t->term_id ? 'selected' : '' }}>
                        {{ $t->term_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>

              {{-- Volumen del grupo --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="volume">Volumen del grupo</label>
                  <input type="number"
                         name="volume"
                         id="volume"
                         class="form-control"
                         value="{{ old('volume', $grupo->volume) }}"
                         min="0" step="1" required>
                </div>
              </div>

              {{-- Turno (shifts) -> se guarda en groups.turn_id --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="turn_id">Turno</label>
                  <select name="turn_id" id="turn_id" class="form-control" required>
                    <option value="">— Selecciona turno —</option>
                    @foreach ($turnos as $sh)
                      <option value="{{ $sh->shift_id }}"
                        {{ (string)old('turn_id', $grupo->turn_id) === (string)$sh->shift_id ? 'selected' : '' }}>
                        {{ $sh->shift_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            <hr>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-success">Guardar cambios</button>
                  <a href="{{ route('grupos.index') }}" class="btn btn-secondary">Cancelar</a>
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
