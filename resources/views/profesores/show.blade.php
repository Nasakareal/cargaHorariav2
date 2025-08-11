@extends('adminlte::page')

@section('title', 'Detalle de Profesor')

@section('content_header')
  <h1 class="text-center w-100">Detalle de profesor</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-warning">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Información del profesor</h3>

          <div class="btn-group">
            <a href="{{ route('profesores.index') }}" class="btn btn-secondary btn-sm">
              <i class="fas fa-arrow-left"></i> Volver
            </a>

            @can('asignar materias')
            <a href="{{ route('profesores.asignar-materias', $profesor->teacher_id ?? $profesor->id) }}"
               class="btn btn-warning btn-sm" title="Asignar materias">
              <i class="bi bi-journal-text"></i> Asignar materias
            </a>
            @endcan

            @can('editar profesores')
            <a href="{{ route('profesores.edit', $profesor->teacher_id ?? $profesor->id) }}"
               class="btn btn-success btn-sm" title="Editar">
              <i class="fas fa-edit"></i> Editar
            </a>
            @endcan

            @can('eliminar profesores')
            <form action="{{ route('profesores.destroy', $profesor->teacher_id ?? $profesor->id) }}"
                  method="POST" id="formEliminar-{{ $profesor->teacher_id ?? $profesor->id }}" class="d-inline">
              @csrf
              @method('DELETE')
              <button type="button" class="btn btn-danger btn-sm"
                      onclick="confirmarEliminar('{{ $profesor->teacher_id ?? $profesor->id }}', this)">
                <i class="fas fa-trash"></i> Eliminar
              </button>
            </form>
            @endcan
          </div>
        </div>

        <div class="card-body">
          @php
            $id   = $profesor->teacher_id ?? $profesor->id ?? null;
            $name = $profesor->teacher_name ?? $profesor->nombres ?? '—';

            // Campos simples
            $clasificacion = $profesor->clasificacion ?? 'No asignado';
            $area          = $profesor->area ?? 'No asignado';
            $horas         = $profesor->hours ?? $horas_semanales ?? 0;

            // Materias / Programas / Grupos pueden venir como string "a, b, c" o arreglo/colección
            $toBadges = function ($val) {
              if (is_null($val) || $val === '') return [];
              if (is_string($val)) return array_filter(array_map('trim', explode(',', $val)));
              if ($val instanceof \Illuminate\Support\Collection) return $val->toArray();
              if (is_array($val)) return $val;
              return [];
            };

            $materias  = $toBadges($materias ?? ($profesor->materias ?? null));
            $programas = $toBadges($programas ?? ($profesor->programas ?? null));
            $grupos    = $toBadges($grupos ?? ($profesor->grupos ?? null));

            // Horarios: colección/array de objetos con day_of_week, start_time, end_time
            $horarios  = $horarios ?? ($profesor->horarios ?? collect());
            $diasMap = [
              'Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles',
              'Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo',
              // por si ya vienen en español:
              'Lunes'=>'Lunes','Martes'=>'Martes','Miércoles'=>'Miércoles','Jueves'=>'Jueves',
              'Viernes'=>'Viernes','Sábado'=>'Sábado','Domingo'=>'Domingo'
            ];
            $fmt = function($t){ return $t ? substr((string)$t,0,5) : '—'; };
          @endphp

          <div class="row">
            <div class="col-md-6 mb-3">
              <strong>ID:</strong>
              <div>#{{ $id }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Nombres:</strong>
              <div>{{ $name }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Clasificación:</strong>
              <div>{{ $clasificacion }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Área:</strong>
              <div>{{ $area }}</div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Horas semanales:</strong>
              <div>{{ (int)$horas }}</div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Materias:</strong>
              <div>
                @forelse ($materias as $m)
                  <span class="badge badge-warning">{{ $m }}</span>
                @empty
                  <span class="text-muted">Sin materias</span>
                @endforelse
              </div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Programas:</strong>
              <div>
                @forelse ($programas as $p)
                  <span class="badge badge-info">{{ $p }}</span>
                @empty
                  <span class="text-muted">Sin programas</span>
                @endforelse
              </div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Grupos:</strong>
              <div>
                @forelse ($grupos as $g)
                  <span class="badge badge-secondary">{{ $g }}</span>
                @empty
                  <span class="text-muted">Sin grupos</span>
                @endforelse
              </div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Horarios disponibles:</strong>
              <div>
                @php
                  $rows = collect($horarios)->map(function($h){
                    return (object)[
                      'day'  => $h->day_of_week ?? ($h['day_of_week'] ?? null),
                      'ini'  => $h->start_time  ?? ($h['start_time']  ?? null),
                      'fin'  => $h->end_time    ?? ($h['end_time']    ?? null),
                    ];
                  });
                @endphp

                @if($rows->count())
                  <ul class="mb-0">
                    @foreach ($rows as $h)
                      <li>
                        {{ $diasMap[$h->day] ?? $h->day ?? '—' }}:
                        de {{ $fmt($h->ini) }} a {{ $fmt($h->fin) }}
                      </li>
                    @endforeach
                  </ul>
                @else
                  <span class="text-muted">Sin disponibilidad registrada</span>
                @endif
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Creación:</strong>
              <div>{{ optional($profesor->fyh_creacion ?? null)->format('Y-m-d H:i') }}</div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Última actualización:</strong>
              <div>{{ optional($profesor->fyh_actualizacion ?? null)->format('Y-m-d H:i') ?? '—' }}</div>
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
    title: 'Eliminar Profesor',
    text: '¿Desea eliminar este profesor?',
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
