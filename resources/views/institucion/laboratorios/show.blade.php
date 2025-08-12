@extends('adminlte::page')

@section('title', 'Detalle del Laboratorio')

@section('content_header')
  <h1 class="text-center w-100">Detalle del laboratorio</h1>
@endsection

@section('content')
<div class="container-xl">

  {{-- Tarjeta: info del laboratorio --}}
  <div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title">Información del laboratorio</h3>
      <div class="d-flex gap-2">
        @can('ver laboratorios')
          <a href="{{ route('institucion.laboratorios.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        @endcan
        @can('ver laboratorios')
          <a href="{{ route('institucion.laboratorios.horario', $lab->lab_id) }}" class="btn btn-warning btn-sm" title="Ver horario">
            <i class="bi bi-calendar2-week"></i> Horario
          </a>
        @endcan
      </div>
    </div>

    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Nombre</dt>
        <dd class="col-sm-9">{{ $lab->lab_name }}</dd>

        <dt class="col-sm-3">Descripción</dt>
        <dd class="col-sm-9">
          {{ $lab->description ? $lab->description : '—' }}
        </dd>

        <dt class="col-sm-3">Áreas</dt>
        <dd class="col-sm-9">
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

        <dt class="col-sm-3">Fecha creación</dt>
        <dd class="col-sm-9">{{ $lab->fyh_creacion ?? '—' }}</dd>

        <dt class="col-sm-3">Última actualización</dt>
        <dd class="col-sm-9">{{ $lab->fyh_actualizacion ?? '—' }}</dd>
      </dl>
    </div>
  </div>

  {{-- Tarjeta: grupos que usan el laboratorio --}}
  <div class="card card-outline card-primary mt-3">
    <div class="card-header">
      <h3 class="card-title">Grupos que usan este laboratorio</h3>
    </div>
    <div class="card-body">
      @if($grupos->isEmpty())
        <p class="text-muted mb-0">Este laboratorio no tiene grupos asignados.</p>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-hover">
            <thead>
              <tr>
                <th style="width:60px;">#</th>
                <th>Grupo</th>
                <th>Programa</th>
                <th>Cuatrimestre</th>
                <th>Turno</th>
              </tr>
            </thead>
            <tbody>
              @foreach($grupos as $i => $g)
                <tr>
                  <td>{{ $i+1 }}</td>
                  <td>{{ $g->group_name }}</td>
                  <td>{{ $g->program_name }}</td>
                  <td>{{ $g->term_name }}</td>
                  <td>{{ $g->shift_name }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

</div>
@endsection
