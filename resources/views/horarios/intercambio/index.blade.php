{{-- ============================
     Intercambio de horarios (solo mover/borrar)
     ============================ --}}
@extends('adminlte::page')

@section('title', 'Intercambio de horarios')

@section('content_header')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <h1 class="m-0">Intercambio de horarios</h1>
@endsection

@section('content')
<div class="container-fluid">

  {{-- Filtro: SOLO grupo --}}
  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-primary">
        <div class="card-body">
          <form class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
              <label for="groupSelector" class="form-label">Seleccione un grupo:</label>
              <select id="groupSelector" class="form-control">
                <option value="">-- Seleccionar grupo --</option>
                @foreach(($grupos ?? []) as $g)
                  <option value="{{ $g->group_id }}"
                    {{ (isset($group_id) && $group_id == $g->group_id) ? 'selected' : '' }}>
                    {{ $g->group_name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-8">
              <span class="text-muted">Arrastra las clases del grupo para reprogramarlas. Click para borrar.</span>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Calendario único --}}
  <div class="row">
    <div class="col-12">
      <div class="card card-primary card-outline">
        <div class="card-body p-0">
          <div id="calendar"></div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<style>
  .fc .fc-timegrid-event { font-size: 11px; color:#fff !important; }
  .fc .fc-toolbar-title { font-size: 1.05rem; }
  /* altura uniforme por slot de 30min */
    .fc .fc-timegrid-slot { height: 36px; }  /* ajusta 32–42px a gusto */

    /* forzar scroll vertical interno del grid por si algún theme lo pisa */
    .fc .fc-scroller { overflow-y: auto !important; }

</style>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js"></script>

<script>
  // ============================
  // Config / Rutas
  // ============================
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  // OJO: si tus names NO llevan el prefijo "horarios.", cámbialos a "manual.ajax.grupo", "manual.ajax.mover", "manual.ajax.borrar"
    const RUTAS = {
      eventosGrupo : @json(route('horarios.intercambio.ajax.grupo', ['group_id' => '__ID__'])),
      mover        : @json(route('horarios.intercambio.mover')),
      borrar       : @json(route('horarios.intercambio.borrar')),
    };


  // ============================
  // Helpers
  // ============================
  const CAL_TZ = 'America/Mexico_City';
  const DAYS_MAP = { 'lunes':1,'martes':2,'miércoles':3,'miercoles':3,'jueves':4,'viernes':5,'sábado':6,'sabado':6,'domingo':7 };
  const PALETTE = ['#1f77b4','#2ca02c','#ff7f0e','#ffc107','#9467bd','#8c564b','#e377c2','#7f7f7f','#bcbd22','#17becf'];

  function pad(n){ return (n<10?'0':'')+n; }
  function normalizaDiaEs(s){ return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

  // lee "HH:mm:ss" de startStr/endStr (evita líos de TZ)
  function timeFromFc(str){ return (str || '').slice(11, 19); }

  function ymdLocal(d){
    const y = d.getFullYear(), m = pad(d.getMonth()+1), da = pad(d.getDate());
    return `${y}-${m}-${da}`;
  }
  function weekDateFor(isoDow){
    const today = new Date();
    const dow = today.getDay() === 0 ? 7 : today.getDay(); // 1..7
    const diff = isoDow - dow;
    const target = new Date(today);
    target.setDate(today.getDate() + diff);
    return ymdLocal(target);
  }

  async function fetchJson(url, opts={}){
    const headers = { 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json', ...(opts.headers||{}) };
    const res = await fetch(url, { credentials:'same-origin', ...opts, headers });
    return res.json();
  }

  // Color estable por subject_id
  function colorForSubject(id){
    const idx = Math.abs(parseInt(id,10) || 0) % PALETTE.length;
    return PALETTE[idx];
  }

    function buildFCEvents(rows){
      return rows.map(r=>{
        const num = DAYS_MAP[ normalizaDiaEs(r.schedule_day) ];
        if(!num) return null;
        const ymd = weekDateFor(num);
        const color = colorForSubject(r.subject_id);
        const labId = r.lab1_assigned ?? r.lab2_assigned ?? null;

        return {
          title: `${r.subject_name} - Grupo ${r.group_name}`,
          start: `${ymd}T${r.start_time}`,
          end  : `${ymd}T${r.end_time}`,
          backgroundColor: color,
          borderColor: color,
          textColor:'#fff',
          editable:true,
          extendedProps: {
            assignment_id: r.assignment_id,
            subject_id: r.subject_id,
            group_id: r.group_id,
            schedule_day: r.schedule_day,
            start_time: r.start_time,
            end_time: r.end_time,
            tipo_espacio: r.tipo_espacio,     // <---
            classroom_id: r.classroom_id,     // <---
            lab_id: labId                      // <---
          }
        };
      }).filter(Boolean);
    }


  async function cargarEventosGrupo(groupId){
    if(!groupId){ calendar?.removeAllEvents(); return; }
    const url = RUTAS.eventosGrupo.replace('__ID__', groupId);
    const rows = await fetchJson(url);
    const events = buildFCEvents(rows);
    calendar.removeAllEvents();
    calendar.addEventSource(events);
  }

  async function recargar(){
    const gid = document.getElementById('groupSelector').value || null;
    await cargarEventosGrupo(gid);
  }

  // ============================
  // Calendar
  // ============================
  let calendar;

  $(function(){
    const el = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(el, {
      initialView:'timeGridWeek',
      locale:'es',
      timeZone: CAL_TZ,
      editable: true,           // mover sí
      droppable: false,         // NO arrastrar materias nuevas
      headerToolbar:{ left:'', center:'', right:'' },
      allDaySlot:false,
      slotMinTime:'07:00:00',
      slotMaxTime:'20:00:00',
      slotDuration:'00:30',
      hiddenDays:[0],

      // mover evento existente
      eventDrop: async function(info){
        const isLab = (info.event.extendedProps.tipo_espacio === 'Laboratorio');

        const payload = {
          assignment_id: info.event.extendedProps.assignment_id,
          subject_id   : info.event.extendedProps.subject_id,
          start_time   : timeFromFc(info.event.startStr),
          end_time     : info.event.endStr ? timeFromFc(info.event.endStr) : null,
          schedule_day : new Intl.DateTimeFormat('es-MX',{weekday:'long', timeZone: CAL_TZ})
                           .format(new Date(info.event.startStr)),
          group_id     : info.event.extendedProps.group_id,
          tipo_espacio : info.event.extendedProps.tipo_espacio,
          lab_id       : isLab ? (info.event.extendedProps.lab_id || null) : null,
          aula_id      : isLab ? null : (info.event.extendedProps.classroom_id || null),
        };


        try{
          const resp = await fetchJson(RUTAS.mover,{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
          });
          if(resp.status==='success'){
            await recargar();
            Swal.fire('Guardado','Movimiento aplicado.','success');
          }else{
            Swal.fire('Error', resp.message || 'No se pudo guardar.','error');
            info.revert();
          }
        }catch(e){
          Swal.fire('Error','Fallo de conexión.','error');
          info.revert();
        }
      },

      // click para borrar
      eventClick: function(info){
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
              body: JSON.stringify({ assignment_id: info.event.extendedProps.assignment_id })
            });
            if(resp.status==='success'){
              await recargar();
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

    // carga inicial si viene group_id del backend
    (async ()=>{
      const pre = @json($group_id ?? null);
      if(pre){ $('#groupSelector').val(pre); await cargarEventosGrupo(pre); }
    })();

    // cambio de grupo
    $('#groupSelector').on('change', recargar);
  });
</script>
@endsection
