@extends('adminlte::page')

@section('title', 'Asignar materias · ' . ($profesor->teacher_name ?? 'Profesor'))

@section('content_header')
  <h1 class="text-center w-100">
    Asignar materias — {{ $profesor->teacher_name }}
  </h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card card-outline card-warning">
        <div class="card-header">
          <h3 class="card-title">Llene los datos</h3>
        </div>

        <div class="card-body">
          <form action="{{ route('profesores.asignar-materias.store', $profesor->teacher_id) }}" method="POST" autocomplete="off" id="formAsignar">
            @csrf
            <input type="hidden" name="teacher_id" value="{{ $profesor->teacher_id }}">
            {{-- Aquí se van agregando <input type="hidden" name="grupos_asignados[]"> --}}
            <div id="hidden_group_inputs"></div>

            {{-- Total de horas asignadas + Selector de grupo --}}
            <div class="row g-3 mb-4">
              <div class="col-md-5">
                <label for="total_hours" class="form-label">Total de horas asignadas</label>
                <input type="text" id="total_hours" class="form-control" readonly>
              </div>

              <div class="col-md-7">
                <label for="grupos_disponibles" class="form-label">Grupos disponibles</label>
                <div class="input-group">
                    @php
                        $mapTurno = [
                        1=>'MATUTINO', 2=>'VESPERTINO', 3=>'MIXTO', 4=>'ZINAPÉCUARO',
                        5=>'ENFERMERIA', 6=>'MATUTINO AVANZADO', 7=>'VESPERTINO AVANZADO'
                        ];
                    @endphp

                    <select id="grupos_disponibles" class="form-control">
                        <option value="" selected disabled>— Seleccione un grupo —</option>
                        @foreach ($grupos as $g)
                        @php
                            $label = $g->group_name ?? ('Grupo '.$g->group_id);
                            $turno = $mapTurno[$g->turn_id] ?? null;
                        @endphp
                        <option value="{{ $g->group_id }}">
                            {{ $label }}@if($turno) ({{ $turno }}) @endif
                        </option>
                        @endforeach
                    </select>

                  <button id="confirm_group" class="btn btn-primary" type="button">Seleccionar Grupo</button>
                </div>
                <small class="text-muted d-block mt-1">
                  Puedes seleccionar varios grupos haciendo clic en “Seleccionar Grupo” varias veces.
                </small>
                <div id="gruposElegidos" class="mt-2"></div>
              </div>
            </div>

            {{-- Cajas de materias disponibles / asignadas --}}
            <div class="row">
              <div class="col-md-5">
                <label for="materias_disponibles" class="form-label">Materias disponibles</label>
                <select id="materias_disponibles" class="form-control" multiple style="height: 220px;">
                  {{-- Se carga por AJAX al elegir grupo --}}
                </select>
              </div>

              <div class="col-md-2 d-flex flex-column align-items-stretch justify-content-center text-center mt-3 mt-md-0">
                <button type="button" id="add_subject" class="btn btn-primary mb-2">Agregar &gt;&gt;</button>
                <button type="button" id="remove_subject" class="btn btn-primary">&lt;&lt; Quitar</button>
              </div>

              <div class="col-md-5">
                <label for="materias_asignadas" class="form-label">Materias asignadas</label>
                <select id="materias_asignadas" name="materias_asignadas[]" class="form-control" multiple style="height: 220px;">
                  @foreach ($materiasAsignadas as $m)
                    <option value="{{ $m->subject_id }}"
                            data-hours="{{ (int)$m->weekly_hours }}"
                            {{-- si quieres precargar por grupo, podrías filtrar aquí por $m->group_id --}}
                    >{{ $m->subject_name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <hr>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning">Actualizar</button>
              <a href="{{ route('profesores.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>

          </form>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  const rutas = {
    materiasPorGrupo: @json(route('profesores.ajax.materias-por-grupo', $profesor->teacher_id)),
    horasProfesor:    @json(route('profesores.ajax.horas', $profesor->teacher_id)),
    csrf:             @json(csrf_token()),
  };

  const $hiddenGroups   = document.getElementById('hidden_group_inputs');
  const $gruposSelect   = document.getElementById('grupos_disponibles');
  const $btnConfirmGrp  = document.getElementById('confirm_group');
  const $materiasDisp   = document.getElementById('materias_disponibles');
  const $materiasAsig   = document.getElementById('materias_asignadas');
  const $totalHoras     = document.getElementById('total_hours');
  const $gruposElegidos = document.getElementById('gruposElegidos');
  const $form           = document.getElementById('formAsignar');

  function addHiddenGroup(groupId){
    if ([...$hiddenGroups.querySelectorAll('input[name="grupos_asignados[]"]')]
        .some(i => i.value === String(groupId))) return;

    const i = document.createElement('input');
    i.type  = 'hidden';
    i.name  = 'grupos_asignados[]';
    i.value = groupId;
    $hiddenGroups.appendChild(i);
    const chip = document.createElement('span');
    chip.className = 'badge bg-primary me-1';
    chip.dataset.groupId = groupId;
    chip.textContent = ($gruposSelect.options[$gruposSelect.selectedIndex]?.text || ('Grupo '+groupId));
    $gruposElegidos.appendChild(chip);
  }

  function clearAsignadas(){
    [...$materiasAsig.options].forEach(o => $materiasDisp.appendChild(o));
    calcTotal();
  }

  function calcTotal(){
    let total = 0;
    [...$materiasAsig.options].forEach(o => {
      const h = parseInt(o.getAttribute('data-hours'), 10) || 0;
      total += h;
    });
    $totalHoras.value = total;
  }

  function cargarHorasIniciales(){
    fetch(rutas.horasProfesor, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': rutas.csrf },
      body: JSON.stringify({})
    })
    .then(r => r.text())
    .then(t => { $totalHoras.value = parseInt(t, 10) || 0; })
    .catch(() => { $totalHoras.value = 0; });
  }

  function cargarMateriasPorGrupo(groupId){
    fetch(rutas.materiasPorGrupo, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': rutas.csrf },
      body: JSON.stringify({ group_id: groupId })
    })
    .then(r => r.text())
    .then(html => {
      $materiasDisp.innerHTML = html;
    })
    .catch(() => { $materiasDisp.innerHTML = ''; });
  }

  $btnConfirmGrp.addEventListener('click', () => {
    const groupId = $gruposSelect.value;
    if (!groupId) return;

    if ($materiasAsig.options.length > 0) {
      Swal.fire({
        title: '¿Eliminar materias asignadas?',
        text: 'Ya tienes materias asignadas. ¿Quieres eliminarlas antes de seleccionar otro grupo?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'No, cancelar',
      }).then((res) => {
        if (!res.isConfirmed) return;
        $materiasAsig.innerHTML = '';
        $totalHoras.value = 0;
        $hiddenGroups.innerHTML = '';
        $gruposElegidos.innerHTML = '';
        addHiddenGroup(groupId);
        cargarMateriasPorGrupo(groupId);
      });
    } else {
      addHiddenGroup(groupId);
      cargarMateriasPorGrupo(groupId);
    }
  });

  document.getElementById('add_subject').addEventListener('click', () => {
    [...$materiasDisp.selectedOptions].forEach(o => $materiasAsig.appendChild(o));
    calcTotal();
  });

  document.getElementById('remove_subject').addEventListener('click', () => {
    [...$materiasAsig.selectedOptions].forEach(o => $materiasDisp.appendChild(o));
    calcTotal();
  });

  $form.addEventListener('submit', () => {
    [...$materiasAsig.options].forEach(o => o.selected = true);
  });

  cargarHorasIniciales();
  calcTotal();
})();
</script>

{{-- Flashes --}}
@if (session('success'))
<script>
Swal.fire({ icon:'success', title:@json(session('success')), timer:4500, showConfirmButton:false, position:'center' });
</script>
@endif
@if (session('error'))
<script>
Swal.fire({ icon:'error', title:'Ups', text:@json(session('error')), position:'center' });
</script>
@endif
@if ($errors->any())
<script>
Swal.fire({ icon:'warning', title:'Revisa los datos', html:`{!! implode('<br>', $errors->all()) !!}`, position:'center' });
</script>
@endif
@endsection
