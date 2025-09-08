@extends('adminlte::page')

@section('title', 'Horarios de Salones/Laboratorios')

@section('content_header')
  <h1 class="text-center w-100">Listado de Horarios de Salones y Laboratorios</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Espacios registrados</h3>
        </div>

        <div class="card-body">

          {{-- Filtros --}}
          <form method="GET" action="{{ route('horarios.salones.index') }}" class="mb-3">
            @php
              $qOld   = $filtros['q']   ?? request('q', '');
              $tipoOld= $filtros['tipo']?? request('tipo', '');
            @endphp
            <div class="row g-2">
              <div class="col-md-6">
                <input type="text" name="q" value="{{ $qOld }}" class="form-control"
                       placeholder="Buscar espacio (nombre de aula o laboratorio)…">
              </div>
              <div class="col-md-3">
                <select name="tipo" class="form-control">
                  <option value="">— Todos —</option>
                  <option value="aula" {{ $tipoOld === 'aula' ? 'selected' : '' }}>Solo aulas</option>
                  <option value="lab"  {{ $tipoOld === 'lab'  ? 'selected' : '' }}>Solo laboratorios</option>
                </select>
              </div>
              <div class="col-md-3 text-end">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="{{ route('horarios.salones.index') }}" class="btn btn-outline-secondary">
                  Limpiar
                </a>
              </div>
            </div>
          </form>

          <table id="tablaEspacios" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th class="text-center">Nombre del espacio</th>
                <th class="text-center">Tipo</th>
                <th>Grupos asignados</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($espacios as $i => $e)
                @php
                  $id     = $e->id ?? $e->classroom_id ?? $e->lab_id ?? null;
                  $nombre = $e->nombre ?? $e->classroom_name ?? $e->lab_name ?? '—';
                  $tipo   = $e->tipo ?? (isset($e->classroom_id) ? 'aula' : 'lab');

                  // Campos nuevos que vienen del controlador
                  $grupos  = $e->grupos ?? '';
                  $nGrupos = isset($e->n_grupos)
                              ? (int)$e->n_grupos
                              : (trim($grupos) === '' ? 0 : count(explode(',', $grupos)));
                @endphp

                <tr>
                  <td class="text-center">{{ is_int($i) ? $i + 1 : $loop->iteration }}</td>

                  <td class="text-center">{{ $nombre }}</td>

                  <td class="text-center">
                    @if ($tipo === 'aula')
                      <span class="badge bg-primary">Aula</span>
                    @else
                      <span class="badge bg-warning text-dark">Laboratorio</span>
                    @endif
                  </td>

                  <td class="text-center">
                    @if ($nGrupos === 0)
                      <span class="badge bg-secondary">Sin asignar</span>
                    @else
                      <div class="text-truncate d-inline-block" style="max-width: 360px;" title="{{ $grupos }}">
                        {{ $grupos }}
                      </div>
                      @if ($nGrupos > 1)
                        <span class="badge bg-info ms-1">{{ $nGrupos }} grupos</span>
                      @endif
                    @endif
                  </td>

                  <td class="text-center">
                    <div class="btn-group" role="group">
                      <a href="{{ route('horarios.salones.show', ['tipo'=>$tipo,'espacio_id'=>$id]) }}"
                         class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i> Ver
                      </a>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>

          {{-- Paginación del servidor (si algún día usas paginate()) --}}
          @if ($espacios instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="mt-3">
              {{ $espacios->withQueryString()->links() }}
            </div>
          @endif

        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
  const dt = $("#tablaEspacios").DataTable({
    pageLength: 10,
    language: {
      emptyTable: "No hay información",
      info: "Mostrando _START_ a _END_ de _TOTAL_ Espacios",
      infoEmpty: "Mostrando 0 a 0 de 0 Espacios",
      infoFiltered: "(Filtrado de _MAX_ total Espacios)",
      lengthMenu: "Mostrar _MENU_ Espacios",
      search: "Buscador:",
      zeroRecords: "Sin resultados encontrados",
      paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" }
    },
    responsive: true, lengthChange: true, autoWidth: false,
    buttons: [
      { extend:'collection', text:'Opciones', orientation:'landscape', buttons:['copy','pdf','csv','excel','print'] },
      { extend:'colvis', text:'Visor de columnas', collectionLayout:'fixed three-column' }
    ]
  });
  dt.buttons().container().appendTo('#tablaEspacios_wrapper .col-md-6:eq(0)');
});
</script>

{{-- Flashes --}}
@if (session('success'))
<script>
Swal.fire({ icon:'success', title:@json(session('success')), showConfirmButton:false, timer:6500, timerProgressBar:true, position:'center' });
</script>
@endif
@if (session('error'))
<script>
Swal.fire({ icon:'error', title:'Ups', text:@json(session('error')), confirmButtonColor:'#E43636', position:'center' });
</script>
@endif
@if ($errors->any())
<script>
Swal.fire({ icon:'warning', title:'Revisa los datos', html:`{!! implode('<br>', $errors->all()) !!}`, position:'center' });
</script>
@endif
@endsection
