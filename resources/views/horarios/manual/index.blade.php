@extends('adminlte::page')

@section('title', 'Asignación manual de horarios')

{{-- metatag CSRF para fetch/AJAX --}}
@section('content_header')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <h1 class="m-0">Calendario de Horarios</h1>
@endsection

@section('content')
<div class="container-fluid">

  {{-- ============================
       Filtros (Grupo / Aula / Laboratorio)
       ============================ --}}
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-body">
          <form id="filtrosForm" class="row g-3 align-items-end">
            @csrf

            <div class="col-md-4">
              <label for="groupSelector" class="form-label">Seleccione un grupo:</label>
              <select id="groupSelector" name="id" class="form-control">
                <option value="">-- Seleccionar grupo --</option>
                @foreach(($grupos ?? []) as $grupo)
                  <option value="{{ $grupo->group_id }}"
                    {{ (isset($group_id) && $group_id == $grupo->group_id) ? 'selected' : '' }}>
                    {{ $grupo->group_name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-4">
              <label for="aulaSelector" class="form-label">Seleccione un aula:</label>
              <select id="aulaSelector" name="aula_id" class="form-control">
                <option value="">-- Seleccionar aula --</option>
                @foreach(($aulas ?? []) as $a)
                  <option value="{{ $a->classroom_assigned }}">{{ $a->aula_nombre }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-4">
              <label for="labSelector" class="form-label">Seleccione un laboratorio:</label>
              <select id="labSelector" name="lab_id" class="form-control">
                <option value="">-- Seleccionar laboratorio --</option>
                @foreach(($labs ?? []) as $l)
                  <option value="{{ $l->lab_id }}">{{ $l->lab_name }}</option>
                @endforeach
              </select>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- ============================
       Layout principal: Materias (izq) + Calendario (der)
       ============================ --}}
  <div class="row">
    {{-- Lista de materias arrastrables --}}
    <div class="col-lg-3">
      <div class="card card-outline card-primary h-100">
        <div class="card-header">
          <h3 class="card-title">Materias Disponibles</h3>
        </div>
        <div class="card-body">
          <div id="external-events">
            @php
              $materiasBlade = $materias ?? collect();
              $palette = ['#1f77b4','#2ca02c','#ff7f0e','#ffc107','#9467bd','#8c564b','#e377c2','#7f7f7f','#bcbd22','#17becf'];
            @endphp

            @forelse($materiasBlade as $index=>$mat)
              @php $color = $palette[$index % count($palette)]; @endphp
              <div class="external-event"
                   style="background-color: {{ $color }};"
                   data-event='@json(["title"=>$mat->subject_name,"subject_id"=>$mat->subject_id])'>
                {{ strtoupper($mat->subject_name) }}
              </div>
            @empty
              <p class="text-muted">Seleccione un grupo para ver las materias disponibles.</p>
            @endforelse

            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="drop-remove">
              <label class="form-check-label" for="drop-remove">Eliminar al arrastrar</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Calendario --}}
    <div class="col-lg-9">
      <div class="card card-primary card-outline">
        <div class="card-body p-0">
          <div id="calendar"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Flashes opcionales --}}
  @if(session('mensaje'))
    <div class="alert alert-{{ session('icono','info') }} mt-3">{{ session('mensaje') }}</div>
  @endif

</div>
@endsection

{{-- ============================
     CSS (AdminLTE + FullCalendar + estilos de tarjetas de materia)
     ============================ --}}
@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<style>
  /* ===== Igual que en tu PHP pero con un look más sólido ===== */
  #external-events .external-event{
    cursor:pointer;
    margin-bottom:12px;
    padding:10px 12px;
    color:#fff;
    border-radius:8px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.2px;
    box-shadow:0 1px 2px rgba(0,0,0,.08);
  }
  .fc .fc-timegrid-event { font-size: 11px; color:#fff !important; }
  .fc .fc-toolbar-title { font-size: 1.1rem; }
</style>
@endsection

{{-- ============================
     JS (jQuery, jQuery UI, SweetAlert, FullCalendar) + lógica DnD
     ============================ --}}
@section('js')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-ui-dist/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js"></script>

<script>
  // ============================
  // Config inicial
  // ============================
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const INI = {
    groupId : @json($group_id ?? request('id') ?? null),
    labs    : @json($labs ?? []),
    aulas   : @json($aulas ?? []),
    materias: @json($materias ?? []),
  };

  // Permisos UI (backend ya tiene middleware, esto es solo UX)
  const CAN_CREAR  = @can('crear horario laboratorio') true @else false @endcan;
  const CAN_EDITAR = @can('editar horario laboratorio') true @else false @endcan;
  const CAN_BORRAR = @can('borrar horario laboratorio') true @else false @endcan;

  // Rutas Laravel (names exactos)
  const RUTAS = {
    crear   : @json(route('horarios.manual.ajax.crear')),
    mover   : @json(route('horarios.manual.ajax.mover')),
    borrar  : @json(route('horarios.manual.ajax.borrar')),
    opciones: @json(route('horarios.manual.ajax.opciones')),
    espacio : @json(route('horarios.manual.ajax.eventos-espacio')),
  };

  const COLOR_PALETTE = ['#1f77b4','#2ca02c','#ff7f0e','#ffc107','#9467bd','#8c564b','#e377c2','#7f7f7f','#bcbd22','#17becf'];
  const DAYS_MAP = { 'lunes':1,'martes':2,'miércoles':3,'miercoles':3,'jueves':4,'viernes':5,'sábado':6,'sabado':6,'domingo':7 };

  // ============================
  // Helpers
  // ============================
  const CAL_TZ = 'America/Mexico_City';
  function pad(n){ return (n<10?'0':'')+n; }
  function normalizaDiaEs(s){ return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }
  function timeFromFc(str){ return (str || '').slice(11, 19); }
  function dowFromFc(startStr){
    const fmt = new Intl.DateTimeFormat('es-MX', { weekday:'long', timeZone: CAL_TZ });
    return fmt.format(new Date(startStr));
  }
  function ymdLocal(d){ const y=d.getFullYear(), m=pad(d.getMonth()+1), da=pad(d.getDate()); return `${y}-${m}-${da}`; }
  function weekDateFor(isoDow){
    const today = new Date();
    const dow = today.getDay() === 0 ? 7 : today.getDay();
    const diff = isoDow - dow;
    const target = new Date(today);
    target.setDate(today.getDate() + diff);
    return ymdLocal(target);
  }
  function buildSubjectColorMap(mats){
    const m={}; (mats||[]).forEach((x,i)=> m[x.subject_id] = COLOR_PALETTE[i % COLOR_PALETTE.length]); return m;
  }
  async function fetchJson(url, opts={}){
    const headers = { 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json', ...(opts.headers||{}) };
    const res = await fetch(url, { credentials:'same-origin', ...opts, headers });
    return res.json();
  }
  function buildFCEvents(rows, groupId, colorMap){
    return rows.map(r=>{
      const num = DAYS_MAP[ normalizaDiaEs(r.schedule_day) ]; if(!num) return null;
      const ymd = weekDateFor(num);
      const start = `${ymd}T${r.start_time}`;
      const end   = `${ymd}T${r.end_time}`;
      const own = groupId && parseInt(r.group_id)===parseInt(groupId);
      const color = own ? (colorMap[r.subject_id] || '#4F4F4F') : '#4F4F4F';
      return {
        title: `${r.subject_name} - Grupo ${r.group_name}`,
        start, end, color, textColor:'#fff',
        editable: CAN_EDITAR,            // mover existente solo si puede editar
        durationEditable: false,         // sin resize
        extendedProps:{ assignment_id:r.assignment_id, group_id:r.group_id, subject_id:r.subject_id }
      };
    }).filter(Boolean);
  }
  async function recargarEventosDesdeBackend(){
    const materias = Array.from(document.querySelectorAll('#external-events .external-event'))
      .map(div => JSON.parse(div.getAttribute('data-event')));
    await cargarEventos({
      groupId: INI.groupId,
      aulaId : $('#aulaSelector').val() || null,
      labId  : $('#labSelector').val()  || null,
      materias
    });
  }

  // ============================
  // Draggables (materias)
  // ============================
  function iniExternalEvents(){
    // Si NO puede crear, no activamos draggable (se ve la lista, pero no arrastra)
    if (!CAN_CREAR) {
      $('#external-events .external-event').css('cursor','not-allowed');
      $('#drop-remove').prop('disabled', true);
      return;
    }

    $('#external-events .external-event').each(function () {
      const data = $(this).data('event');
      $(this).data('eventObject', { title: $.trim($(this).text()), subject_id: data.subject_id });
      $(this).draggable({ zIndex:1070, revert:true, revertDuration:0 });
    });

    new FullCalendar.Draggable(document.getElementById('external-events'),{
      itemSelector: '.external-event',
      eventData: (el)=>{
        const data = $(el).data('event');
        return {
          title: el.innerText.trim(),
          subject_id: data.subject_id,
          color: getComputedStyle(el).getPropertyValue('background-color'),
          textColor: getComputedStyle(el).getPropertyValue('color')
        };
      }
    });
  }

  // ============================
  // Cargar combos y materias según grupo
  // ============================
  async function cargarOpciones(groupId){
    if(!groupId){
      $('#aulaSelector').html('<option value="">-- Seleccionar aula --</option>');
      $('#labSelector').html('<option value="">-- Seleccionar laboratorio --</option>');
      $('#external-events').html('<p class="text-muted">Seleccione un grupo para ver las materias disponibles.</p><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="drop-remove"><label class="form-check-label" for="drop-remove">Eliminar al arrastrar</label></div>');
      iniExternalEvents();
      return { aulas:[], labs:[], materias:[] };
    }

    const data = await fetchJson(`${RUTAS.opciones}?group_id=${groupId}`);

    // aulas
    const aulas = data.aulas||[];
    $('#aulaSelector').html(['<option value="">-- Seleccionar aula --</option>']
      .concat(aulas.map(a=>`<option value="${a.classroom_assigned}">${a.aula_nombre}</option>`)).join(''));

    // labs
    const labs = data.labs||[];
    $('#labSelector').html(['<option value="">-- Seleccionar laboratorio --</option>']
      .concat(labs.map(l=>`<option value="${l.lab_id}">${l.lab_name}</option>`)).join(''));

    // materias
    const materias = data.materias||[];
    const $ext = $('#external-events').empty().append(`<p class="text-muted">Arrastra las materias al calendario para programarlas.</p>`);
    const map = buildSubjectColorMap(materias);
    materias.forEach((m,i)=>{
      $ext.append(`<div class="external-event" style="background-color:${map[m.subject_id]};"
                    data-event='${JSON.stringify({title:m.subject_name,subject_id:m.subject_id})}'>
                    ${m.subject_name.toUpperCase()}
                  </div>`);
    });
    $ext.append(`<div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="drop-remove"><label class="form-check-label" for="drop-remove">Eliminar al arrastrar</label></div>`);
    iniExternalEvents();

    return { aulas,labs,materias };
  }

  // ============================
  // Carga eventos por aula o lab
  // ============================
  async function cargarEventos({groupId, aulaId, labId, materias}){
    const params = new URLSearchParams();
    if (labId) params.set('lab_id', labId);
    else if (aulaId) params.set('aula_id', aulaId);
    else { calendar?.removeAllEvents(); return; }

    const rows = await fetchJson(`${RUTAS.espacio}?${params.toString()}`);
    const colorMap = buildSubjectColorMap(materias||[]);
    const events = buildFCEvents(rows, groupId, colorMap);
    calendar.removeAllEvents();
    calendar.addEventSource(events);
  }

  // ============================
  // FullCalendar init
  // ============================
  let calendar;
  $(function(){
    iniExternalEvents();

    const el = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(el, {
      initialView:'timeGridWeek',
      locale:'es',
      timeZone:'America/Mexico_City',

      // 🔐 Permisos correctos
      editable: CAN_EDITAR,            // mover existentes solo si puede editar
      eventStartEditable: CAN_EDITAR,  // drag
      eventDurationEditable: false,    // sin resize
      droppable: CAN_CREAR,            // permitir drop externo solo si puede crear

      // ✅ Deja crear desde la lista aunque no pueda editar; bloquea mover existentes si no puede editar
      eventAllow: function(dropInfo, draggedEvent) {
        const hasAssignment = !!(draggedEvent && draggedEvent.extendedProps && draggedEvent.extendedProps.assignment_id);
        return hasAssignment ? CAN_EDITAR : CAN_CREAR;
      },

      headerToolbar:{ left:'', center:'', right:'' },
      allDaySlot:false,
      slotMinTime:'07:00:00',
      slotMaxTime:'20:00:00',
      slotDuration:'00:30',
      hiddenDays:[0],

      // ===== drop de materia (crear) =====
      eventReceive: function(info){
        if(!INI.groupId){
          Swal.fire('Selecciona un grupo','Primero elige un grupo.','info');
          info.event.remove(); 
          return;
        }
        const aulaId = $('#aulaSelector').val() || null;
        const labId  = $('#labSelector').val() || null;
        const assignmentType = labId ? 'Laboratorio' : 'Aula';

        Swal.fire({
          title:'¿Guardar asignación?',
          text:`"${info.event.title}"`,
          icon:'question',
          showCancelButton:true,
          confirmButtonText:'Guardar',
          cancelButtonText:'Cancelar'
        }).then(async (r)=>{
          if(!r.isConfirmed){ info.event.remove(); return; }

          const payload = {
            subject_id  : info.event.extendedProps.subject_id,
            start_time  : timeFromFc(info.event.startStr),
            end_time    : info.event.endStr ? timeFromFc(info.event.endStr) : null,
            schedule_day: normalizaDiaEs(dowFromFc(info.event.startStr)),
            group_id    : INI.groupId,
            lab_id      : labId,
            aula_id     : aulaId,
            tipo_espacio: assignmentType
          };

          try{
            const resp = await fetchJson(RUTAS.crear,{
              method:'POST',
              headers:{'Content-Type':'application/json'},
              body:JSON.stringify(payload)
            });

            if(resp.status==='success'){
              if(resp.assignment_id) info.event.setExtendedProp('assignment_id', resp.assignment_id);
              await recargarEventosDesdeBackend();
              Swal.fire('Asignación guardada','Listo.','success');
            }else{
              Swal.fire('Error', resp.message || 'No se pudo guardar.','error');
              info.event.remove();
            }
          }catch(e){
            Swal.fire('Error','Fallo de conexión.','error');
            info.event.remove();
          }
        });
      },

      // ===== mover evento existente =====
      eventDrop: function(info){
        if(!CAN_EDITAR){ info.revert(); return; }

        const aulaId = $('#aulaSelector').val() || null;
        const labId  = $('#labSelector').val() || null;
        const assignmentType = labId ? 'Laboratorio' : 'Aula';

        Swal.fire({
          title:'¿Guardar cambios?',
          text:`"${info.event.title}"`,
          icon:'question',
          showCancelButton:true,
          confirmButtonText:'Guardar',
          cancelButtonText:'Cancelar'
        }).then(async (r)=>{
          if(!r.isConfirmed){ info.revert(); return; }

          const payload = {
            assignment_id: info.event.extendedProps.assignment_id,
            subject_id   : info.event.extendedProps.subject_id,
            start_time   : timeFromFc(info.event.startStr),
            end_time     : info.event.endStr ? timeFromFc(info.event.endStr) : null,
            schedule_day : normalizaDiaEs(dowFromFc(info.event.startStr)),
            group_id     : INI.groupId,
            lab_id       : labId,
            aula_id      : aulaId,
            tipo_espacio : assignmentType
          };

          try{
            const resp = await fetchJson(RUTAS.mover,{
              method:'POST',
              headers:{'Content-Type':'application/json'},
              body:JSON.stringify(payload)
            });

            if(resp.status==='success'){
              await recargarEventosDesdeBackend();
              Swal.fire('Asignación guardada','Cambios aplicados.','success');
            }else{
              Swal.fire('Error', resp.message || 'No se pudo guardar.','error');
              info.revert();
            }
          }catch(e){
            Swal.fire('Error','Fallo de conexión.','error');
            info.revert();
          }
        });
      },

      // ===== click para borrar =====
      eventClick: function(info){
        if(!CAN_BORRAR){ Swal.fire('Acceso restringido','No tienes permiso para eliminar.','info'); return; }
        Swal.fire({
          title:'¿Eliminar asignación?',
          text:`"${info.event.title}"`,
          icon:'warning',
          showCancelButton:true,
          confirmButtonText:'Eliminar',
          confirmButtonColor:'#E43636',
          cancelButtonText:'Cancelar'
        }).then(async (r)=>{
          if(!r.isConfirmed) return;
          try{
            const resp = await fetchJson(RUTAS.borrar,{
              method:'POST',
              headers:{'Content-Type':'application/json'},
              body: JSON.stringify({ assignment_id: info.event.extendedProps.assignment_id, group_id: INI.groupId })
            });
            if(resp.status==='success'){
              info.event.remove();
              Swal.fire('Eliminada','Listo.','success');
            }else{
              Swal.fire('Error', resp.message || 'No se pudo eliminar.','error');
            }
          }catch(e){
            Swal.fire('Error','Fallo de conexión.','error');
          }
        });
      }
    });

    calendar.render();

    // Carga inicial si viene group_id del backend
    (async ()=>{
      if(INI.groupId){
        const { materias } = await cargarOpciones(INI.groupId);
        const aulaSel = $('#aulaSelector').val() || null;
        const labSel  = $('#labSelector').val() || null;
        await cargarEventos({ groupId: INI.groupId, aulaId: aulaSel, labId: labSel, materias });
      }
    })();

    // Listeners de filtros
    $('#groupSelector').on('change', async function(){
      INI.groupId = this.value || null;
      $('#aulaSelector').val(''); $('#labSelector').val('');
      const { materias } = await cargarOpciones(INI.groupId);
      calendar.removeAllEvents();
    });

    $('#aulaSelector').on('change', async function(){
      $('#labSelector').val('');
      const aulaId = this.value || null;
      const materias = Array.from(document.querySelectorAll('#external-events .external-event'))
        .map(div => JSON.parse(div.getAttribute('data-event')));
      await cargarEventos({ groupId: INI.groupId, aulaId, labId:null, materias });
    });

    $('#labSelector').on('change', async function(){
      $('#aulaSelector').val('');
      const labId = this.value || null;
      const materias = Array.from(document.querySelectorAll('#external-events .external-event'))
        .map(div => JSON.parse(div.getAttribute('data-event')));
      await cargarEventos({ groupId: INI.groupId, aulaId:null, labId, materias });
    });
  });
</script>
@endsection
