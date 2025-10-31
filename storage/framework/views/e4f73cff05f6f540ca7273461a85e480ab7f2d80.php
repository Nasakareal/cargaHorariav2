

<?php $__env->startSection('title', 'Detalle de Profesor'); ?>

<?php $__env->startSection('content_header'); ?>
  <h1 class="text-center w-100">Detalle de profesor</h1>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <div class="card card-outline card-warning">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title">Información del profesor</h3>

          <div class="btn-group">
            <a href="<?php echo e(route('profesores.index')); ?>" class="btn btn-secondary btn-sm">
              <i class="fas fa-arrow-left"></i> Volver
            </a>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('asignar materias')): ?>
            <a href="<?php echo e(route('profesores.asignar-materias', $profesor->teacher_id ?? $profesor->id)); ?>"
               class="btn btn-warning btn-sm" title="Asignar materias">
              <i class="bi bi-journal-text"></i> Asignar materias
            </a>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('editar profesores')): ?>
            <a href="<?php echo e(route('profesores.edit', $profesor->teacher_id ?? $profesor->id)); ?>"
               class="btn btn-success btn-sm" title="Editar">
              <i class="fas fa-edit"></i> Editar
            </a>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('eliminar profesores')): ?>
            <form action="<?php echo e(route('profesores.destroy', $profesor->teacher_id ?? $profesor->id)); ?>"
                  method="POST" id="formEliminar-<?php echo e($profesor->teacher_id ?? $profesor->id); ?>" class="d-inline">
              <?php echo csrf_field(); ?>
              <?php echo method_field('DELETE'); ?>
              <button type="button" class="btn btn-danger btn-sm"
                      onclick="confirmarEliminar('<?php echo e($profesor->teacher_id ?? $profesor->id); ?>', this)">
                <i class="fas fa-trash"></i> Eliminar
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="card-body">
          <?php
            $id   = $profesor->teacher_id ?? $profesor->id ?? null;
            $name = $profesor->teacher_name ?? $profesor->nombres ?? '—';

            // Campos simples
            $clasificacion = $profesor->clasificacion ?? 'No asignado';
            $area          = $profesor->area ?? 'No asignado';
            $horas         = $profesor->hours ?? $horas_semanales ?? 0;

            // Materias / Programas / Grupos pueden venir como string "a, b, c" o arreglo/colección
            $toBadges = function ($val) {
              if (is_null($val) || $val === '') return [];
              if (is_string($val)) return array_filter(array_map('trim', explode(',', $val)));
              if ($val instanceof \Illuminate\Support\Collection) return $val->toArray();
              if (is_array($val)) return $val;
              return [];
            };

            $materias  = $toBadges($materias ?? ($profesor->materias ?? null));
            $programas = $toBadges($programas ?? ($profesor->programas ?? null));
            $grupos    = $toBadges($grupos ?? ($profesor->grupos ?? null));

            // Horarios: colección/array de objetos con day_of_week, start_time, end_time
            $horarios  = $horarios ?? ($profesor->horarios ?? collect());
            $diasMap = [
              'Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles',
              'Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo',
              // por si ya vienen en español:
              'Lunes'=>'Lunes','Martes'=>'Martes','Miércoles'=>'Miércoles','Jueves'=>'Jueves',
              'Viernes'=>'Viernes','Sábado'=>'Sábado','Domingo'=>'Domingo'
            ];
            $fmt = function($t){ return $t ? substr((string)$t,0,5) : '—'; };
          ?>

          <div class="row">
            <div class="col-md-6 mb-3">
              <strong>ID:</strong>
              <div>#<?php echo e($id); ?></div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Nombres:</strong>
              <div><?php echo e($name); ?></div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Clasificación:</strong>
              <div><?php echo e($clasificacion); ?></div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Área:</strong>
              <div><?php echo e($area); ?></div>
            </div>

            <div class="col-md-4 mb-3">
              <strong>Horas semanales:</strong>
              <div><?php echo e((int)$horas); ?></div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Materias:</strong>
              <div>
                <?php $__empty_1 = true; $__currentLoopData = $materias; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                  <span class="badge badge-warning"><?php echo e($m); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                  <span class="text-muted">Sin materias</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Programas:</strong>
              <div>
                <?php $__empty_1 = true; $__currentLoopData = $programas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                  <span class="badge badge-info"><?php echo e($p); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                  <span class="text-muted">Sin programas</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Grupos:</strong>
              <div>
                <?php $__empty_1 = true; $__currentLoopData = $grupos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                  <span class="badge badge-secondary"><?php echo e($g); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                  <span class="text-muted">Sin grupos</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-md-12 mb-3">
              <strong>Horarios disponibles:</strong>
              <div>
                <?php
                  $rows = collect($horarios)->map(function($h){
                    return (object)[
                      'day'  => $h->day_of_week ?? ($h['day_of_week'] ?? null),
                      'ini'  => $h->start_time  ?? ($h['start_time']  ?? null),
                      'fin'  => $h->end_time    ?? ($h['end_time']    ?? null),
                    ];
                  });
                ?>

                <?php if($rows->count()): ?>
                  <ul class="mb-0">
                    <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <li>
                        <?php echo e($diasMap[$h->day] ?? $h->day ?? '—'); ?>:
                        de <?php echo e($fmt($h->ini)); ?> a <?php echo e($fmt($h->fin)); ?>

                      </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </ul>
                <?php else: ?>
                  <span class="text-muted">Sin disponibilidad registrada</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Creación:</strong>
              <div><?php echo e(optional($profesor->fyh_creacion ?? null)->format('Y-m-d H:i')); ?></div>
            </div>

            <div class="col-md-6 mb-3">
              <strong>Última actualización:</strong>
              <div><?php echo e(optional($profesor->fyh_actualizacion ?? null)->format('Y-m-d H:i') ?? '—'); ?></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('js'); ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmarEliminar(id, btn){
  const form = document.getElementById('formEliminar-' + id);
  if(!form){ console.error('No existe formEliminar-', id); return; }
  btn.disabled = true;

  Swal.fire({
    title: 'Eliminar Profesor',
    text: '¿Desea eliminar este profesor?',
    icon: 'warning',
    showDenyButton: true,
    confirmButtonText: 'Eliminar',
    confirmButtonColor: '#E43636',
    denyButtonColor: '#007bff',
    denyButtonText: 'Cancelar',
    position: 'center'
  }).then((r)=>{
    if(r.isConfirmed){ form.submit(); }
    else { btn.disabled = false; }
  });
}
</script>

<?php if(session('success')): ?>
<script>
Swal.fire({ icon:'success', title:<?php echo json_encode(session('success'), 15, 512) ?>, position:'center', timer:2500, showConfirmButton:false });
</script>
<?php endif; ?>
<?php if(session('error')): ?>
<script>
Swal.fire({ icon:'error', title:'Ups', text:<?php echo json_encode(session('error'), 15, 512) ?>, position:'center' });
</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('adminlte::page', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\wamp64\www\cargaHorariav2\resources\views/profesores/show.blade.php ENDPATH**/ ?>