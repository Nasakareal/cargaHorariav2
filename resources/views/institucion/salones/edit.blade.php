@extends('adminlte::page')

@section('title', 'Editar Salón')

@section('content_header')
  <h1 class="text-center w-100">
    Editar salón
    @if(!empty($salon->estado))
      <small class="ml-2">
        <span class="badge {{ strtoupper($salon->estado)==='ACTIVO' ? 'badge-success' : 'badge-danger' }}">
          {{ strtoupper($salon->estado) }}
        </span>
      </small>
    @endif
  </h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12 col-lg-8 mx-auto">

      @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
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
          <h3 class="card-title">Actualiza los datos del salón</h3>
          <a href="{{ route('institucion.salones.index') }}" class="btn btn-outline-success btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>

        <form method="POST" action="{{ route('institucion.salones.update', $salon->classroom_id) }}" autocomplete="off" id="formSalon">
          @csrf
          @method('PUT')

          <div class="card-body">

            {{-- Edificio (SELECT) --}}
            <div class="form-group">
              <label for="building">Edificio <span class="text-danger">*</span></label>
              <select name="building" id="building" class="form-control @error('building') is-invalid @enderror" required>
                <option value="">— Selecciona un edificio —</option>
                @foreach ($edificios as $b)
                  @php $val = is_object($b) ? ($b->building_name ?? $b->building ?? (string)$b) : (string)$b; @endphp
                  <option value="{{ $val }}"
                          {{ old('building', $salon->building) === $val ? 'selected' : '' }}>
                    {{ $val }}
                  </option>
                @endforeach
              </select>
              @error('building')
                <span class="invalid-feedback">{{ $message }}</span>
              @enderror
            </div>

            {{-- Planta --}}
            <div class="form-group">
              <label for="floor">Planta</label>
              <select name="floor" id="floor" class="form-control @error('floor') is-invalid @enderror">
                <option value="" {{ old('floor', $salon->floor) === null || old('floor', $salon->floor)==='' ? 'selected' : '' }}>—</option>
                @foreach($plantas as $p)
                  <option value="{{ $p }}" {{ old('floor', strtoupper((string)$salon->floor)) === $p ? 'selected' : '' }}>
                    {{ $p }}
                  </option>
                @endforeach
              </select>
              @error('floor')
                <span class="invalid-feedback">{{ $message }}</span>
              @enderror
            </div>

            {{-- Salón / Número --}}
            <div class="form-group">
              <label for="classroom_name">Salón <span class="text-danger">*</span></label>
              <input type="text" name="classroom_name" id="classroom_name"
                     class="form-control @error('classroom_name') is-invalid @enderror"
                     value="{{ old('classroom_name', $salon->classroom_name) }}" maxlength="50" required>
              @error('classroom_name')
                <span class="invalid-feedback">{{ $message }}</span>
              @else
                <small class="form-text text-muted">Ej.: 1, 12, M, A1…</small>
              @enderror
            </div>

            {{-- Capacidad --}}
            <div class="form-group">
              <label for="capacity">Capacidad</label>
              <input type="number" name="capacity" id="capacity" min="0" step="1"
                     class="form-control @error('capacity') is-invalid @enderror"
                     value="{{ old('capacity', (int)($salon->capacity ?? 0)) }}">
              @error('capacity')
                <span class="invalid-feedback">{{ $message }}</span>
              @enderror
            </div>

          </div>

          <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('institucion.salones.index') }}" class="btn btn-outline-secondary">
              Cancelar
            </a>
            @can('editar salones')
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
// Normaliza mayúsculas en planta
document.getElementById('floor')?.addEventListener('change', function(){
  this.value = (this.value || '').toUpperCase();
});
</script>
@endsection
