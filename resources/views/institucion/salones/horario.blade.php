@extends('adminlte::page')

@section('title', 'Horario del salón')

@section('content_header')
  <h1 class="text-center w-100">
    Horario del salón: {{ $salon->classroom_name }}
    <small class="text-muted">({{ $salon->building }}, {{ $salon->floor ?: '—' }})</small>
  </h1>
@endsection

@section('content')
<div class="container-xl">

  <div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title">Información</h3>
      <div>
        <a href="{{ route('institucion.salones.index') }}" class="btn btn-secondary btn-sm">
          <i class="fas fa-arrow-left"></i> Volver
        </a>
        <a href="{{ route('institucion.salones.show', $salon->classroom_id) }}" class="btn btn-info btn-sm">
          <i class="bi bi-eye"></i> Detalle
        </a>
      </div>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-2">Salón</dt>
        <dd class="col-sm-4">{{ $salon->classroom_name }}</dd>

        <dt class="col-sm-2">Edificio</dt>
        <dd class="col-sm-4">{{ $salon->building }}</dd>

        <dt class="col-sm-2">Planta</dt>
        <dd class="col-sm-4">{{ $salon->floor ?: '—' }}</dd>

        <dt class="col-sm-2">Capacidad</dt>
        <dd class="col-sm-4">{{ (int)$salon->capacity }}</dd>

        <dt class="col-sm-2">Estado</dt>
        <dd class="col-sm-4 {{ strtoupper($salon->estado)==='ACTIVO' ? 'text-success' : 'text-danger font-weight-bold' }}">
          {{ $salon->estado ?: '—' }}
        </dd>
      </dl>
    </div>
  </div>

  <div class="card card-outline card-primary mt-3">
    <div class="card-header">
      <h3 class="card-title">Horario semanal</h3>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0">
          <thead class="thead-light">
            <tr>
              <th style="width: 120px;">Hora</th>
              @foreach($dias as $d)
                <th class="text-center">{{ $d }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @forelse($horas as $h)
              <tr>
                <th class="align-middle">{{ $h }}</th>
                @foreach($dias as $d)
                  <td style="min-width: 220px;">
                    {!! $tabla[$h][$d] ?: '<span class="text-muted">—</span>' !!}
                  </td>
                @endforeach
              </tr>
            @empty
              <tr>
                <td colspan="{{ 1 + count($dias) }}" class="text-center text-muted p-4">
                  No hay horario registrado.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
