

<?php $__env->startSection('title', 'Login | Sistema de Carga Horaria'); ?>

<?php $__env->startSection('adminlte_css'); ?>
    <link rel="icon" href="<?php echo e(asset('UTM.png')); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo e(asset('UTM.png')); ?>">
<?php $__env->stopSection(); ?>


<?php $__env->startSection('auth_header'); ?>
    <center>
        <img src="<?php echo e(asset('logo_2025.png')); ?>" width="340px" alt="UTM Logo"><br><br>
    </center>
    <h3><b>Sistema de Carga Horaria</b></h3>
    <p class="login-box-msg">Inicio de sesión</p>
    <hr>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('auth_body'); ?>
    <form action="<?php echo e(route('login')); ?>" method="post">
        <?php echo csrf_field(); ?>
        <div class="input-group mb-3">
            <input type="email" name="email" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   value="<?php echo e(old('email')); ?>" placeholder="Email" required autofocus>
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-envelope"></span>
                </div>
            </div>
            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="invalid-feedback" role="alert"><strong><?php echo e($message); ?></strong></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="input-group mb-3">
            <input type="password" name="password" class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   placeholder="Password" required>
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
            <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="invalid-feedback" role="alert"><strong><?php echo e($message); ?></strong></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <hr>
        <div class="input-group mb-3">
            <button class="btn btn-primary btn-block" type="submit">Ingresar</button>
        </div>
    </form>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('auth_footer'); ?>
    <?php if(session('error')): ?>
        <script>
            Swal.fire({
                position: "top-center",
                icon: "error",
                title: "<?php echo e(session('error')); ?>",
                showConfirmButton: false,
                timer: 4000
            });
        </script>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('adminlte::auth.auth-page', ['auth_type' => 'login'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\wamp64\www\cargaHorariav2\resources\views/auth/login.blade.php ENDPATH**/ ?>