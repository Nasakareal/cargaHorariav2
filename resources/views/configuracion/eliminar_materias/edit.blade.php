{{-- resources/views/configuracion/eliminar_materias/edit.blade.php --}}
@extends('adminlte::page')

@section('title', 'Quitar materias · ' . ($profesor->teacher_name ?? 'Profesor'))

@section('content_header')
  <h1 class="text-center w-100">
    Quitar materias — {{ $profesor->teacher_name }}
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

      <div class="card card-outline card-danger">
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title">Selecciona lo que quieras quitar</h3>

          <div class="ms-auto d-flex align-items-center gap-2" style="gap:.5rem">
            {{-- Filtro por grupo (opcional) --}}
            <label for="filtro_grupo" class="me-2 mb-0">Filtrar por grupo:</label>
            <select id="filtro_grupo" class="form-control form-control-sm" style="min-width:260px">
              <option value="">— Todos los grupos —</option>
              @foreach ($grupos as $g)
                <option value="{{ $g->group_id }}">{{ $g->group_name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="card-body">
          <form id="formQuitar" action="{{ route('configuracion.eliminar-materias.destroy-selected', $profesor->teacher_id) }}" method="POST" autocomplete="off">
            @csrf

            {{-- Se llenan dinámicamente: <input type="hidden" name="teacher_subject_ids[]"> --}}
            <div id="hidden_ts_ids"></div>

            <div class="table-responsive">
              <table id="tablaAsignadas" class="table table-striped table-bordered table-hover table-sm align-middle mb-2">
                <thead>
                  <tr>
                    <th style="width: 36px" class="text-center">
                      <input type="checkbox" id="chk_all">
                    </th>
                    <th>Grupo</th>
                    <th>Materia</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($asignadas as $a)
                    <tr data-ts="{{ $a->teacher_subject_id }}">
                      <td class="text-center">
                        <input type="checkbox" class="chk_item" value="{{ $a->teacher_subject_id }}">
                      </td>
                      <td>{{ $a->group_name ?? ('Grupo ' . $a->group_id) }}</td>
                      <td>{{ $a->subject_name }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="3" class="text-center text-muted">No hay materias asignadas.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <div class="d-flex gap-2 mt-3">
              @can('eliminar materias')
                <button type="button" class="btn btn-danger" id="btnQuitarSel">
                  <i class="bi bi-scissors"></i> Quitar seleccionadas
                </button>

                <button type="button" class="btn btn-outline-danger" id="btnQuitarTodasGrupo">
                  <i class="bi bi-x-circle"></i> Quitar todas (grupo filtrado)
                </button>
              @endcan

              <a href="{{ route('configuracion.eliminar-materias.index') }}" class="btn btn-secondary ms-auto">
                Volver
              </a>
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
    ajaxMateriasAsignadas: @json(route('configuracion.eliminar-materias.ajax.materias-asignadas', $profesor->teacher_id)),
    csrf: @json(csrf_token()),
  };

  const $tabla   = document.getElementById('tablaAsignadas');
  const $tbody   = $tabla.querySelector('tbody');
  const $chkAll  = document.getElementById('chk_all');
  const $hidden  = document.getElementById('hidden_ts_ids');
  const $btnSel  = document.getElementById('btnQuitarSel');
  const $btnAllG = document.getElementById('btnQuitarTodasGrupo');
  const $form    = document.getElementById('formQuitar');
  const $filtro  = document.getElementById('filtro_grupo');

  function currentRows(){
    return Array.from($tbody.querySelectorAll('tr[data-ts]'));
  }

  function checkedItems(){
    return Array.from($tbody.querySelectorAll('.chk_item:checked')).map(i => i.value);
  }

  function setHiddenTsIds(ids){
    $hidden.innerHTML = '';
    ids.forEach(id => {
      const i = document.createElement('input');
      i.type = 'hidden';
      i.name = 'teacher_subject_ids[]';
      i.value = id;
      $hidden.appendChild(i);
    });
  }

  function renderRows(rows){
    $tbody.innerHTML = '';
    if(!rows || rows.length === 0){
      $tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No hay materias asignadas.</td></tr>';
      return;
    }
    const frag = document.createDocumentFragment();
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.setAttribute('data-ts', r.teacher_subject_id);

      const td0 = document.createElement('td');
      td0.className = 'text-center';
      td0.style.width = '36px';
      td0.innerHTML = `<input type="checkbox" class="chk_item" value="${r.teacher_subject_id}">`;

      const td1 = document.createElement('td');
      td1.textContent = r.group_name || ('Grupo ' + r.group_id);

      const td2 = document.createElement('td');
      td2.textContent = r.subject_name;

      tr.appendChild(td0); tr.appendChild(td1); tr.appendChild(td2);
      frag.appendChild(tr);
    });
    $tbody.appendChild(frag);
  }

  function loadByGroup(groupId){
    const payload = groupId ? {group_id: parseInt(groupId, 10)} : {};
    return fetch(rutas.ajaxMateriasAsignadas, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': rutas.csrf },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(j => {
      if(!j || !j.ok){ throw new Error('Respuesta inválida'); }
      renderRows(j.data || []);
      $chkAll.checked = false;
    })
    .catch(err => {
      console.error(err);
      renderRows([]);
      $chkAll.checked = false;
    });
  }

  $chkAll.addEventListener('change', (e)=>{
    const on = e.target.checked;
    currentRows().forEach(tr => {
      const c = tr.querySelector('.chk_item');
      if(c) c.checked = on;
    });
  });

  if($btnSel){
    $btnSel.addEventListener('click', ()=>{
      const ids = checkedItems();
      if(ids.length === 0){
        Swal.fire({icon:'info', title:'Nada seleccionado', text:'Selecciona al menos una asignación.'});
        return;
      }
      Swal.fire({
        title: 'Quitar asignaciones',
        text: `¿Deseas eliminar ${ids.length} asignación(es) del profesor?`,
        icon: 'warning',
        showDenyButton: true,
        confirmButtonText: 'Sí, quitar',
        confirmButtonColor: '#E43636',
        denyButtonText: 'Cancelar',
      }).then((r)=>{
        if(!r.isConfirmed) return;
        setHiddenTsIds(ids);
        $form.submit();
      });
    });
  }

  if($btnAllG){
    $btnAllG.addEventListener('click', ()=>{
      const groupId = $filtro.value;
      if(!groupId){
        Swal.fire({icon:'info', title:'Sin grupo', text:'Selecciona un grupo en el filtro para esta opción.'});
        return;
      }
      const rows = currentRows();
      if(rows.length === 0){
        Swal.fire({icon:'info', title:'No hay asignaciones', text:'No hay materias para quitar en este grupo.'});
        return;
      }
      const ids = rows.map(tr => tr.getAttribute('data-ts')).filter(Boolean);
      Swal.fire({
        title: 'Quitar todas del grupo',
        text: `¿Deseas eliminar ${ids.length} asignación(es) del grupo seleccionado?`,
        icon: 'warning',
        showDenyButton: true,
        confirmButtonText: 'Sí, quitar todas',
        confirmButtonColor: '#E43636',
        denyButtonText: 'Cancelar',
      }).then((r)=>{
        if(!r.isConfirmed) return;
        setHiddenTsIds(ids);
        $form.submit();
      });
    });
  }

  $filtro.addEventListener('change', ()=>{
    loadByGroup($filtro.value);
  });

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
