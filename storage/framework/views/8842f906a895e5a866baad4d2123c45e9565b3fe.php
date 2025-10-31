<?php $__env->startSection('title', config('app.name').' | Inicio'); ?>

<?php $__env->startSection('content_header'); ?>
  <h1 class="text-center w-100"><?php echo e(config('app.name')); ?></h1>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <div class="row">
    
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ver grafico')): ?>
    <div class="col-md-5">
      <div class="card card-outline card-teal">
        <div class="card-header">
          <h3 class="card-title">Materias cubiertas</h3>
        </div>
        <div class="card-body">
          <canvas id="materiasChart"></canvas>
          <p class="mt-3 text-center">
            <strong>% Cubiertas:</strong> <?php echo e($porcentaje_cubiertas); ?>%<br>
            <strong>% No Cubiertas:</strong> <?php echo e($porcentaje_no_cubiertas); ?>%
          </p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ver tabla faltante')): ?>
    <div class="col-md-7">
      <div class="card card-outline card-warning">
        <div class="card-header">
          <h3 class="card-title">Grupos con materias sin profesor</h3>
        </div>
        <div class="card-body">
          <p class="text-center">
            <strong>Total de materias faltantes:</strong> <?php echo e($materias_no_cubiertas); ?>

          </p>

          <?php if($grupos_con_faltantes->count()): ?>
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
                  <?php $__currentLoopData = $grupos_con_faltantes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                      <td><?php echo e($g->group_name); ?></td>
                      <td><?php echo e($g->materias_faltantes ?: '—'); ?></td>
                      <td class="text-center"><?php echo e((int)$g->materias_no_cubiertas); ?></td>
                    </tr>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-center mb-0">Todos los grupos tienen sus materias asignadas a profesores.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->denies('ver grafico')): ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->denies('ver tabla faltante')): ?>
        <div class="col-12">
          <div class="alert alert-info mb-0">
            No tienes permisos para ver el tablero de inicio.
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>


<audio id="audioCelebracion" src="<?php echo e(asset('grunt.mp3')); ?>"></audio>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('css'); ?>
  
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('js'); ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // ====== Gráfico (si existe el canvas y el usuario tiene permiso) ======
  const canvas = document.getElementById('materiasChart');
  if (canvas) {
    const cubiertas   = <?php echo json_encode($materias_cubiertas, 15, 512) ?>;
    const noCubiertas = <?php echo json_encode($materias_no_cubiertas, 15, 512) ?>;
    const porc        = <?php echo json_encode($porcentaje_cubiertas, 15, 512) ?>;

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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('adminlte::page', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\wamp64\www\cargaHorariav2\resources\views/home.blade.php ENDPATH**/ ?>