<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Welcome
use App\Http\Controllers\WelcomeController;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PerfilController;

// Materias
use App\Http\Controllers\ProgramController;

// Materias
use App\Http\Controllers\SubjectController;

// Grupos
use App\Http\Controllers\GrupoController;

// Profesores
use App\Http\Controllers\ProfesorController;

//Horarios
use App\Http\Controllers\Horarios\HorarioManualController;
use App\Http\Controllers\Horarios\IntercambioHorarioController;
use App\Http\Controllers\Horarios\HorarioGrupoController;
use App\Http\Controllers\Horarios\HorarioProfesorController;

// Institucion
use App\Http\Controllers\Institucion\SalonesController;
use App\Http\Controllers\Institucion\EdificiosController;
use App\Http\Controllers\Institucion\LaboratoriosController;

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

Route::get('/politica-privacidad', function () {
    return view('privacy');
})->name('privacy');

// Página pública de bienvenida
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

// Autenticación (usa tu tabla usuarios) sin registro público
Auth::routes(['register' => false]);

// Página protegida (solo si está autenticado)
Route::get('/home', [HomeController::class, 'index'])
    ->name('home')
    ->middleware('auth');


// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/perfil', [PerfilController::class, 'index'])->name('perfil.index');
    Route::post('/perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password');
    Route::post('/perfil/foto', [PerfilController::class, 'updatePhoto'])->name('perfil.foto');
});


// =================== Programas ===================
Route::prefix('programas')->name('programas.')->middleware('can:ver programas')->group(function () {

    Route::get('/create', [ProgramController::class, 'create'])->middleware('can:crear programas')->name('create');
    Route::post('/', [ProgramController::class, 'store'])->middleware('can:crear programas')->name('store');
    Route::get('/', [ProgramController::class, 'index'])->name('index');
    Route::get('/{id}', [ProgramController::class, 'show'])->name('show')->whereNumber('id');
    Route::get('/{id}/edit', [ProgramController::class, 'edit'])->middleware('can:editar programas')->name('edit')->whereNumber('id');
    Route::put('/{id}', [ProgramController::class, 'update'])->middleware('can:editar programas')->name('update')->whereNumber('id');
    Route::delete('/{id}', [ProgramController::class, 'destroy'])->middleware('can:eliminar programas')->name('destroy')->whereNumber('id');
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

// =================== Grupos ===================
Route::prefix('grupos')->name('grupos.')->middleware('can:ver grupos')->group(function () {
    Route::get('/',           [GrupoController::class,'index'])->name('index');
    Route::get('/create',     [GrupoController::class,'create'])->middleware('can:crear grupos')->name('create');
    Route::post('/',          [GrupoController::class,'store'])->middleware('can:crear grupos')->name('store');
    Route::get('/{id}/edit',  [GrupoController::class,'edit'])->middleware('can:editar grupos')->name('edit')->whereNumber('id');
    Route::put('/{id}',       [GrupoController::class,'update'])->middleware('can:editar grupos')->name('update')->whereNumber('id');
    Route::get('/{id}',       [GrupoController::class,'show'])->name('show')->whereNumber('id');
    Route::delete('/{id}',    [GrupoController::class,'destroy'])->middleware('can:eliminar grupos')->name('destroy')->whereNumber('id');
    Route::get('/{id}/validar-salon/{classroom}', [GrupoController::class,'validarSalon'])->name('validar-salon')->middleware('can:editar grupos')->whereNumber('id')->whereNumber('classroom');
});

// =================== AJAX: edificio → plantas / salones ===================
Route::middleware(['auth','can:editar grupos'])->prefix('ajax')->name('ajax.')->group(function () {

        // /ajax/edificios/{building}/plantas   →  name: ajax.edificio.plantas
        Route::get('/edificios/{building}/plantas', function ($building) {
            $building = rawurldecode($building);

            $floors = DB::table('classrooms')->where('building', $building)->whereNotNull('floor')->select('floor')->distinct()->orderBy('floor')->pluck('floor')->map(fn($f) => strtoupper((string)$f))->values();

            if ($floors->isEmpty()) {
                $floors = collect(['BAJA','ALTA']);
            }

            return response()->json($floors);
        })->name('edificio.plantas');

        // /ajax/edificios/{building}/plantas/{floor}/salones  →  name: ajax.planta.salones
        Route::get('/edificios/{building}/plantas/{floor}/salones', function ($building, $floor) {
            $building = rawurldecode($building);
            $floor    = strtoupper(rawurldecode($floor));

            $salones = DB::table('classrooms')->where('building', $building)->when($floor !== '' && $floor !== '-', fn($q) => $q->where('floor', $floor))->select('classroom_id','classroom_name')->orderByRaw('CAST(classroom_name AS UNSIGNED), classroom_name')->get();

            return response()->json($salones);
        })->name('planta.salones');
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

// =================== INSTITUCIÓN ===================
Route::prefix('institucion')->middleware('auth','can:ver instituciones')->name('institucion.')->group(function () {

    Route::get('/', [App\Http\Controllers\Institucion\InstitucionController::class, 'index'])->name('index');

    // ----- Salones -----
    Route::prefix('salones')->name('salones.')->middleware('can:ver salones')->group(function () {
        Route::get('/',         [SalonesController::class,'index'])     ->name('index');
        Route::get('/create',   [SalonesController::class,'create'])    ->middleware('can:crear salones')->name('create');
        Route::post('/',        [SalonesController::class,'store'])     ->middleware('can:crear salones')->name('store');
        Route::get('/{id}',     [SalonesController::class,'show'])      ->name('show');
        Route::get('/{id}/edit',[SalonesController::class,'edit'])      ->middleware('can:editar salones')->name('edit');
        Route::put('/{id}',     [SalonesController::class,'update'])    ->middleware('can:editar salones')->name('update');
        Route::delete('/{id}',  [SalonesController::class,'destroy'])   ->middleware('can:eliminar salones')->name('destroy');
        Route::get('/{id}/horario', [SalonesController::class,'horario'])->name('horario');
    });

    // ----- Edificios -----
    Route::prefix('edificios')->name('edificios.')->middleware('can:ver edificios')->group(function () {
        Route::get('/',         [EdificiosController::class,'index'])     ->name('index');
        Route::get('/create',   [EdificiosController::class,'create'])    ->middleware('can:crear edificios')->name('create');
        Route::post('/',        [EdificiosController::class,'store'])     ->middleware('can:crear edificios')->name('store');
        Route::get('/{id}',     [EdificiosController::class,'show'])      ->name('show');
        Route::get('/{id}/edit',[EdificiosController::class,'edit'])      ->middleware('can:editar edificios')->name('edit');
        Route::put('/{id}',     [EdificiosController::class,'update'])    ->middleware('can:editar edificios')->name('update');
        Route::delete('/{id}',  [EdificiosController::class,'destroy'])   ->middleware('can:eliminar edificios')->name('destroy');
    });

    // ----- Laboratorios -----
    Route::prefix('laboratorios')->name('laboratorios.')->middleware('can:ver laboratorios')->group(function () {
        Route::get('/',         [LaboratoriosController::class,'index'])     ->name('index');
        Route::get('/create',   [LaboratoriosController::class,'create'])    ->middleware('can:crear laboratorios')->name('create');
        Route::post('/',        [LaboratoriosController::class,'store'])     ->middleware('can:crear laboratorios')->name('store');
        Route::get('/{id}',     [LaboratoriosController::class,'show'])      ->name('show');
        Route::get('/{id}/edit',[LaboratoriosController::class,'edit'])      ->middleware('can:editar laboratorios')->name('edit');
        Route::put('/{id}',     [LaboratoriosController::class,'update'])    ->middleware('can:editar laboratorios')->name('update');
        Route::delete('/{id}',  [LaboratoriosController::class,'destroy'])   ->middleware('can:eliminar laboratorios')->name('destroy');
        Route::get('/{id}/horario', [LaboratoriosController::class,'horario'])->name('horario');
    });
});

// =================== CONFIGURACIÓN ===================
Route::prefix('configuracion')->middleware('auth','can:ver configuraciones')->name('configuracion.')->group(function () {

    Route::get('/', [App\Http\Controllers\Config\ConfiguracionController::class, 'index'])->name('index');


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
    Route::prefix('vaciar-bd')->name('vaciar-bd.')->middleware('can:ver vaciar bd')->group(function () {

        // Página principal con tarjetas/opciones
        Route::get('/', [VaciarBDController::class,'index'])->name('index');

        // ===== Grupos =====
        Route::get('/grupos',  [VaciarBDController::class,'gruposIndex'])->name('grupos.index');
        Route::delete('/grupos', [VaciarBDController::class,'gruposTruncate'])->middleware('can:vaciar bd')->name('grupos.truncate');
        Route::delete('/grupos/seleccionados', [VaciarBDController::class,'gruposDestroySelected'])->middleware('can:vaciar bd')->name('grupos.destroy-selected');

        // ===== Materias =====
        Route::get('/materias', [VaciarBDController::class,'materiasIndex'])->name('materias.index');
        Route::delete('/materias', [VaciarBDController::class,'materiasTruncate'])->middleware('can:vaciar bd')->name('materias.truncate');
        Route::delete('/materias/seleccionados', [VaciarBDController::class,'materiasDestroySelected'])->middleware('can:vaciar bd')->name('materias.destroy-selected');

        // ===== Profesores =====
        Route::get('/profesores', [VaciarBDController::class,'profesoresIndex'])->name('profesores.index');
        Route::delete('/profesores', [VaciarBDController::class,'profesoresTruncate'])->middleware('can:vaciar bd')->name('profesores.truncate');
        Route::delete('/profesores/seleccionados', [VaciarBDController::class,'profesoresDestroySelected'])->middleware('can:vaciar bd')->name('profesores.destroy-selected');

        // ===== Asignaciones (materia <-> profesor) =====
        Route::get('/asignaciones', [VaciarBDController::class,'asignacionesIndex'])->name('asignaciones.index');
        Route::delete('/asignaciones', [VaciarBDController::class,'asignacionesTruncate'])->middleware('can:vaciar bd')->name('asignaciones.truncate');
        Route::delete('/asignaciones/seleccionados', [VaciarBDController::class,'asignacionesDestroySelected'])->middleware('can:vaciar bd')->name('asignaciones.destroy-selected');

        // ===== Horario Escolar (auto) =====
        Route::get('/horario-escolar', [VaciarBDController::class,'horarioEscolarIndex'])->name('horario-escolar.index');
        Route::delete('/horario-escolar', [VaciarBDController::class,'horarioEscolarTruncate'])->middleware('can:vaciar bd')->name('horario-escolar.truncate');

        // ===== Horario Escolar (manual) =====
        Route::get('/horario-escolar-manual', [VaciarBDController::class,'horarioEscolarManualIndex'])->name('horario-escolar-manual.index');
        Route::delete('/horario-escolar-manual', [VaciarBDController::class,'horarioEscolarManualTruncate'])->middleware('can:vaciar bd')->name('horario-escolar-manual.truncate');
    });

    // ----- Eliminar Materias (quitar asignaciones a un profesor) -----
    Route::prefix('eliminar-materias')->name('eliminar-materias.')->middleware('can:ver eliminar materias')->group(function () {

        Route::get('/', [EliminarMateriasController::class,'index'])->name('index');
        Route::get('/profesor/{id}', [EliminarMateriasController::class,'edit'])->middleware('can:eliminar materias')->name('edit')->whereNumber('id');
        Route::post('/profesor/{id}', [EliminarMateriasController::class,'destroySelected'])->middleware('can:eliminar materias')->name('destroy-selected')->whereNumber('id');
        Route::post('/profesor/{id}/ajax/materias-asignadas', [EliminarMateriasController::class,'materiasAsignadas'])->middleware('can:eliminar materias')->name('ajax.materias-asignadas')->whereNumber('id');
        Route::post('/profesor/{id}/ajax/horas', [EliminarMateriasController::class,'horasProfesor'])->middleware('can:eliminar materias')->name('ajax.horas')->whereNumber('id');
    });

    // ----- Activar/Desactivar Usuarios (masivo, sin vista)
    Route::get('activar-usuarios/on',  [ActivarUsuariosController::class,'activarTodos'])->middleware('can:activar usuarios')->name('activar-usuarios.on');
    Route::get('activar-usuarios/off', [ActivarUsuariosController::class,'desactivarTodos'])->middleware('can:activar usuarios')->name('activar-usuarios.off');

    // ----- Estadísticas -----
    Route::prefix('estadisticas')->name('estadisticas.')->middleware('can:ver estadisticas')->group(function () {

            Route::get('/', [EstadisticaController::class, 'index'])->name('index');

            Route::get('/export/grupos',              [EstadisticaController::class, 'exportHorariosGrupos'])->name('export.grupos');
            Route::get('/export/grupos-sin-profesor', [EstadisticaController::class, 'exportHorariosGruposSinProfesor'])->name('export.grupos-sin-profesor');
            Route::get('/export/profesores',          [EstadisticaController::class, 'exportHorariosProfesores'])->name('export.profesores');
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
        Route::get('/',        [RegistroActividadController::class,'index'])->name('index');
        Route::get('/create',  [RegistroActividadController::class,'create'])->middleware('can:crear registro actividad')->name('create');
        Route::post('/',       [RegistroActividadController::class,'store'])->middleware('can:crear registro actividad')->name('store');
        Route::get('/{id}',    [RegistroActividadController::class,'show'])->name('show');
        Route::get('/{id}/edit',[RegistroActividadController::class,'edit'])->middleware('can:editar registro actividad')->name('edit');
        Route::put('/{id}',    [RegistroActividadController::class,'update'])->middleware('can:editar registro actividad')->name('update');
        Route::delete('/{id}', [RegistroActividadController::class,'destroy'])->middleware('can:eliminar registro actividad')->name('destroy');
    });
});
