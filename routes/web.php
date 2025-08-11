<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PerfilController;

// Profesores
use App\Http\Controllers\SubjectController;

// Materias
use App\Http\Controllers\Materiasontroller;

//Horarios
use App\Http\Controllers\Horarios\HorarioManualController;
use App\Http\Controllers\Horarios\IntercambioHorarioController;
use App\Http\Controllers\Horarios\HorarioGrupoController;
use App\Http\Controllers\Horarios\HorarioProfesorController;

// Configuración
use App\Http\Controllers\Config\UsuarioController;
use App\Http\Controllers\Config\RolController;
use App\Http\Controllers\Config\VaciarBDController;
use App\Http\Controllers\Config\EliminarMateriasController;
use App\Http\Controllers\Config\ActivarUsuariosController;
use App\Http\Controllers\Config\EstadisticaController;
use App\Http\Controllers\Config\CalendarioEscolarController;
use App\Http\Controllers\Config\HorarioPasadoController;
use App\Http\Controllers\Config\RegistroActividadController;

// Página pública de bienvenida
Route::get('/', function () {
    return view('welcome');
});

// Autenticación (usa tu tabla usuarios) sin registro público
Auth::routes(['register' => false]);

// Página protegida (solo si está autenticado)
Route::get('/home', [HomeController::class, 'index'])
    ->name('home')
    ->middleware('auth');



Route::middleware('auth')->group(function () {
    Route::get('/perfil', [PerfilController::class, 'index'])->name('perfil.index');
    Route::post('/perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password');
});

// =================== Profesores ===================
Route::prefix('profesores')->name('profesores.')->middleware('can:ver profesores')->group(function () {
    Route::get('/',           [ProfesorController::class,'index'])->name('index');
    Route::get('/create',     [ProfesorController::class,'create'])->middleware('can:crear profesores')->name('create');
    Route::post('/',          [ProfesorController::class,'store'])->middleware('can:crear profesores')->name('store');
    Route::get('/{id}/edit',              [ProfesorController::class,'edit'])->middleware('can:editar profesores')->name('edit')->whereNumber('id');
    Route::put('/{id}',                   [ProfesorController::class,'update'])->middleware('can:editar profesores')->name('update')->whereNumber('id');
    Route::get('/{id}/asignar-materias',  [ProfesorController::class,'asignarMateriasForm'])->middleware('can:asignar materias')->name('asignar-materias')->whereNumber('id');
    Route::post('/{id}/asignar-materias', [ProfesorController::class,'asignarMateriasStore'])->middleware('can:asignar materias')->name('asignar-materias.store')->whereNumber('id');
    Route::post('/{id}/ajax/materias-por-grupo', [ProfesorController::class,'materiasPorGrupo'])->middleware('can:asignar materias')->name('ajax.materias-por-grupo')->whereNumber('id');
    Route::post('/{id}/ajax/horas',              [ProfesorController::class,'horasProfesor'])->middleware('can:asignar materias')->name('ajax.horas')->whereNumber('id');
    Route::get('/{id}',        [ProfesorController::class,'show'])->name('show')->whereNumber('id');
    Route::delete('/{id}',     [ProfesorController::class,'destroy'])->middleware('can:eliminar profesores')->name('destroy')->whereNumber('id');
});


// =================== Materias ===================
Route::prefix('materias')->name('materias.')->middleware('can:ver materias')->group(function () {
    Route::get('/',           [SubjectController::class,'index'])->name('index');
    Route::get('/create',     [SubjectController::class,'create'])->middleware('can:crear materias')->name('create');
    Route::post('/',          [SubjectController::class,'store'])->middleware('can:crear materias')->name('store');
    Route::get('/{id}/edit',  [SubjectController::class,'edit'])->middleware('can:editar materias')->name('edit')->whereNumber('id');
    Route::put('/{id}',       [SubjectController::class,'update'])->middleware('can:editar materias')->name('update')->whereNumber('id');
    Route::get('/{id}',       [SubjectController::class,'show'])->name('show')->whereNumber('id');
    Route::delete('/{id}',    [SubjectController::class,'destroy'])->middleware('can:eliminar materias')->name('destroy')->whereNumber('id');
});

// =================== Horarios ===================
Route::prefix('horarios')->name('horarios.')->middleware('can:ver horario laboratorio')->group(function () {

    // ===== 1) Asignación manual (drag & drop dentro del mismo blade) =====
    Route::middleware('can:bloquear horario')->group(function () {
        Route::get('/manual', [HorarioManualController::class,'index'])->middleware('can:ver horario laboratorio')->name('manual.index');
        Route::post('/manual/asignaciones', [HorarioManualController::class,'store'])->middleware('can:crear horario laboratorio')->name('manual.asignaciones.store');
        Route::put('/manual/asignaciones/{id}', [HorarioManualController::class,'update'])->middleware('can:editar horario laboratorio')->whereNumber('id')->name('manual.asignaciones.update');
        Route::delete('/manual/asignaciones/{id}', [HorarioManualController::class,'destroy'])->middleware('can:borrar horario laboratorio')->whereNumber('id')->name('manual.asignaciones.destroy');
        Route::post('/manual/ajax/mover', [HorarioManualController::class,'mover'])->middleware('can:editar horario laboratorio')->name('manual.ajax.mover'); // arrastrar/soltar (cambia día/hora/aula)
        Route::post('/manual/ajax/borrar', [HorarioManualController::class,'borrar'])->middleware('can:borrar horario laboratorio')->name('manual.ajax.borrar'); // borrar desde el grid (click)
        Route::post('/manual/ajax/crear', [HorarioManualController::class,'crear'])->middleware('can:crear horario laboratorio')->name('manual.ajax.crear'); // crear soltando en un slot vacío
        Route::get('/manual/ajax/grupo/{grupo_id}', [HorarioManualController::class,'dataPorGrupo'])->middleware('can:ver horario laboratorio')->whereNumber('grupo_id')->name('manual.ajax.grupo'); // eventos del grupo (calendar/json)
        Route::get('/manual/ajax/profesor/{profesor_id}', [HorarioManualController::class,'dataPorProfesor'])->middleware('can:ver horario laboratorio')->whereNumber('profesor_id')->name('manual.ajax.profesor'); // eventos del profe (calendar/json)
        Route::get('/manual/ajax/opciones', [HorarioManualController::class,'opciones'])->middleware('can:ver horario laboratorio')->name('manual.ajax.opciones'); // combos: grupos, materias, aulas, turnos, etc.
        Route::get('/manual/ajax/eventos-espacio', [HorarioManualController::class,'eventosPorEspacio'])->middleware('can:ver horario laboratorio')->name('manual.ajax.eventos-espacio');
    });

    // ===== 2) Intercambio de horarios =====
    Route::middleware('can:intercambiar horarios')->group(function () {
        Route::get('/intercambio', [IntercambioHorarioController::class,'index'])->name('intercambio.index');
        Route::get('/intercambio/ajax/grupo/{group_id}', [IntercambioHorarioController::class,'eventosPorGrupo'])->whereNumber('group_id')->name('intercambio.ajax.grupo');
        Route::post('/intercambio/mover',  [IntercambioHorarioController::class,'mover'])->name('intercambio.mover');
        Route::post('/intercambio/borrar', [IntercambioHorarioController::class,'borrar'])->name('intercambio.borrar');
        Route::post('/intercambio/simular',   [IntercambioHorarioController::class,'simular'])->name('intercambio.simular');
        Route::post('/intercambio/confirmar', [IntercambioHorarioController::class,'confirmar'])->name('intercambio.confirmar');
    });

    // ===== 3) Ver horarios de grupos (solo lectura) =====
    Route::middleware('can:ver horarios grupos')->group(function () {
        Route::get('/grupos', [HorarioGrupoController::class,'index'])->name('grupos.index');
        Route::get('/grupos/{grupo_id}', [HorarioGrupoController::class,'show'])->whereNumber('grupo_id')->name('grupos.show');
        Route::get('/grupos/{grupo_id}/eventos', [HorarioGrupoController::class,'eventos'])->whereNumber('grupo_id')->name('grupos.eventos');
    });

    // ===== 4) Ver horarios de profesores (solo lectura) =====
    Route::middleware('can:ver horarios profesores')->group(function () {
        Route::get('/profesores', [HorarioProfesorController::class,'index'])->name('profesores.index');
        Route::get('/profesores/{profesor_id}', [HorarioProfesorController::class,'show'])->whereNumber('profesor_id')->name('profesores.show');
        Route::get('/profesores/{profesor_id}/eventos', [HorarioProfesorController::class,'eventos'])->whereNumber('profesor_id')->name('profesores.eventos');
    });
});

// =================== CONFIGURACIÓN ===================
Route::prefix('configuracion')->middleware('auth','can:ver configuraciones')->name('configuracion.')->group(function () {

    Route::get('/', [App\Http\Controllers\Config\ConfiguracionController::class, 'index'])
        ->name('index');


    // ----- Usuarios -----
    Route::prefix('usuarios')->name('usuarios.')->middleware('can:ver usuarios')->group(function () {
        Route::get('/',         [UsuarioController::class,'index'])     ->name('index');
        Route::get('/create',   [UsuarioController::class,'create'])    ->middleware('can:crear usuarios')->name('create');
        Route::post('/',        [UsuarioController::class,'store'])     ->middleware('can:crear usuarios')->name('store');
        Route::get('/{id}',     [UsuarioController::class,'show'])      ->name('show');
        Route::get('/{id}/edit',[UsuarioController::class,'edit'])      ->middleware('can:editar usuarios')->name('edit');
        Route::put('/{id}',     [UsuarioController::class,'update'])    ->middleware('can:editar usuarios')->name('update');
        Route::delete('/{id}',  [UsuarioController::class,'destroy'])   ->middleware('can:eliminar usuarios')->name('destroy');
    });

    // ----- Roles -----
    Route::prefix('roles')->name('roles.')->middleware('can:ver roles')->group(function () {
        Route::get('/',                     [RolController::class,'index'])->name('index');
        Route::get('/create',               [RolController::class,'create'])->middleware('can:crear roles')->name('create');
        Route::post('/',                    [RolController::class,'store'])->middleware('can:crear roles')->name('store');
        Route::get('/{id}',                 [RolController::class,'show'])->name('show');
        Route::get('/{id}/edit',            [RolController::class,'edit'])->middleware('can:editar roles')->name('edit');
        Route::put('/{id}',                 [RolController::class,'update'])->middleware('can:editar roles')->name('update');
        Route::delete('/{id}',              [RolController::class,'destroy'])->middleware('can:eliminar roles')->name('destroy');
        Route::get('/{id}/permissions',     [RolController::class,'permissions'])->middleware('can:editar roles')->name('permissions');
        Route::post('/{id}/permissions',    [RolController::class,'assignPermissions'])->middleware('can:editar roles')->name('assign-permissions');
    });

    // ----- Vaciar Base de Datos -----
    Route::get('vaciar-bd',    [VaciarBDController::class,'index'])->middleware('can:ver vaciar bd')->name('vaciar-bd.index');
    Route::delete('vaciar-bd', [VaciarBDController::class,'destroy'])->middleware('can:vaciar bd')->name('vaciar-bd.destroy');

    // ----- Eliminar Materias -----
    Route::get('eliminar-materias',    [EliminarMateriasController::class,'index'])->middleware('can:ver eliminar materias')->name('eliminar-materias.index');
    Route::delete('eliminar-materias', [EliminarMateriasController::class,'destroy'])->middleware('can:eliminar materias')->name('eliminar-materias.destroy');

    // ----- Activar Usuarios (botón + interfaz) -----
    Route::get('activar-usuarios',        [ActivarUsuariosController::class,'index'])->middleware('can:ver activar usuarios')->name('activar-usuarios.index');
    Route::post('activar-usuarios/{id}',  [ActivarUsuariosController::class,'activar'])->middleware('can:activar usuarios')->name('activar-usuarios.activar');

    // ----- Estadísticas -----
    Route::prefix('estadisticas')->name('estadisticas.')->middleware('can:ver estadisticas')->group(function () {
        Route::get('/',         [EstadisticaController::class,'index'])->name('index');
        Route::get('/create',   [EstadisticaController::class,'create'])->middleware('can:crear estadisticas')->name('create');
        Route::post('/',        [EstadisticaController::class,'store'])->middleware('can:crear estadisticas')->name('store');
        Route::get('/{id}',     [EstadisticaController::class,'show'])->name('show');
        Route::get('/{id}/edit',[EstadisticaController::class,'edit'])->middleware('can:editar estadisticas')->name('edit');
        Route::put('/{id}',     [EstadisticaController::class,'update'])->middleware('can:editar estadisticas')->name('update');
        Route::delete('/{id}',  [EstadisticaController::class,'destroy'])->middleware('can:eliminar estadisticas')->name('destroy');
    });

    // ----- Calendario Escolar -----
    Route::prefix('calendario-escolar')->name('calendario-escolar.')->middleware('can:ver calendario escolar')->group(function () {
        Route::get('/',        [App\Http\Controllers\Config\CalendarioEscolarController::class,'index'])->name('index');
        Route::get('/create',  [App\Http\Controllers\Config\CalendarioEscolarController::class,'create'])->middleware('can:crear calendario escolar')->name('create');
        Route::post('/',       [App\Http\Controllers\Config\CalendarioEscolarController::class,'store'])->middleware('can:crear calendario escolar')->name('store');
        Route::get('/{id}',    [App\Http\Controllers\Config\CalendarioEscolarController::class,'show'])->name('show');
        Route::get('/{id}/edit',[App\Http\Controllers\Config\CalendarioEscolarController::class,'edit'])->middleware('can:editar calendario escolar')->name('edit');
        Route::put('/{id}',    [App\Http\Controllers\Config\CalendarioEscolarController::class,'update'])->middleware('can:editar calendario escolar')->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Config\CalendarioEscolarController::class,'destroy'])->middleware('can:eliminar calendario escolar')->name('destroy');
    });

    // ----- Horarios Pasados -----
    Route::prefix('horarios-pasados')->name('horarios-pasados.')->middleware('can:ver horarios pasados')->group(function () {
        Route::get('/',        [App\Http\Controllers\Config\HorarioPasadoController::class,'index'])->name('index');
        Route::get('/create',  [App\Http\Controllers\Config\HorarioPasadoController::class,'create'])->middleware('can:crear horarios pasados')->name('create');
        Route::post('/',       [App\Http\Controllers\Config\HorarioPasadoController::class,'store'])->middleware('can:crear horarios pasados')->name('store');
        Route::get('/{id}',    [App\Http\Controllers\Config\HorarioPasadoController::class,'show'])->name('show');
        Route::get('/{id}/edit',[App\Http\Controllers\Config\HorarioPasadoController::class,'edit'])->middleware('can:editar horarios pasados')->name('edit');
        Route::put('/{id}',    [App\Http\Controllers\Config\HorarioPasadoController::class,'update'])->middleware('can:editar horarios pasados')->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Config\HorarioPasadoController::class,'destroy'])->middleware('can:eliminar horarios pasados')->name('destroy');
    });

    // ----- Registro de Actividad -----
    Route::prefix('registro-actividad')->name('registro-actividad.')->middleware('can:ver registro actividad')->group(function () {
        Route::get('/',        [App\Http\Controllers\Config\RegistroActividadController::class,'index'])->name('index');
        Route::get('/create',  [App\Http\Controllers\Config\RegistroActividadController::class,'create'])->middleware('can:crear registro actividad')->name('create');
        Route::post('/',       [App\Http\Controllers\Config\RegistroActividadController::class,'store'])->middleware('can:crear registro actividad')->name('store');
        Route::get('/{id}',    [App\Http\Controllers\Config\RegistroActividadController::class,'show'])->name('show');
        Route::get('/{id}/edit',[App\Http\Controllers\Config\RegistroActividadController::class,'edit'])->middleware('can:editar registro actividad')->name('edit');
        Route::put('/{id}',    [App\Http\Controllers\Config\RegistroActividadController::class,'update'])->middleware('can:editar registro actividad')->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Config\RegistroActividadController::class,'destroy'])->middleware('can:eliminar registro actividad')->name('destroy');
    });
});
