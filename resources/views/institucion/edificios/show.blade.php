@extends('adminlte::page')

@section('title', 'Detalle del Edificio')

@section('content_header')
  <h1 class="text-center w-100">Detalle del edificio</h1>
@endsection

@section('content')
<div class="container-xl">

  {{-- Card: info edificio --}}
  <div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title">Información del edificio</h3>
      <div class="card-tools">
        <a href="{{ route('institucion.edificios.index') }}" class="btn btn-secondary btn-sm">
          <i class="fas fa-arrow-left"></i> Volver
        </a>
        @can('editar edificios')
          <a href="{{ route('institucion.edificios.edit', $rowId) }}" class="btn btn-success btn-sm">
            <i class="bi bi-pencil"></i> Editar
          </a>
        @endcan
      </div>
    </div>

    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Nombre</dt>
        <dd class="col-sm-9">{{ $edificio->building_name }}</dd>

        <dt class="col-sm-3">Plantas</dt>
        <dd class="col-sm-9">
          @php
            $baja = (int)($edificio->planta_baja ?? 0) === 1;
            $alta = (int)($edificio->planta_alta ?? 0) === 1;
          @endphp
          @if($baja)
            <span class="badge badge-primary">BAJA</span>
          @endif
          @if($alta)
            <span class="badge badge-info">ALTA</span>
          @endif
          @if(!$baja && !$alta)
            <span class="text-muted">—</span>
          @endif
        </dd>

        <dt class="col-sm-3">Áreas</dt>
        <dd class="col-sm-9">
          @php $areasList = collect(explode(',', (string)($edificio->areas ?? '')))->map('trim')->filter(); @endphp
          @forelse($areasList as $a)
            <span class="badge badge-secondary mr-1">{{ $a }}</span>
          @empty
            <span class="text-muted">—</span>
          @endforelse
        </dd>

        <dt class="col-sm-3">Fecha creación</dt>
        <dd class="col-sm-9">{{ $edificio->fyh_creacion_min ?? '—' }}</dd>

        <dt class="col-sm-3">Última actualización</dt>
        <dd class="col-sm-9">{{ $edificio->fyh_actualizacion_max ?? '—' }}</dd>
      </dl>
    </div>
  </div>

  {{-- Card: salones del edificio --}}
  <div class="card card-outline card-primary mt-3">
    <div class="card-header">
      <h3 class="card-title">Salones de {{ $edificio->building_name }}</h3>
    </div>
    <div class="card-body">
      @if(empty($salones) || (isset($salones) && $salones->isEmpty()))
        <p class="text-muted mb-0">Este edificio no tiene salones registrados.</p>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-hover mb-0">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Salón</th>
                <th class="text-center">Planta</th>
                <th class="text-center">Capacidad</th>
                <th class="text-center">Grupos</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach($salones as $i => $s)
                @php
                  $plRaw = strtoupper(trim($s->floor ?? ''));
                  $plTx  = in_array($plRaw, ['ALTA','BAJA']) ? $plRaw : '—';
                  $cap   = (int)($s->capacity ?? 0);
                  $grs   = (int)($s->grupos_count ?? 0);
                  $estTx = strtoupper(trim($s->estado ?? '—'));
                  $isOk  = ($estTx === 'ACTIVO');
                @endphp
                <tr>
                  <td class="text-center">{{ $i+1 }}</td>
                  <td>{{ $s->classroom_name }}</td>
                  <td class="text-center">
                    @if($plTx === 'ALTA')
                      <span class="badge badge-info">ALTA</span>
                    @elseif($plTx === 'BAJA')
                      <span class="badge badge-primary">BAJA</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-center">{{ $cap }}</td>
                  <td class="text-center">
                    @if($grs > 0)
                      <a href="{{ route('institucion.salones.show', $s->classroom_id) }}" title="Ver grupos">{{ $grs }}</a>
                    @else
                      0
                    @endif
                  </td>
                  <td class="text-center {{ $isOk ? 'text-success' : 'text-danger font-weight-bold' }}">{{ $estTx }}</td>
                  <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                      <a href="{{ route('institucion.salones.show', $s->classroom_id) }}" class="btn btn-info" title="Ver">
                        <i class="bi bi-eye"></i>
                      </a>
                      @can('editar salones')
                        <a href="{{ route('institucion.salones.edit', $s->classroom_id) }}" class="btn btn-success" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                      @endcan
                    </div>
                  </td>
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
