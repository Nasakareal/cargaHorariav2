@extends('adminlte::page')

@section('title', 'Registro de Actividad')

@section('content_header')
  <h1 class="text-center w-100">Registro de actividad</h1>
@endsection

@section('content')
<div class="card card-outline card-info">
  <div class="card-header">
    <h3 class="card-title">Movimientos recientes</h3>
  </div>
  <div class="card-body">
    <table id="tablaActividad" class="table table-striped table-bordered table-hover table-sm">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Usuario</th>
          <th>Acción</th>
          <th>Tabla</th>
          <th>Registro</th>
          <th>Descripción</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($actividades as $a)
          <tr>
            <td>{{ \Carbon\Carbon::parse($a->fecha)->format('Y-m-d H:i') }}</td>
            <td>{{ $a->nombre_usuario }}</td>
            <td><span class="badge badge-info">{{ $a->accion }}</span></td>
            <td>{{ $a->tabla }}</td>
            <td>{{ $a->registro_id }}</td>
            <td>{{ $a->descripcion }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('js')
<script>
$(function () {
  $("#tablaActividad").DataTable({
    pageLength: 10,
    order: [[0, 'desc']],
    language: {
      url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
    }
  });
});
</script>
@endpush
