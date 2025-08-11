@extends('adminlte::page')

@section('title', 'Horario del Profesor')

@section('content_header')
  <h1 class="text-center w-100">Horario del Profesor</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row mb-3">
    <div class="col-md-6">
      <form method="GET"
            action="{{ route('horarios.profesores.show', $profesor->teacher_id) }}"
            onsubmit="location.href=this.action.replace('{{ $profesor->teacher_id }}', document.getElementById('teacherSelector').value);return false;">
        <label for="teacherSelector" class="form-label">Seleccione un profesor:</label>
        <select id="teacherSelector" class="form-control"
                onchange="location.href='{{ route('horarios.profesores.show', 0) }}'.replace('/0','/'+this.value)">
          <option value="">— Seleccionar profesor —</option>
          @foreach ($profesores as $p)
            <option value="{{ $p->teacher_id }}" {{ $p->teacher_id == $profesor->teacher_id ? 'selected' : '' }}>
              {{ $p->docente }}
            </option>
          @endforeach
        </select>
      </form>
    </div>
    <div class="col-md-6 d-flex align-items-end justify-content-end">
      <a href="{{ route('horarios.profesores.index') }}" class="btn btn-secondary">
        Volver
      </a>
    </div>
  </div>

  <div class="row mb-2">
    <div class="col-12">
      <h4 class="mb-0">
        Profesor: <strong>{{ $profesor->docente }}</strong>
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
                <tr>
                  <td>{{ $hLabel }}</td>
                  @foreach ($dias as $dia)
                    @php
                      $contenido = $tabla[$hLabel][$dia] ?? '';
                    @endphp
                    <td>{!! $contenido ?: '&nbsp;' !!}</td>
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
  const nombreProfesor = @json($profesor->docente ?? 'profesor');
  const titulo = `Horario del Profesor: ${nombreProfesor}`;

  function limpiar(data) {
    if (typeof data !== 'string') return data;
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
      { extend: 'copyHtml5',  text: 'Copiar',
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'csvHtml5',   text: 'CSV',     filename: `Horario_${nombreProfesor}`,
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'excelHtml5', text: 'Excel',   filename: `Horario_${nombreProfesor}`,
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'pdfHtml5',   text: 'PDF',     filename: `Horario_${nombreProfesor}`, title: titulo, orientation: 'landscape', pageSize: 'LEGAL',
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'print',      text: 'Imprimir', title: titulo,
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' }
  });
});
</script>
@endsection
