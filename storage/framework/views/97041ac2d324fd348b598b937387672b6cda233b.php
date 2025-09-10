<li <?php if(isset($item['id'])): ?> id="<?php echo e($item['id']); ?>" <?php endif; ?> class="nav-item has-treeview <?php echo e($item['submenu_class']); ?>">


    
    <a class="nav-link bg-primary rounded mb-2 <?php echo e($item['class']); ?> <?php if(isset($item['shift'])): ?> <?php echo e($item['shift']); ?> <?php endif; ?>"
       href="" <?php echo $item['data-compiled'] ?? ''; ?>

       style="color:#fff !important;">

        <i class="nav-icon <?php echo e($item['icon'] ?? 'far fa-fw fa-circle'); ?> <?php echo e(isset($item['icon_color']) ? 'text-'.$item['icon_color'] : ''); ?>"></i>

        <p>
            <?php echo e($item['text']); ?>

            <i class="fas fa-angle-left right"></i>

            <?php if(isset($item['label'])): ?>
                <span class="badge badge-<?php echo e($item['label_color'] ?? 'primary'); ?> right">
                    <?php echo e($item['label']); ?>

                </span>
            <?php endif; ?>
        </p>
    </a>

    
    
    <ul class="nav nav-treeview nav-treeview-solid" style="background-color:#007D80 !important;">
        <?php echo $__env->renderEach('adminlte::partials.sidebar.menu-item', $item['submenu'], 'item'); ?>
    </ul>

    <?php if (! $__env->hasRenderedOnce('f66516bf-ad85-4393-904e-46e9eed0e9c0')): $__env->markAsRenderedOnce('f66516bf-ad85-4393-904e-46e9eed0e9c0'); ?>
    <style>
      /* Texto e íconos blancos en submenús */
      .nav-treeview > .nav-item > .nav-link,
      .nav-treeview > .nav-item > .nav-link > p,
      .nav-treeview > .nav-item > .nav-link .nav-icon {
        color: #fff !important;
        opacity: 1 !important;
      }

      /* Hover */
      .nav-treeview > .nav-item > .nav-link:hover {
        background: rgba(255,255,255,.12) !important;
      }

      /* Activo resaltado */
      .nav-treeview > .nav-item > .nav-link.active {
        background: rgba(255,255,255,.25) !important;
        font-weight: bold;
        border-left: 3px solid #fff; /* Línea izquierda opcional */
      }


      /*

      
    <a class="nav-link rounded mb-2 <?php echo e($item['class']); ?> <?php if(isset($item['shift'])): ?> <?php echo e($item['shift']); ?> <?php endif; ?>"
       href="" <?php echo $item['data-compiled'] ?? ''; ?>

       style="background-color:#343a40 !important; color:#fff !important;">

        <i class="nav-icon <?php echo e($item['icon'] ?? 'far fa-fw fa-circle'); ?> <?php echo e(isset($item['icon_color']) ? 'text-'.$item['icon_color'] : ''); ?>"></i>

        <p>
            <?php echo e($item['text']); ?>

            <i class="fas fa-angle-left right"></i>

            <?php if(isset($item['label'])): ?>
                <span class="badge badge-<?php echo e($item['label_color'] ?? 'primary'); ?> right">
                    <?php echo e($item['label']); ?>

                </span>
            <?php endif; ?>
        </p>
    </a>


      */
    </style>
    <?php endif; ?>

</li>
<?php /**PATH C:\wamp64\www\cargaHorariav2\resources\views/vendor/adminlte/partials/sidebar/menu-item-treeview-menu.blade.php ENDPATH**/ ?>