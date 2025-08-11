@extends('adminlte::page')

@section('title', 'Horarios de Grupos')

@section('content_header')
  <h1 class="text-center w-100">Listado de Horarios de Grupos</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title">Grupos registrados</h3>

          {{-- Botón Asignar Horario (solo si existe la ruta y el permiso) --}}
          @can('asignar horarios grupos')
            @if (Route::has('horarios.grupos.autoasignar'))
              <div class="card-tools">
              </div>
            @else
              <div class="card-tools">
                <a href="#" class="btn btn-secondary disabled" tabindex="-1" aria-disabled="true">
                  <i class="bi bi-arrow-repeat"></i> Asignar Horario
                </a>
              </div>
            @endif
          @endcan
        </div>

        <div class="card-body">

          {{-- Filtros --}}
          <form method="GET" action="{{ route('horarios.grupos.index') }}" class="mb-3">
            @php
              $qOld     = $filtros['q']     ?? request('q', '');
              $turnoOld = $filtros['turno'] ?? request('turno', '');
              $items    = ($grupos instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $grupos->items() : $grupos;
              $turnos   = collect($items)->pluck('turno')->filter()->unique()->values();
            @endphp
            <div class="row g-2">
              <div class="col-md-5">
                <input type="text" name="q" value="{{ $qOld }}" class="form-control" placeholder="Buscar grupo (nombre, grado, letra)…">
              </div>
              <div class="col-md-4">
                <select name="turno" class="form-control">
                  <option value="">— Todos los turnos —</option>
                  @foreach ($turnos as $t)
                    <option value="{{ $t }}" {{ $turnoOld === $t ? 'selected' : '' }}>{{ $t }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3 text-end">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="{{ route('horarios.grupos.index') }}" class="btn btn-outline-secondary">
                  Limpiar
                </a>
              </div>
            </div>
          </form>

          <table id="tablaGrupos" class="table table-striped table-bordered table-hover table-sm">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th class="text-center">Nombre del Grupo</th>
                <th class="text-center">Turno</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($grupos as $i => $g)
                @php
                  $id     = $g->group_id ?? $g->id ?? $g->id_grupo ?? null;
                  $nombre = $g->group_name ?? $g->nombre ?? $g->name ?? $g->grupo ?? '—';
                  $turno  = $g->turno ?? $g->shift_name ?? $g->nombre_turno ?? '—';
                @endphp
                <tr>
                  <td class="text-center">{{ is_int($i) ? $i + 1 : $loop->iteration }}</td>
                  <td class="text-center">{{ $nombre }}</td>
                  <td class="text-center">{{ $turno }}</td>
                  <td class="text-center">
                    <div class="btn-group" role="group">
                      <a href="{{ route('horarios.grupos.show', $id) }}" class="btn btn-info btn-sm" title="Ver">
                        <i class="bi bi-eye"></i> Ver
                      </a>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>

          {{-- Paginación del servidor (opcional, si usas paginate()) --}}
          @if ($grupos instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="mt-3">
              {{ $grupos->withQueryString()->links() }}
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
  const dt = $("#tablaGrupos").DataTable({
    pageLength: 10,
    language: {
      emptyTable: "No hay información",
      info: "Mostrando _START_ a _END_ de _TOTAL_ Grupos",
      infoEmpty: "Mostrando 0 a 0 de 0 Grupos",
      infoFiltered: "(Filtrado de _MAX_ total Grupos)",
      lengthMenu: "Mostrar _MENU_ Grupos",
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
  dt.buttons().container().appendTo('#tablaGrupos_wrapper .col-md-6:eq(0)');
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
