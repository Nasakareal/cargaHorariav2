@extends('adminlte::page')

@section('title', 'Detalle de Materia')

@section('content_header')
  <h1 class="text-center w-100">Materia: {{ $materia->subject_name }}</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-info">
        <div class="card-header">
          <h3 class="card-title">Datos de la Materia</h3>
          <div class="card-tools">
            @can('editar materias')
              <a href="{{ route('materias.edit', $materia->subject_id) }}" class="btn btn-success btn-sm">
                <i class="bi bi-pencil"></i> Editar
              </a>
            @endcan
            <a href="{{ route('materias.index') }}" class="btn btn-secondary btn-sm">
              Volver
            </a>
          </div>
        </div>

        <div class="card-body">
          <div class="row">
            {{-- Nombre --}}
            <div class="col-md-4">
              <div class="form-group">
                <label>Nombre de la materia</label>
                <p class="mb-0">{{ $materia->subject_name }}</p>
              </div>
            </div>

            {{-- Horas consecutivas --}}
            <div class="col-md-4">
              <div class="form-group">
                <label>Horas consecutivas (máximo)</label>
                <p class="mb-0">{{ $materia->max_consecutive_class_hours ?? '—' }}</p>
              </div>
            </div>

            {{-- Horas semanales --}}
            <div class="col-md-4">
              <div class="form-group">
                <label>Horas semanales</label>
                <p class="mb-0">{{ $materia->weekly_hours ?? 0 }}</p>
              </div>
            </div>

            {{-- Unidades --}}
            <div class="col-md-4">
              <div class="form-group">
                <label>Unidades</label>
                <p class="mb-0">{{ $materia->unidades ?? '—' }}</p>
              </div>
            </div>

            {{-- Estado --}}
            <div class="col-md-4">
              <div class="form-group">
                <label>Estado</label>
                <p class="mb-0">
                  @php $est = $materia->estado ?? 'ACTIVO'; @endphp
                  <span class="badge {{ $est === 'ACTIVO' ? 'badge-success' : 'badge-secondary' }}">
                    {{ $est }}
                  </span>
                </p>
              </div>
            </div>

            {{-- Programa/Cuatrimestre principal (si la tabla subjects los trae) --}}
            <div class="col-md-4">
              <div class="form-group">
                <label>Programa (principal)</label>
                <p class="mb-0">{{ $materia->program_name ?? 'No asignado' }}</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Cuatrimestre (principal)</label>
                <p class="mb-0">{{ $materia->term_name ?? 'No asignado' }}</p>
              </div>
            </div>

            {{-- Relaciones N–N (programa–cuatrimestre) --}}
            <div class="col-md-12">
              <div class="form-group">
                <label>Relaciones Programa–Cuatrimestre</label>

                @php
                  // El controller puede pasar $rel como collection de filas { program_name, term_name }
                  // o strings agregados. Cubrimos ambos.
                  $listaRel = collect($rel ?? []);
                @endphp

                @if ($listaRel->count() > 0)
                  <ul class="mb-0">
                    @foreach ($listaRel as $r)
                      <li>
                        {{ $r->program_name ?? $r['program_name'] ?? '—' }}
                        —
                        {{ $r->term_name ?? $r['term_name'] ?? '—' }}
                      </li>
                    @endforeach
                  </ul>
                @else
                  <p class="mb-0 text-muted">Sin relaciones adicionales.</p>
                @endif
              </div>
            </div>

            {{-- Timestamps opcionales --}}
            <div class="col-md-6">
              <div class="form-group">
                <label>Creada</label>
                <p class="mb-0">{{ optional($materia->fyh_creacion)->format('Y-m-d H:i:s') ?? ($materia->fyh_creacion ?? '—') }}</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Actualizada</label>
                <p class="mb-0">{{ optional($materia->fyh_actualizacion)->format('Y-m-d H:i:s') ?? ($materia->fyh_actualizacion ?? '—') }}</p>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection
