@extends('adminlte::page')

@section('title', 'Crear Edificio')

@section('content_header')
  <h1 class="text-center w-100">Creación de un nuevo Edificio</h1>
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
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
          </ul>
        </div>
      @endif

      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Llene los datos</h3>
          <a href="{{ route('institucion.edificios.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>

        <form action="{{ route('institucion.edificios.store') }}" method="POST" autocomplete="off">
          @csrf
          <div class="card-body">
            <div class="row">
              {{-- Nombre del Edificio --}}
              <div class="col-md-6">
                <div class="form-group">
                  <label>Nombre del Edificio</label>
                  <input type="text" name="building_name" class="form-control @error('building_name') is-invalid @enderror"
                         value="{{ old('building_name') }}" required>
                  @error('building_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
              </div>

              {{-- Planta Alta --}}
              <div class="col-md-3">
                <div class="form-group">
                  <label>Planta Alta</label>
                  <select name="planta_alta" class="form-control @error('planta_alta') is-invalid @enderror" required>
                    <option value="1" {{ old('planta_alta','1')=='1' ? 'selected' : '' }}>Sí</option>
                    <option value="0" {{ old('planta_alta')=='0' ? 'selected' : '' }}>No</option>
                  </select>
                  @error('planta_alta') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
              </div>

              {{-- Planta Baja --}}
              <div class="col-md-3">
                <div class="form-group">
                  <label>Planta Baja</label>
                  <select name="planta_baja" class="form-control @error('planta_baja') is-invalid @enderror" required>
                    <option value="1" {{ old('planta_baja','1')=='1' ? 'selected' : '' }}>Sí</option>
                    <option value="0" {{ old('planta_baja')=='0' ? 'selected' : '' }}>No</option>
                  </select>
                  @error('planta_baja') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
              </div>
            </div>

            {{-- Áreas --}}
            <div class="form-group">
              <label>Áreas</label>
              <div class="form-control" style="height:auto; padding:10px;">
                @forelse($areas as $a)
                  @php $id = 'area_'.preg_replace('/[^A-Za-z0-9_-]+/','',$a); @endphp
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="areas[]"
                           value="{{ $a }}" id="{{ $id }}"
                           {{ in_array($a, old('areas', [])) ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ $id }}">{{ $a }}</label>
                  </div>
                @empty
                  <span class="text-muted">No hay áreas registradas.</span>
                @endforelse
              </div>
              @error('areas') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

          </div>

          <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('institucion.edificios.index') }}" class="btn btn-secondary">Cancelar</a>
            @can('crear edificios')
              <button type="submit" class="btn btn-primary">Registrar</button>
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
// Normaliza planta a mayúsculas si el usuario escribe
document.getElementById('floor')?.addEventListener('change', function(){
  this.value = (this.value || '').toUpperCase();
});
</script>
@endsection
