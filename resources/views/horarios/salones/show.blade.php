@extends('adminlte::page')

@section('title', 'Horario del Espacio')

@section('content_header')
  <h1 class="text-center w-100">Horario del Espacio</h1>
@endsection

@section('content')
<div class="container-xl">

  {{-- Selector de espacio (aula/lab) --}}
  <div class="row mb-3">
    <div class="col-md-8">
      <label for="espacioSelector" class="form-label">Seleccione un espacio:</label>
      <select id="espacioSelector" class="form-control">
        <option value="">— Seleccionar —</option>
        @foreach ($espacios as $e)
          @php
            $optId   = $e->id ?? $e->classroom_id ?? $e->lab_id ?? null;
            $optTipo = $e->tipo ?? (isset($e->classroom_id) ? 'aula' : 'lab');
            $optNom  = $e->nombre ?? $e->classroom_name ?? $e->lab_name ?? '—';
            $isSel   = ($optTipo === $tipo) && ((string)$optId === (string)$espacio->id);
          @endphp
          <option value="{{ $optTipo }}:{{ $optId }}" {{ $isSel ? 'selected' : '' }}>
            [{{ strtoupper($optTipo) }}] {{ $optNom }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4 d-flex align-items-end justify-content-end">
      <a href="{{ route('horarios.salones.index') }}" class="btn btn-secondary">
        Volver
      </a>
    </div>
  </div>

  {{-- Encabezado --}}
  <div class="row mb-2">
    <div class="col-12">
      <h4 class="mb-0">
        Espacio:
        <strong>{{ $espacio->nombre ?? '—' }}</strong>
        <span class="ms-2">
          (Tipo:
          @if ($tipo === 'aula')
            <span class="badge bg-primary">Aula</span>
          @else
            <span class="badge bg-warning text-dark">Laboratorio</span>
          @endif
          )
        </span>
      </h4>
    </div>
  </div>

  {{-- Tabla de horario --}}
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
                      $contenido     = $tabla[$hLabel][$dia] ?? '';
                      $esSinProfesor = str_contains($contenido, 'Sin profesor');
                      $clase         = $esSinProfesor ? 'table-warning' : '';
                    @endphp
                    <td class="{{ $clase }}">
                      {!! $contenido !== '' ? $contenido : '&nbsp;' !!}
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
(function(){
  const sel = document.getElementById('espacioSelector');
  const baseUrl = @json(url('horarios/salones'));
  sel?.addEventListener('change', function(){
    if (!this.value) return;
    const [tipo, id] = this.value.split(':');
    if (tipo && id) window.location.href = `${baseUrl}/${tipo}/${id}`;
  });

  const nombre = @json(($espacio->nombre ?? 'espacio').' ('.strtoupper($tipo).')');
  const titulo = `Horario del Espacio: ${nombre}`;

  function limpiar(data) {
    if (typeof data !== 'string') return data;
    data = data.replace(/<br\s*\/?>/gi, '\n').replace(/\u00A0/g, ' ');
    const tmp = document.createElement('div'); tmp.innerHTML = data;
    return (tmp.textContent || tmp.innerText || '').replace(/[ \t]+\n/g, '\n').replace(/\s+/g,' ').trim();
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
      { extend: 'csvHtml5',   text: 'CSV',     filename: `Horario_${nombre}`, exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'excelHtml5', text: 'Excel',   filename: `Horario_${nombre}`, exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'pdfHtml5',   text: 'PDF',     filename: `Horario_${nombre}`, title: titulo, orientation: 'landscape', pageSize: 'LEGAL',
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
      { extend: 'print',      text: 'Imprimir', title: titulo,
        exportOptions: { columns: ':visible', stripHtml: true, format: { body: limpiar } } },
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' }
  });
})();
</script>
@endsection
