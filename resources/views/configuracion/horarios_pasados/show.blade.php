@extends('adminlte::page')

@section('title', 'Paquete '.$fecha)

@section('content_header')
  <h1 class="text-center w-100">Paquete — {{ $fecha }} @if($quarterName) <small class="text-muted">({{ $quarterName }})</small>@endif</h1>
@endsection

@section('content')
<div class="container-xl">
  {{-- Filtros --}}
  <div class="card card-outline card-info mb-4">
    <div class="card-header">
      <h3 class="card-title">Filtrar horarios archivados</h3>
    </div>
    <div class="card-body">
      <form method="GET" action="{{ route('configuracion.horarios-pasados.show', $fecha) }}">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Grupo</label>
            <select name="group_id" id="group_id" class="form-control">
              <option value="">— Ninguno —</option>
              @foreach($grupos as $g)
                <option value="{{ $g->group_id }}" @selected($groupId == $g->group_id)>
                  {{ $g->group_name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Profesor</label>
            <select name="teacher_id" id="teacher_id" class="form-control">
              <option value="">— Ninguno —</option>
              @foreach($profes as $p)
                <option value="{{ $p->teacher_id }}" @selected($teacherId == $p->teacher_id)>
                  {{ $p->teacher_name }}
                </option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-primary"><i class="bi bi-search"></i> Ver horarios</button>
          <a href="{{ route('configuracion.horarios-pasados.show', $fecha) }}" class="btn btn-secondary">Limpiar</a>
          <a href="{{ route('configuracion.horarios-pasados.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
      </form>
    </div>
  </div>

  {{-- Resultados --}}
  @if(!empty($horas) && !empty($dias))
    <div class="card card-outline card-info">
      <div class="card-header">
        <h3 class="card-title">Detalle del horario</h3>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="tabla" class="table table-bordered table-hover">
            <thead>
              <tr>
                <th>Hora/Día</th>
                @foreach($dias as $d) <th>{{ $d }}</th> @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach($horas as $h)
                <tr>
                  <td>{{ $h }}</td>
                  @foreach($dias as $d)
                    <td>{!! $tabla[$h][$d] ?? '' !!}</td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @else
    <div class="alert alert-secondary">No hay horarios para el filtro seleccionado.</div>
  @endif
</div>
@endsection

@section('js')
<script>
  // Si eliges grupo, limpia profesor, y viceversa (como en tu PHP)
  document.getElementById('group_id')?.addEventListener('change', function(){
    if (this.value) document.getElementById('teacher_id').value = '';
  });
  document.getElementById('teacher_id')?.addEventListener('change', function(){
    if (this.value) document.getElementById('group_id').value = '';
  });
</script>
@endsection
