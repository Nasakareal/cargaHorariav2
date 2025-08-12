@extends('adminlte::page')

@section('title', 'Horario del laboratorio')

@section('content_header')
  <h1 class="text-center w-100">
    Horario del laboratorio: {{ $lab->lab_name }}
  </h1>
@endsection

@section('content')
<div class="container-xl">

  {{-- Info del laboratorio --}}
  <div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title">Información</h3>
      <div>
        <a href="{{ route('institucion.laboratorios.index') }}" class="btn btn-secondary btn-sm">
          <i class="fas fa-arrow-left"></i> Volver
        </a>
        <a href="{{ route('institucion.laboratorios.show', $lab->lab_id) }}" class="btn btn-info btn-sm">
          <i class="bi bi-eye"></i> Detalle
        </a>
      </div>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-2">Laboratorio</dt>
        <dd class="col-sm-10">{{ $lab->lab_name }}</dd>

        <dt class="col-sm-2">Áreas</dt>
        <dd class="col-sm-10">
          @php
            $areas = collect(explode(',', (string)($lab->area ?? '')))
                      ->map(fn($a)=>trim($a))
                      ->filter()
                      ->values()
                      ->all();
          @endphp
          @if(count($areas))
            @foreach($areas as $a)
              <span class="badge badge-secondary mr-1">{{ $a }}</span>
            @endforeach
          @else
            <span class="text-muted">—</span>
          @endif
        </dd>

        <dt class="col-sm-2">Descripción</dt>
        <dd class="col-sm-10">{{ $lab->description ?: '—' }}</dd>

        <dt class="col-sm-2">Fecha creación</dt>
        <dd class="col-sm-4">{{ $lab->fyh_creacion ?? '—' }}</dd>

        <dt class="col-sm-2">Última actualización</dt>
        <dd class="col-sm-4">{{ $lab->fyh_actualizacion ?? '—' }}</dd>
      </dl>
    </div>
  </div>

  {{-- Horario semanal --}}
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
