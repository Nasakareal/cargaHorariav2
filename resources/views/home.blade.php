@extends('adminlte::page')

@section('title', config('app.name').' | Inicio')

@section('content_header')
  <h1 class="text-center w-100">{{ config('app.name') }}</h1>
@endsection

@section('content')
<div class="container-xl">
  <div class="row">
    {{-- ======= Gráfico (permiso) ======= --}}
    @can('ver grafico')
    <div class="col-md-5">
      <div class="card card-outline card-teal">
        <div class="card-header">
          <h3 class="card-title">Materias cubiertas</h3>
        </div>
        <div class="card-body">
          <canvas id="materiasChart"></canvas>
          <p class="mt-3 text-center">
            <strong>% Cubiertas:</strong> {{ $porcentaje_cubiertas }}%<br>
            <strong>% No Cubiertas:</strong> {{ $porcentaje_no_cubiertas }}%
          </p>
        </div>
      </div>
    </div>
    @endcan

    {{-- ======= Tabla de faltantes (permiso) ======= --}}
    @can('ver tabla faltante')
    <div class="col-md-7">
      <div class="card card-outline card-warning">
        <div class="card-header">
          <h3 class="card-title">Grupos con materias sin profesor</h3>
        </div>
        <div class="card-body">
          <p class="text-center">
            <strong>Total de materias faltantes:</strong> {{ $materias_no_cubiertas }}
          </p>

          @if($grupos_con_faltantes->count())
            <div class="table-responsive">
              <table id="listadoMaterias" class="table table-striped table-bordered table-hover table-sm">
                <thead>
                  <tr>
                    <th>Grupo</th>
                    <th>Materias sin profesor</th>
                    <th class="text-center"># Faltantes</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($grupos_con_faltantes as $g)
                    <tr>
                      <td>{{ $g->group_name }}</td>
                      <td>{{ $g->materias_faltantes ?: '—' }}</td>
                      <td class="text-center">{{ (int)$g->materias_no_cubiertas }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <p class="text-center mb-0">Todos los grupos tienen sus materias asignadas a profesores.</p>
          @endif
        </div>
      </div>
    </div>
    @endcan

    {{-- Si no tiene ninguno de los permisos, un mensajito --}}
    @cannot('ver grafico')
      @cannot('ver tabla faltante')
        <div class="col-12">
          <div class="alert alert-info mb-0">
            No tienes permisos para ver el tablero de inicio.
          </div>
        </div>
      @endcannot
    @endcannot
  </div>
</div>

{{-- Audio de celebración (en public/grunt.mp3) --}}
<audio id="audioCelebracion" src="{{ asset('grunt.mp3') }}"></audio>
@endsection

@section('css')
  {{-- DataTables (si AdminLTE no los auto-incluye, déjalos) --}}
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
@endsection

@section('js')
{{-- Chart + Confetti --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

{{-- jQuery + DataTables (core + bs4) --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // ====== Gráfico (si existe el canvas y el usuario tiene permiso) ======
  const canvas = document.getElementById('materiasChart');
  if (canvas) {
    const cubiertas   = @json($materias_cubiertas);
    const noCubiertas = @json($materias_no_cubiertas);
    const porc        = @json($porcentaje_cubiertas);

    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
      type: 'pie',
      data: {
        labels: ['Cubiertas', 'No cubiertas'],
        datasets: [{
          data: [cubiertas, noCubiertas],
          backgroundColor: ['#008080', '#A9A9A9']
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: function (context) {
                const label = context.label ? context.label + ': ' : '';
                return label + context.raw;
              }
            }
          }
        },
        animation: {
          onComplete: function(){
            if (porc === 100) lanzarConfeti();
          }
        }
      }
    });
  }

  function lanzarConfeti() {
    const duracion = 15000;
    const base = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 1000 };
    const audio = document.getElementById('audioCelebracion');

    try { audio && audio.play(); } catch(e){}

    const intervalo = setInterval(() => {
      confetti({ ...base, origin: { x: Math.random(), y: Math.random() }, angle: Math.random()*360 });
    }, 250);

    setTimeout(() => {
      clearInterval(intervalo);
      confetti.reset && confetti.reset();
      if (audio){ audio.pause(); audio.currentTime = 0; }
    }, duracion);
  }

  // ====== DataTable (si existe la tabla y el usuario tiene permiso) ======
  const tabla = $('#listadoMaterias');
  if (tabla.length) {
    tabla.DataTable({
      pageLength: 5,
      language: {
        emptyTable: "No hay grupos con materias sin profesor",
        info: "Mostrando _START_ a _END_ de _TOTAL_ grupos",
        infoEmpty: "Mostrando 0 a 0 de 0 grupos",
        infoFiltered: "(Filtrado de _MAX_ grupos en total)",
        lengthMenu: "Mostrar _MENU_ grupos",
        search: "Buscar:",
        zeroRecords: "Sin resultados encontrados",
        paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" }
      },
      responsive: true, lengthChange: true, autoWidth: false
    });
  }
});
</script>
@endsection
