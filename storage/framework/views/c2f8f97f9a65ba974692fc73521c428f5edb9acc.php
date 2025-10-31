<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" href="<?php echo e(asset('UTM.png')); ?>" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenidos | Sistema de Carga Horaria</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { height: 100%; font-family: Arial, sans-serif; }

        header { position: absolute; top: 10px; right: 10px; z-index: 100; }
        header a.login-button {
            background: #004f39; color: #fff; padding: 8px 12px; border-radius: 4px;
            text-decoration: none; font-weight: bold; transition: background 0.3s;
        }
        header a.login-button:hover { background: #003d2d; }

        .welcome-container {
            position: relative;
            background: url('<?php echo e(asset('images/utm_background.jpg')); ?>') no-repeat center center/cover;
            min-height: 60vh;
            display: flex; align-items: center; justify-content: center;
            text-align: center; padding: 20px; color: white;
        }
        .overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6); }
        .content { position: relative; z-index: 2; max-width: 500px; padding: 20px; }
        .utm-logo { width: 480px; max-width: 100%; height: auto; margin-bottom: 15px; }
        .title { font-size: 1.8rem; font-weight: bold; margin-bottom: 8px; }
        .subtitle { font-size: 1.1rem; margin-bottom: 25px; }

        .system-card {
            background: rgba(255, 255, 255, 0.15); padding: 15px; border-radius: 8px;
            display: inline-block; margin-top: 15px;
        }
        .system-card img { width: 80px; height: auto; margin-bottom: 10px; }
        .system-card a {
            display: inline-block; padding: 10px 20px; font-size: 1rem; font-weight: bold;
            border-radius: 6px; text-transform: uppercase; background-color: #004f39;
            color: white; text-decoration: none; transition: all 0.3s ease-in-out;
        }
        .system-card a:hover { transform: scale(1.05); opacity: 0.9; }

        /* ===== Sección pública ===== */
        .public-section { padding: 28px 16px 48px; background: #f7f7f9; }
        .container { max-width: 1140px; margin: 0 auto; }

        .card { background:#fff; border:1px solid #e3e6ea; border-radius: 8px; margin-bottom: 20px; }
        .card-header { padding: 12px 16px; border-bottom: 1px solid #e3e6ea; font-weight: bold; }
        .card-body { padding: 16px; }
        .text-center { text-align:center; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; border: 1px solid #ddd; }
        th { background: #f1f3f5; }

        .sin-profe { background: #fff3cd; } /* amarillo */
        .muted { color:#6c757d; }

        .row { display: flex; flex-wrap: wrap; gap: 20px; }
        .col-12 { flex: 0 0 100%; }
        .col-md-5 { flex: 0 0 calc(41.666% - 10px); min-width: 300px; }
        .col-md-7 { flex: 0 0 calc(58.333% - 10px); min-width: 300px; }
        .col-md-12 { flex: 0 0 100%; }

        .form-inline { display:flex; gap: 8px; align-items:center; }
        .select { padding:8px; border:1px solid #ced4da; border-radius:4px; min-width: 260px; }
        .small { font-size: .875rem; }
    </style>

    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
</head>
<body>
    <header>
        <a href="<?php echo e(route('login')); ?>" class="login-button">Login</a>
    </header>

    
    <div class="welcome-container">
        <div class="overlay"></div>
        <div class="content">
            <img src="<?php echo e(asset('logo_2025.png')); ?>" alt="UTM" class="utm-logo">
            <h1 class="title">Sistema de Carga Horaria</h1>
            <p class="subtitle">Universidad Tecnológica de Morelia</p>

            <div class="system-card">
                <img src="<?php echo e(asset('UTM.png')); ?>" alt="Carga Horaria">
                <a href="<?php echo e(route('login')); ?>">Ingresar al Sistema</a>
            </div>
        </div>
    </div>



    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
    <script>
      $(function(){
        const tabla = $('#tablaFaltantes');
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
</body>
</html>
<?php /**PATH C:\wamp64\www\cargaHorariav2\resources\views/welcome.blade.php ENDPATH**/ ?>