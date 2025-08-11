@extends('adminlte::page')

@section('title', 'Horario del Grupo')

@section('content_header')
  <h1 class="text-center w-100">Horario del Grupo</h1>
@endsection

@section('content')
@php
  // Helper local: ¿la hora (HH:MM) cae dentro de los rangos del día?
  $estaDentro = function(string $turno, string $dia, string $horaHHMM, array $dispTurno): bool {
      if (!isset($dispTurno[$dia])) return false;
      $hora = \Carbon\Carbon::createFromFormat('H:i:s', $horaHHMM.':00');
      $slots = isset($dispTurno[$dia]['start']) ? [$dispTurno[$dia]] : $dispTurno[$dia];
      foreach ($slots as $r) {
          $ini = \Carbon\Carbon::createFromFormat('H:i:s', $r['start']);
          $fin = \Carbon\Carbon::createFromFormat('H:i:s', $r['end']);
          if ($hora >= $ini && $hora < $fin) return true;
      }
      return false;
  };
@endphp

<div class="container-xl">
  <div class="row mb-3">
    <div class="col-md-6">
      <form method="GET" action="{{ route('horarios.grupos.show', $grupo->group_id) }}" onsubmit="location.href=this.action.replace('{{ $grupo->group_id }}', document.getElementById('groupSelector').value);return false;">
        <label for="groupSelector" class="form-label">Seleccione un grupo:</label>
        <select id="groupSelector" class="form-control" onchange="location.href='{{ route('horarios.grupos.show', 0) }}'.replace('/0','/'+this.value)">
          <option value="">— Seleccionar grupo —</option>
          @foreach ($grupos as $g)
            <option value="{{ $g->group_id }}" {{ $g->group_id == $grupo->group_id ? 'selected' : '' }}>
              {{ $g->group_name }}
            </option>
          @endforeach
        </select>
      </form>
    </div>
    <div class="col-md-6 d-flex align-items-end justify-content-end">
      <a href="{{ route('horarios.grupos.index') }}" class="btn btn-secondary">
        Volver
      </a>
    </div>
  </div>

  <div class="row mb-2">
    <div class="col-12">
      <h4 class="mb-0">
        Grupo: <strong>{{ $grupo->group_name }}</strong>
        <span class="ms-2"> (Turno: <strong>{{ $turno }}</strong>)</span>
      </h4>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <div class="card card-outline card-info">
        <div class="card-header">
          <h3 class="card-title">Detalles del horario</h3>
        </div>
        <div class="card-body table-responsive">
          <table id="tablaHorario" class="table table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th>Hora/Día</th>
                @foreach ($dias as $dia)
                  <th class="text-center">{{ $dia }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach ($horas as $hLabel)
                @php
                  // "07:00 - 08:00" -> "07:00"
                  $horaInicio = substr($hLabel, 0, 5);
                @endphp
                <tr>
                  <td>{{ $hLabel }}</td>
                  @foreach ($dias as $dia)
                    @php
                      $contenido      = $tabla[$hLabel][$dia] ?? '';
                      $esSinProfesor  = str_contains($contenido, 'Sin profesor');
                      $amarillo       = $esSinProfesor ? 'table-warning' : '';
                      $vacio          = trim($contenido) === '';
                      $dentro         = $estaDentro($turno, $dia, $horaInicio, $dispTurno);
                      $rojo           = (!$dentro && $vacio) ? 'table-danger' : '';
                      $clase          = trim($amarillo.' '.$rojo);
                    @endphp
                    <td class="{{ $clase }}">
                      {!! $contenido ?: '&nbsp;' !!}
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
@endsection


@section('js')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(function () {
  const nombreGrupo = @json($grupo->group_name ?? 'grupo');
  const titulo = `Horario del Grupo: ${nombreGrupo}`;

  function limpiar(data) {
    if (typeof data !== 'string') return data;
    // <br> -> salto de línea, quitar &nbsp; y etiquetas
    data = data.replace(/<br\s*\/?>/gi, '\n').replace(/\u00A0/g, ' ');
    return $('<div>').html(data).text().replace(/[ \t]+\n/g, '\n').replace(/\s+/g,' ').trim();
  }

  $('#tablaHorario').DataTable({
    paging: false,
    searching: false,
    info: false,
    ordering: false,
    autoWidth: false,
    dom: 'Bfrtip',
    buttons: [
      { extend: 'copyHtml5',  text: 'Copiar',  exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'csvHtml5',   text: 'CSV',     filename: `Horario_${nombreGrupo}`, exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'excelHtml5', text: 'Excel',   filename: `Horario_${nombreGrupo}`, exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'pdfHtml5',   text: 'PDF',     filename: `Horario_${nombreGrupo}`, title: titulo, orientation: 'landscape', pageSize: 'LEGAL',
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'print',      text: 'Imprimir', title: titulo,
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' }
  });
});
</script>
@endsection

