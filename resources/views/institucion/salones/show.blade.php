@extends('adminlte::page')

@section('title', 'Detalle del Salón')

@section('content_header')
    <h1 class="text-center w-100">Detalle del salón</h1>
@endsection

@section('content')
<div class="container-xl">

  <div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title">Información del salón</h3>
      <a href="{{ route('institucion.salones.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
    <div class="card-body">
      <dl class="row">
        <dt class="col-sm-3">Nombre</dt>
        <dd class="col-sm-9">{{ $salon->classroom_name }}</dd>

        <dt class="col-sm-3">Edificio</dt>
        <dd class="col-sm-9">{{ $salon->building }}</dd>

        <dt class="col-sm-3">Piso</dt>
        <dd class="col-sm-9">{{ $salon->floor }}</dd>

        <dt class="col-sm-3">Capacidad</dt>
        <dd class="col-sm-9">{{ $salon->capacity }}</dd>

        <dt class="col-sm-3">Estado</dt>
        <dd class="col-sm-9 {{ strtoupper($salon->estado) === 'ACTIVO' ? 'text-success' : 'text-danger font-weight-bold' }}">
          {{ strtoupper($salon->estado) }}
        </dd>

        <dt class="col-sm-3">Fecha creación</dt>
        <dd class="col-sm-9">{{ $salon->fyh_creacion ?? '—' }}</dd>

        <dt class="col-sm-3">Última actualización</dt>
        <dd class="col-sm-9">{{ $salon->fyh_actualizacion ?? '—' }}</dd>
      </dl>
    </div>
  </div>

  <div class="card card-outline card-primary mt-3">
    <div class="card-header">
      <h3 class="card-title">Grupos que usan este salón</h3>
    </div>
    <div class="card-body">
      @if($grupos->isEmpty())
        <p class="text-muted">Este salón no tiene grupos asignados.</p>
      @else
        <table class="table table-bordered table-sm table-hover">
          <thead>
            <tr>
              <th>#</th>
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
      @endif
    </div>
  </div>

</div>
@endsection
