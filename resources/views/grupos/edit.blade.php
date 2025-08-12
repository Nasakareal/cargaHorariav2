@extends('adminlte::page')

@section('title', 'Editar Grupo')

@section('content_header')
  <h1 class="text-center w-100">Edición de grupo</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      @if (session('error'))
        <div class="alert alert-danger">{!! session('error') !!}</div>
      @endif

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card card-outline card-success">
        <div class="card-header">
          <h3 class="card-title">Actualiza los datos</h3>
          <div class="card-tools">
            <a href="{{ route('grupos.index') }}" class="btn btn-outline-success btn-sm">
              <i class="fas fa-arrow-left"></i> Volver
            </a>
          </div>
        </div>

        <div class="card-body">
          <form id="editForm" action="{{ route('grupos.update', $grupo->group_id) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="row">
              {{-- Nombre del grupo --}}
              <div class="col-md-6">
                <div class="form-group">
                  <label for="group_name">Nombre del grupo</label>
                  <input type="text"
                         name="group_name"
                         id="group_name"
                         class="form-control"
                         value="{{ old('group_name', $grupo->group_name) }}"
                         required>
                </div>
              </div>

              {{-- Programa educativo --}}
              <div class="col-md-6">
                <div class="form-group">
                  <label for="program_id">Programa educativo</label>
                  <select name="program_id" id="program_id" class="form-control" required>
                    <option value="">— Selecciona un programa —</option>
                    @foreach ($programas as $p)
                      <option value="{{ $p->program_id }}"
                        {{ (string)old('program_id', $grupo->program_id) === (string)$p->program_id ? 'selected' : '' }}>
                        {{ $p->program_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              {{-- Cuatrimestre --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="term_id">Cuatrimestre</label>
                  <select name="term_id" id="term_id" class="form-control" required>
                    <option value="">— Selecciona cuatrimestre —</option>
                    @foreach ($terminos as $t)
                      <option value="{{ $t->term_id }}"
                        {{ (string)old('term_id', $grupo->term_id) === (string)$t->term_id ? 'selected' : '' }}>
                        {{ $t->term_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>

              {{-- Volumen --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="volume">Volumen del grupo</label>
                  <input type="number"
                         name="volume"
                         id="volume"
                         class="form-control"
                         value={{ old('volume', $grupo->volume ?? 0) }}
                         min="0" step="1" required>
                </div>
              </div>

              {{-- Turno --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="turn_id">Turno</label>
                  <select name="turn_id" id="turn_id" class="form-control" required>
                    <option value="">— Selecciona turno —</option>
                    @foreach ($turnos as $sh)
                      <option value="{{ $sh->shift_id }}"
                        {{ (string)old('turn_id', $grupo->turn_id) === (string)$sh->shift_id ? 'selected' : '' }}>
                        {{ $sh->shift_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            {{-- ========= Asignación de salón (Edificio → Planta → Salón) ========= --}}
            <hr>
            <h5 class="mb-3">Asignación de salón</h5>
            <div class="row">
              {{-- Edificio --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="building_select">Edificio</label>
                  <select id="building_select" class="form-control">
                    <option value="">— Selecciona —</option>
                    @foreach($edificios as $b)
                      <option value="{{ $b }}"
                        {{ (old('building_select', $selBuilding ?? '') === $b) ? 'selected' : '' }}>
                        {{ $b }}
                      </option>
                    @endforeach
                  </select>
                  <small class="form-text text-muted">Primero elige un edificio.</small>
                </div>
              </div>

              {{-- Planta --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="floor_select">Planta</label>
                  <select id="floor_select" class="form-control" {{ empty($selFloor) ? 'disabled' : '' }}>
                    @if (!empty($selFloor))
                      <option value="{{ $selFloor }}" selected>{{ $selFloor }}</option>
                    @else
                      <option value="">—</option>
                    @endif
                  </select>
                  <small class="form-text text-muted">Se habilita al elegir edificio.</small>
                </div>
              </div>

              {{-- Salón --}}
              <div class="col-md-4">
                <div class="form-group">
                  <label for="classroom_assigned">Salón asignado</label>
                  <select name="classroom_assigned" id="classroom_assigned" class="form-control"
                          {{ empty($selClassroom) ? 'disabled' : '' }}>
                    @if(!empty($selClassroom))
                      <option value="{{ $selClassroom->classroom_id }}" selected>
                        {{ $selClassroom->classroom_name }}
                        ({{ $selClassroom->building }}{{ $selClassroom->floor ? ', '.$selClassroom->floor : '' }})
                      </option>
                    @else
                      <option value="">—</option>
                    @endif
                  </select>
                  <small class="form-text text-muted">Se habilita al elegir planta.</small>
                </div>
              </div>
            </div>
            {{-- ==================================================================== --}}

            <hr>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-success">Guardar cambios</button>
                  <a href="{{ route('grupos.index') }}" class="btn btn-secondary">Cancelar</a>
                </div>
              </div>
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
  const selBuilding  = document.getElementById('building_select');
  const selFloor     = document.getElementById('floor_select');
  const selClassroom = document.getElementById('classroom_assigned');
  const form         = document.getElementById('editForm');
  const groupId      = {{ (int)$grupo->group_id }};

  const initialBuilding  = @json(old('building_select', $selBuilding ?? null));
  const initialFloor     = @json(old('floor_select', $selFloor ?? null));
  const initialClassroom = @json(old('classroom_assigned', $grupo->classroom_assigned ?? null));

  function clearSelect(select, placeholder){
    select.innerHTML = '';
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = placeholder || '—';
    select.appendChild(opt);
  }

  function loadPlantas(building, thenSelectFloor){
    if(!building){
      clearSelect(selFloor, '—');
      selFloor.disabled = true;
      clearSelect(selClassroom, '—');
      selClassroom.disabled = true;
      return;
    }
    selFloor.disabled = true;
    clearSelect(selFloor, 'Cargando…');

    const url = `{{ route('ajax.edificio.plantas', ['building' => '___B___']) }}`.replace('___B___', encodeURIComponent(building));
    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r => r.json())
      .then(floors => {
        clearSelect(selFloor, '— Selecciona planta —');
        floors.forEach(f => {
          const o = document.createElement('option');
          o.value = f || '';
          o.textContent = f || '—';
          selFloor.appendChild(o);
        });
        selFloor.disabled = false;
        if (thenSelectFloor) {
          selFloor.value = thenSelectFloor;
          selFloor.dispatchEvent(new Event('change'));
        }
      })
      .catch(() => {
        clearSelect(selFloor, '—');
        selFloor.disabled = true;
      });
  }

  function loadSalones(building, floor, thenSelectClassroom){
    if(!building || !floor){
      clearSelect(selClassroom, '—');
      selClassroom.disabled = true;
      return;
    }
    selClassroom.disabled = true;
    clearSelect(selClassroom, 'Cargando…');

    const url = `{{ route('ajax.planta.salones', ['building' => '___B___','floor' => '___F___']) }}`
                  .replace('___B___', encodeURIComponent(building))
                  .replace('___F___', encodeURIComponent(floor || '-'));

    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r => r.json())
      .then(salones => {
        clearSelect(selClassroom, '— Selecciona salón —');
        salones.forEach(s => {
          const o = document.createElement('option');
          o.value = s.classroom_id;
          o.textContent = s.classroom_name;
          selClassroom.appendChild(o);
        });
        selClassroom.disabled = false;
        if (thenSelectClassroom) selClassroom.value = thenSelectClassroom;
      })
      .catch(() => {
        clearSelect(selClassroom, '—');
        selClassroom.disabled = true;
      });
  }

  // Carga dependiente
  selBuilding.addEventListener('change', function(){
    loadPlantas(this.value, null);
  });

  selFloor.addEventListener('change', function(){
    loadSalones(selBuilding.value, this.value, null);
  });

  // Preload si venía con valores
  if (initialBuilding) {
    selBuilding.value = initialBuilding;
    loadPlantas(initialBuilding, initialFloor || null);
    if (initialFloor) {
      loadSalones(initialBuilding, initialFloor, initialClassroom || null);
    }
  }

  // Pre-chequeo de empalmes antes de enviar (opcional; el backend revalida)
  form.addEventListener('submit', function(e){
    const classroomId = selClassroom.value;
    if(!classroomId){ return; } // sin aula asignada → sin check

    e.preventDefault(); // detener envío mientras validamos

    const url = `{{ route('grupos.validar-salon', ['id' => '___ID___','classroom' => '___C___']) }}`
                  .replace('___ID___', encodeURIComponent(groupId))
                  .replace('___C___', encodeURIComponent(classroomId));

    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r => r.ok ? r.json() : null)
      .then(json => {
        if(!json){ // si la ruta no existe o falla, continuamos y que backend decida
          form.submit();
          return;
        }
        if(json.ok === false && Array.isArray(json.conflictos) && json.conflictos.length){
          const html = json.conflictos.map(c => `<div>${c}</div>`).join('');
          Swal.fire({
            icon: 'error',
            title: 'Empalmes detectados',
            html: html || 'El salón ya está ocupado en ese horario.',
            confirmButtonText: 'Entendido'
          });
        }else{
          form.submit();
        }
      })
      .catch(() => form.submit());
  });

})();
</script>

@if ($errors->any())
<script>
Swal.fire({
  icon: 'warning',
  title: 'Revisa los datos',
  html: `{!! implode('<br>', $errors->all()) !!}`,
  position: 'center'
});
</script>
@endif
@endsection
