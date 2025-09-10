

<?php $__env->startSection('title', 'Editar Profesor'); ?>

<?php $__env->startSection('content_header'); ?>
<div style="font-size:12px;opacity:.6">BLD <?php echo e(now()); ?></div>

  <h1 class="text-center w-100">Edición de profesor</h1>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <div class="row">
    <div class="col-12">

      <?php if($errors->any()): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <li><?php echo e($error); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </ul>
        </div>
      <?php endif; ?>

        <?php if(session('success')): ?>
          <div class="alert alert-success"><?php echo e(session('success')); ?></div>
        <?php endif; ?>
        <?php if(session('warning')): ?>
          <div class="alert alert-warning"><?php echo e(session('warning')); ?></div>
        <?php endif; ?>
        <?php if(session('error')): ?>
          <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
        <?php endif; ?>


      <div class="card card-outline card-success">
        <div class="card-header">
          <h3 class="card-title">Actualiza los datos</h3>
        </div>

        <div class="card-body">
          <form id="editForm" action="<?php echo e(route('profesores.update', $profesor->teacher_id)); ?>" method="POST" autocomplete="off">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="row">
              
              <div class="col-md-4">
                <div class="form-group">
                  <label for="teacher_name">Nombres del profesor</label>
                  <input type="text" name="teacher_name" id="teacher_name" class="form-control"
                         value="<?php echo e(old('teacher_name', $profesor->teacher_name)); ?>" required>
                </div>
              </div>

              
              <div class="col-md-4">
                <div class="form-group">
                  <label for="clasificacion">Clasificación</label>
                  <?php
                    $clasRaw = old('clasificacion', $profesor->clasificacion);
                    // normaliza "DETERMINADO" a "PA Determinado"
                    $clas = (strcasecmp($clasRaw, 'DETERMINADO') === 0) ? 'PA Determinado' : $clasRaw;
                    $opciones = ['PTC','PA','TA','PA Determinado'];
                  ?>
                  <select name="clasificacion" id="clasificacion" class="form-control" required>
                    <?php $__currentLoopData = $opciones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <option value="<?php echo e($val); ?>" <?php echo e(strcasecmp($clas,$val)===0 ? 'selected' : ''); ?>><?php echo e($val); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </select>
                </div>
              </div>
            </div>

            
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  <label>Áreas</label>

                  <?php
                    $sel  = collect(old('areas', $areasAsignadas ?? []))
                              ->map(fn($v) => (string)$v)->all();
                    $cols = 3;
                  ?>

                  <div class="table-responsive">
                    <table class="table table-bordered">
                      <tbody>
                        <?php $__currentLoopData = $areas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <?php if($idx % $cols === 0): ?>
                            <tr>
                          <?php endif; ?>

                          <?php $inputId = 'area_'.md5((string)$area); ?>
                          <td>
                            <input type="checkbox"
                                   name="areas[]"
                                   id="<?php echo e($inputId); ?>"
                                   value="<?php echo e($area); ?>"
                                   <?php echo e(in_array((string)$area, $sel, true) ? 'checked' : ''); ?>>
                            <label for="<?php echo e($inputId); ?>"><?php echo e($area); ?></label>
                          </td>

                          <?php if($idx % $cols === 2 || $loop->last): ?>
                            <?php for($pad = ($idx % $cols) + 1; $pad < $cols && $loop->last; $pad++): ?>
                              <td></td>
                            <?php endfor; ?>
                            </tr>
                          <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                      </tbody>
                    </table>
                  </div>

                  <small class="text-muted">Selecciona una o más áreas (se asignarán automáticamente sus programas).</small>
                </div>
              </div>
            </div>

            
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  <label>Horarios Disponibles</label>
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th>Día</th>
                        <th>Hora de Inicio</th>
                        <th>Hora de Fin</th>
                        <th>Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="horarios_table">
                      <?php
                        $dias      = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                        $oldDays   = old('day_of_week', []);
                        $oldStarts = old('start_time', []);
                        $oldEnds   = old('end_time', []);
                      ?>

                      
                      <?php if(count($oldDays)): ?>
                        <?php $__currentLoopData = $oldDays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <tr>
                            <td>
                              <select name="day_of_week[]" class="form-control">
                                <?php $__currentLoopData = $dias; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                  <option value="<?php echo e($d); ?>" <?php echo e($day === $d ? 'selected' : ''); ?>><?php echo e($d); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                              </select>
                            </td>
                            <td>
                              <?php $selStart = $oldStarts[$i] ?? null; ?>
                              <select name="start_time[]" class="form-control">
                                <?php for($h=7; $h<=22; $h++): ?>
                                  <?php $t = sprintf('%02d:00', $h); ?>
                                  <option value="<?php echo e($t); ?>" <?php echo e($selStart === $t ? 'selected' : ''); ?>><?php echo e($t); ?></option>
                                <?php endfor; ?>
                              </select>
                            </td>
                            <td>
                              <?php $selEnd = $oldEnds[$i] ?? null; ?>
                              <select name="end_time[]" class="form-control">
                                <?php for($h=7; $h<=22; $h++): ?>
                                  <?php $t = sprintf('%02d:00', $h); ?>
                                  <option value="<?php echo e($t); ?>" <?php echo e($selEnd === $t ? 'selected' : ''); ?>><?php echo e($t); ?></option>
                                <?php endfor; ?>
                              </select>
                            </td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-row">Eliminar</button></td>
                          </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                      
                      <?php elseif(($horarios ?? collect())->count()): ?>
                        <?php $__currentLoopData = $horarios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <?php
                            $selStart = substr($h->start_time, 0, 5);
                            $selEnd   = substr($h->end_time, 0, 5);
                          ?>
                          <tr>
                            <td>
                              <select name="day_of_week[]" class="form-control">
                                <?php $__currentLoopData = $dias; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                  <option value="<?php echo e($d); ?>" <?php echo e($h->day_of_week === $d ? 'selected' : ''); ?>><?php echo e($d); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                              </select>
                            </td>
                            <td>
                              <select name="start_time[]" class="form-control">
                                <?php for($hh=7; $hh<=22; $hh++): ?>
                                  <?php $tt = sprintf('%02d:00', $hh); ?>
                                  <option value="<?php echo e($tt); ?>" <?php echo e($selStart === $tt ? 'selected' : ''); ?>><?php echo e($tt); ?></option>
                                <?php endfor; ?>
                              </select>
                            </td>
                            <td>
                              <select name="end_time[]" class="form-control">
                                <?php for($hh=7; $hh<=22; $hh++): ?>
                                  <?php $tt = sprintf('%02d:00', $hh); ?>
                                  <option value="<?php echo e($tt); ?>" <?php echo e($selEnd === $tt ? 'selected' : ''); ?>><?php echo e($tt); ?></option>
                                <?php endfor; ?>
                              </select>
                            </td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-row">Eliminar</button></td>
                          </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                      
                      <?php else: ?>
                        <tr>
                          <td>
                            <select name="day_of_week[]" class="form-control">
                              <?php $__currentLoopData = $dias; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($d); ?>"><?php echo e($d); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                          </td>
                          <td>
                            <select name="start_time[]" class="form-control">
                              <?php for($h=7; $h<=22; $h++): ?>
                                <?php $t = sprintf('%02d:00', $h); ?>
                                <option value="<?php echo e($t); ?>"><?php echo e($t); ?></option>
                              <?php endfor; ?>
                            </select>
                          </td>
                          <td>
                            <select name="end_time[]" class="form-control">
                              <?php for($h=7; $h<=22; $h++): ?>
                                <?php $t = sprintf('%02d:00', $h); ?>
                                <option value="<?php echo e($t); ?>"><?php echo e($t); ?></option>
                              <?php endfor; ?>
                            </select>
                          </td>
                          <td><button type="button" class="btn btn-danger btn-sm remove-row">Eliminar</button></td>
                        </tr>
                      <?php endif; ?>
                    </tbody>

                  </table>
                  <button type="button" id="addHorario" class="btn btn-success btn-sm">Agregar Horario</button>
                </div>
              </div>
            </div>

            <hr>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <button type="submit" class="btn btn-success">Guardar cambios</button>
                  <a href="<?php echo e(route('profesores.index')); ?>" class="btn btn-secondary">Cancelar</a>
                </div>
              </div>
            </div>

          </form>
        </div>
      </div>

    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('js'); ?>
<script>
(function(){
  function timeSelect(name, selected){
    let html = `<select name="${name}" class="form-control">`;
    for(let h=7; h<=22; h++){
      const t = (h<10? '0'+h:h)+':00';
      html += `<option value="${t}" ${selected===t?'selected':''}>${t}</option>`;
    }
    html += `</select>`;
    return html;
  }

  const dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
  const tbody = document.getElementById('horarios_table');
  document.getElementById('addHorario').addEventListener('click', function(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select name="day_of_week[]" class="form-control">
          ${dias.map(d=>`<option value="${d}">${d}</option>`).join('')}
        </select>
      </td>
      <td>${timeSelect('start_time[]')}</td>
      <td>${timeSelect('end_time[]')}</td>
      <td><button type="button" class="btn btn-danger btn-sm remove-row">Eliminar</button></td>
    `;
    tbody.appendChild(tr);
  });

  tbody.addEventListener('click', function(e){
    if(e.target.classList.contains('remove-row')){
      e.target.closest('tr').remove();
    }
  });
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('adminlte::page', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\wamp64\www\cargaHorariav2\resources\views/profesores/edit.blade.php ENDPATH**/ ?>