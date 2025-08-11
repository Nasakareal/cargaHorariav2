@extends('adminlte::page')

@section('title', 'Detalle de Grupo')

@section('content_header')
  <h1 class="text-center w-100">Detalle de grupo</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-warning">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Información del grupo</h3>

          <div class="btn-group">
            <a href="{{ route('grupos.index') }}" class="btn btn-secondary btn-sm">
              <i class="fas fa-arrow-left"></i> Volver
            </a>

            @can('editar grupos')
            <a href="{{ route('grupos.edit', $grupo->group_id ?? $grupo->id) }}"
               class="btn btn-success btn-sm" title="Editar">
              <i class="fas fa-edit"></i> Editar
            </a>
            @endcan

            @can('eliminar grupos')
            <form action="{{ route('grupos.destroy', $grupo->group_id ?? $grupo->id) }}"
                  method="POST" id="formEliminar-{{ $grupo->group_id ?? $grupo->id }}" class="d-inline">
              @csrf
              @method('DELETE')
              <button type="button" class="btn btn-danger btn-sm"
                      onclick="confirmarEliminar('{{ $grupo->group_id ?? $grupo->id }}', this)">
                <i class="fas fa-trash"></i> Eliminar
              </button>
            </form>
            @endcan
          </div>
        </div>

        <div class="card-body">
          @php
            $id        = $grupo->group_id ?? $grupo->id ?? null;
            $name      = $grupo->group_name ?? '—';
            $programa  = $grupo->program_name ?? $grupo->programa ?? '—';
            $area      = $grupo->area ?? '—';
            $term      = $grupo->term_name ?? $grupo->term_id ?? '—';
            $turno     = $grupo->shift_name ?? $grupo->turno ?? '—';  // tabla real: shifts
            $volume    = (int)($grupo->volume ?? 0);
            $aula      = $grupo->classroom_assigned ?? '—';
            $lab       = $grupo->lab_assigned ?? '—';

            // Materias del grupo (opcional): puedes pasar $materias como colección/array de:
            // subject_name, weekly_hours, teacher_name (null => “Sin profesor”)
            $materias = $materias ?? [];
          @endphp

          <div class="row">
            <div class="col-md-4 mb-3">
              <strong>ID:</strong>
              <div>#{{ $id }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Grupo:</strong>
              <div>{{ $name }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Programa:</strong>
              <div>{{ $programa }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Área:</strong>
              <div>{{ $area }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Cuatrimestre:</strong>
              <div>{{ $term }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Turno:</strong>
              <div>{{ $turno }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Volumen (alumnos):</strong>
              <div>{{ $volume }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Aula asignada:</strong>
              <div>{{ $aula ?: '—' }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Laboratorio asignado:</strong>
              <div>{{ $lab ?: '—' }}</div>
            </div>
          </div>

          {{-- Materias del grupo (opcional) --}}
          <div class="row mt-3">
            <div class="col-12">
              <h5 class="mb-2">Materias del grupo</h5>
              @if(!empty($materias))
                <div class="table-responsive">
                  <table class="table table-bordered table-sm">
                    <thead>
                      <tr>
                        <th>Materia</th>
                        <th class="text-center">Horas/Sem</th>
                        <th>Profesor asignado</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($materias as $m)
                        @php
                          $nombreMateria = $m->subject_name ?? $m['subject_name'] ?? '—';
                          $horas         = $m->weekly_hours ?? $m['weekly_hours'] ?? 0;
                          $profe         = $m->teacher_name ?? $m['teacher_name'] ?? null;
                        @endphp
                        <tr class="{{ $profe ? '' : 'table-warning' }}">
                          <td>{{ $nombreMateria }}</td>
                          <td class="text-center">{{ (int)$horas }}</td>
                          <td>
                            @if($profe)
                              <span class="badge badge-success">{{ $profe }}</span>
                            @else
                              <span class="badge badge-secondary">Sin profesor</span>
                            @endif
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <span class="text-muted">No hay materias registradas para este grupo.</span>
              @endif
            </div>
          </div>

          <div class="row mt-3">
            <div class="col-md-6 mb-3">
              <strong>Creación:</strong>
              <div>{{ optional($grupo->fyh_creacion ?? null)->format('Y-m-d H:i') }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Última actualización:</strong>
              <div>{{ optional($grupo->fyh_actualizacion ?? null)->format('Y-m-d H:i') ?? '—' }}</div>
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
    title: 'Eliminar Grupo',
    text: '¿Desea eliminar este grupo?',
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
