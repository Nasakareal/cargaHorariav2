@extends('adminlte::page')

@section('title', 'Editar Edificio')

@section('content_header')
  <h1 class="text-center w-100">
    Edición de edificio
    <small class="ml-2 text-muted">{{ $building }}</small>
  </h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12 col-lg-10 mx-auto">

      @if(session('error'))
        <div class="alert alert-danger">{!! session('error') !!}</div>
      @endif
      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card card-outline card-success">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Actualiza los datos</h3>
          <a href="{{ route('institucion.edificios.index') }}" class="btn btn-outline-success btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>

        <form action="{{ route('institucion.edificios.update', $rowId) }}" method="POST" autocomplete="off" id="formEdificio">
          @csrf
          @method('PUT')

          <div class="card-body">
            <div class="row">
              {{-- Nombre del edificio --}}
              <div class="col-md-6">
                <div class="form-group">
                  <label for="building_name">Nombre del Edificio <span class="text-danger">*</span></label>
                  <input type="text"
                         name="building_name"
                         id="building_name"
                         class="form-control @error('building_name') is-invalid @enderror"
                         value="{{ old('building_name', $building) }}"
                         required>
                  @error('building_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                  <small class="form-text text-muted">Ej.: EDIFICIO-A, EDIFICIO-B, EDIFICIO-P1…</small>
                </div>
              </div>

              {{-- Planta Alta --}}
              <div class="col-md-3">
                <div class="form-group">
                  <label for="planta_alta">Planta Alta</label>
                  @php $altaSel = (string)old('planta_alta', (int)$planta_alta); @endphp
                  <select name="planta_alta" id="planta_alta" class="form-control @error('planta_alta') is-invalid @enderror">
                    <option value="1" {{ $altaSel === '1' ? 'selected' : '' }}>Sí</option>
                    <option value="0" {{ $altaSel === '0' ? 'selected' : '' }}>No</option>
                  </select>
                  @error('planta_alta') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
              </div>

              {{-- Planta Baja --}}
              <div class="col-md-3">
                <div class="form-group">
                  <label for="planta_baja">Planta Baja</label>
                  @php $bajaSel = (string)old('planta_baja', (int)$planta_baja); @endphp
                  <select name="planta_baja" id="planta_baja" class="form-control @error('planta_baja') is-invalid @enderror">
                    <option value="1" {{ $bajaSel === '1' ? 'selected' : '' }}>Sí</option>
                    <option value="0" {{ $bajaSel === '0' ? 'selected' : '' }}>No</option>
                  </select>
                  @error('planta_baja') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
              </div>
            </div>

            {{-- Áreas --}}
            <div class="form-group">
              <label>Áreas <span class="text-danger">*</span></label>
              @php
                $seleccion = collect(old('areas', $areasSeleccionadas ?? []))->map(fn($v)=>(string)$v)->all();
              @endphp
              <div class="form-control" style="height:auto; padding:10px;">
                @forelse($areas as $a)
                  @php $val = is_object($a) ? (string)($a->area ?? '') : (string)$a; @endphp
                  @if($val !== '')
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="areas[]"
                             id="area_{{ md5($val) }}"
                             value="{{ $val }}"
                             {{ in_array($val, $seleccion, true) ? 'checked' : '' }}>
                      <label class="form-check-label" for="area_{{ md5($val) }}">{{ $val }}</label>
                    </div>
                  @endif
                @empty
                  <span class="text-muted">No hay áreas disponibles.</span>
                @endforelse
              </div>
              @error('areas') <div class="text-danger mt-1 small">{{ $message }}</div> @enderror
            </div>

            @if($building !== old('building_name', $building))
              <div class="alert alert-warning mt-3">
                Al renombrar el edificio, se actualizarán también los salones ligados a este edificio.
              </div>
            @endif
          </div>

          <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('institucion.edificios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            @can('editar edificios')
              <button type="submit" class="btn btn-success">
                <i class="bi bi-save"></i> Guardar cambios
              </button>
            @endcan
          </div>
        </form>
      </div>

    </div>
  </div>
</div>
@endsection

@section('js')
<script>
// Mayúsculas automáticas para el nombre del edificio
document.getElementById('building_name')?.addEventListener('blur', function(){
  this.value = (this.value || '').toUpperCase().trim();
});
</script>
@endsection
