@extends('adminlte::page')

@section('title', 'Editar Laboratorio')

@section('content_header')
  <h1 class="text-center w-100">Editar laboratorio</h1>
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
          <h3 class="card-title">Actualiza los datos del laboratorio</h3>
          <a href="{{ route('institucion.laboratorios.index') }}" class="btn btn-outline-success btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>

        <form method="POST" action="{{ route('institucion.laboratorios.update', $lab->lab_id) }}" autocomplete="off" id="formLab">
          @csrf
          @method('PUT')

          <div class="card-body">

            {{-- Nombre --}}
            <div class="form-group">
              <label for="lab_name">Nombre <span class="text-danger">*</span></label>
              <input type="text"
                     name="lab_name"
                     id="lab_name"
                     class="form-control @error('lab_name') is-invalid @enderror"
                     value="{{ old('lab_name', $lab->lab_name) }}"
                     maxlength="150"
                     required>
              @error('lab_name')
                <span class="invalid-feedback">{{ $message }}</span>
              @enderror
            </div>

            {{-- Descripción --}}
            <div class="form-group">
              <label for="description">Descripción</label>
              <textarea name="description"
                        id="description"
                        rows="4"
                        class="form-control @error('description') is-invalid @enderror"
                        maxlength="1000"
                        placeholder="Descripción breve del laboratorio...">{{ old('description', $lab->description) }}</textarea>
              @error('description')
                <span class="invalid-feedback">{{ $message }}</span>
              @enderror
              <small class="form-text text-muted">Opcional. Máx. 1000 caracteres.</small>
            </div>

            {{-- Áreas (checkboxes) --}}
            <div class="form-group">
              <label>Áreas <span class="text-danger">*</span></label>
              <div class="border rounded p-2" style="max-height: 260px; overflow:auto;">
                @php
                  // Si el controller no pasó $areasSeleccionadas, lo derivamos del CSV $lab->area
                  $pre = isset($areasSeleccionadas) ? $areasSeleccionadas : (explode(',', (string)($lab->area ?? '')));
                  $pre = collect($pre)->map(fn($a)=>trim($a))->filter()->values()->all();
                  $oldAreas = collect(old('areas', $pre))->map(fn($a)=>trim($a))->filter()->values()->all();
                @endphp

                @forelse($areas as $a)
                  @php $checked = in_array($a, $oldAreas, true); @endphp
                  <div class="form-check">
                    <input class="form-check-input"
                           type="checkbox"
                           id="area_{{ md5($a) }}"
                           name="areas[]"
                           value="{{ $a }}"
                           {{ $checked ? 'checked' : '' }}>
                    <label class="form-check-label" for="area_{{ md5($a) }}">
                      {{ $a }}
                    </label>
                  </div>
                @empty
                  <span class="text-muted">No hay áreas disponibles.</span>
                @endforelse
              </div>
              @error('areas')
                <span class="text-danger d-block mt-1">{{ $message }}</span>
              @enderror
              @error('areas.*')
                <span class="text-danger d-block mt-1">{{ $message }}</span>
              @enderror
              <small class="form-text text-muted">Selecciona una o varias áreas a las que pertenece el laboratorio.</small>
            </div>

            {{-- Metadatos opcionales --}}
            <div class="row mt-3">
              <div class="col-sm-6">
                <small class="text-muted">Creado: {{ $lab->fyh_creacion ?? '—' }}</small>
              </div>
              <div class="col-sm-6 text-sm-right">
                <small class="text-muted">Actualizado: {{ $lab->fyh_actualizacion ?? '—' }}</small>
              </div>
            </div>

          </div>

          <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('institucion.laboratorios.index') }}" class="btn btn-outline-secondary">
              Cancelar
            </a>
            @can('editar laboratorios')
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
