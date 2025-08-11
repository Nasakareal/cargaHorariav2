@extends('adminlte::page')

@section('title', 'Detalle de Programa')

@section('content_header')
  <h1 class="text-center w-100">Detalle de programa</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-warning">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Información del programa</h3>

          <div class="btn-group">
            <a href="{{ route('programas.index') }}" class="btn btn-secondary btn-sm">
              <i class="fas fa-arrow-left"></i> Volver
            </a>

            @can('editar programas')
            <a href="{{ route('programas.edit', $programa->program_id) }}"
               class="btn btn-success btn-sm" title="Editar">
              <i class="fas fa-edit"></i> Editar
            </a>
            @endcan

            @can('eliminar programas')
            <form action="{{ route('programas.destroy', $programa->program_id) }}"
                  method="POST" id="formEliminar-{{ $programa->program_id }}" class="d-inline">
              @csrf
              @method('DELETE')
              <button type="button" class="btn btn-danger btn-sm"
                      onclick="confirmarEliminar('{{ $programa->program_id }}', this)">
                <i class="fas fa-trash"></i> Eliminar
              </button>
            </form>
            @endcan
          </div>
        </div>

        <div class="card-body">
          @php
            $id     = $programa->program_id ?? $programa->id ?? null;
            $nombre = $programa->program_name ?? '—';
            $area   = $programa->area ?? '—';

            $totalMaterias = (int)($total_materias ?? 0);

            // Cuatrimestres y materias agregadas (pueden venir como string "a, b, c")
            $toBadges = function ($val) {
              if (is_null($val) || $val === '') return [];
              if (is_string($val)) return array_filter(array_map('trim', explode(',', $val)));
              if ($val instanceof \Illuminate\Support\Collection) return $val->toArray();
              if (is_array($val)) return $val;
              return [];
            };

            $cuatrosBadges  = $toBadges($cuatrimestres ?? null);
            $materiasBadges = $toBadges($materias_join ?? null);

            // Grupos lista (collection de objetos con group_name)
            $gruposList = collect($grupos ?? [])->map(function($g){
              return (object)[
                'id'   => $g->group_id ?? null,
                'name' => $g->group_name ?? '—',
              ];
            });

            // Formateo seguro de fechas
            $fmtDate = function($v){
              try { return $v ? \Carbon\Carbon::parse($v)->format('Y-m-d H:i') : '—'; }
              catch (\Throwable $e) { return $v ?: '—'; }
            };
          @endphp

          <div class="row">
            <div class="col-md-4 mb-3">
              <strong>ID:</strong>
              <div>#{{ $id }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Programa:</strong>
              <div>{{ $nombre }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Área:</strong>
              <div>{{ $area }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Total de materias:</strong>
              <div>{{ $totalMaterias }}</div>
            </div>

            <div class="col-md-8 mb-3">
              <strong>Cuatrimestres:</strong>
              <div>
                @forelse ($cuatrosBadges as $c)
                  <span class="badge badge-info">{{ $c }}</span>
                @empty
                  <span class="text-muted">Sin cuatrimestres</span>
                @endforelse
              </div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Grupos del programa:</strong>
              <div>
                @if($gruposList->count())
                  @foreach ($gruposList as $g)
                    <span class="badge badge-secondary">{{ $g->name }}</span>
                  @endforeach
                @else
                  <span class="text-muted">Sin grupos</span>
                @endif
              </div>
            </div>

            {{-- Materias (tabla detallada) --}}
            <div class="col-md-12">
              <div class="card card-outline card-primary">
                <div class="card-header">
                  <h3 class="card-title">Materias del programa</h3>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover table-sm mb-0">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Materia</th>
                          <th class="text-center">Horas/Sem</th>
                          <th class="text-center">Cuatrimestre</th>
                        </tr>
                      </thead>
                      <tbody>
                        @php $rows = collect($materias ?? []); @endphp
                        @forelse ($rows as $i => $m)
                          <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $m->subject_name ?? '—' }}</td>
                            <td class="text-center">{{ (int)($m->weekly_hours ?? 0) }}</td>
                            <td class="text-center">{{ $m->cuatrimestre ?? '—' }}</td>
                          </tr>
                        @empty
                          <tr>
                            <td colspan="4" class="text-center text-muted">Sin materias registradas para este programa</td>
                          </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
                @if(!empty($materiasBadges))
                  <div class="card-footer">
                    <small class="text-muted">Resumen:</small>
                    <div>
                      @foreach ($materiasBadges as $mb)
                        <span class="badge badge-warning">{{ $mb }}</span>
                      @endforeach
                    </div>
                  </div>
                @endif
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Creación:</strong>
              <div>{{ $fmtDate($programa->fyh_creacion ?? null) }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Última actualización:</strong>
              <div>{{ $fmtDate($programa->fyh_actualizacion ?? null) }}</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmarEliminar(id, btn){
  const form = document.getElementById('formEliminar-' + id);
  if(!form){ console.error('No existe formEliminar-', id); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar Programa',
    text: '¿Desea eliminar este programa?',
    icon: 'warning',
    showDenyButton: true,
    confirmButtonText: 'Eliminar',
    confirmButtonColor: '#E43636',
    denyButtonColor: '#007bff',
    denyButtonText: 'Cancelar',
    position: 'center'
  }).then((r)=>{
    if(r.isConfirmed){ form.submit(); }
    else { btn.disabled = false; }
  });
}
</script>

@if (session('success'))
<script>
Swal.fire({ icon:'success', title:@json(session('success')), position:'center', timer:2500, showConfirmButton:false });
</script>
@endif
@if (session('error'))
<script>
Swal.fire({ icon:'error', title:'Ups', text:@json(session('error')), position:'center' });
</script>
@endif
@endsection
