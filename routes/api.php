<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ========= AUTH / PERFIL =========
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\PerfilApiController;

// ========= CATÁLOGOS =========
use App\Http\Controllers\Api\ProgramApiController;
use App\Http\Controllers\Api\SubjectApiController;
use App\Http\Controllers\Api\GrupoApiController;
use App\Http\Controllers\Api\ProfesorApiController;

// ========= HORARIOS (lectura) =========
use App\Http\Controllers\Api\Horarios\HorarioGrupoApiController;
use App\Http\Controllers\Api\Horarios\HorarioProfesorApiController;
use App\Http\Controllers\Api\Horarios\HorarioEspacioApiController;

// ========= HORARIOS (edición manual: drag & drop) =========
use App\Http\Controllers\Api\Horarios\HorarioManualApiController;

// ========= INSTITUCIÓN =========
use App\Http\Controllers\Api\Institucion\SalonesApiController;
use App\Http\Controllers\Api\Institucion\EdificiosApiController;
use App\Http\Controllers\Api\Institucion\LaboratoriosApiController;

// ========= CONFIG =========
use App\Http\Controllers\Api\Config\UsuarioApiController;
use App\Http\Controllers\Api\Config\RolApiController;
use App\Http\Controllers\Api\Config\EliminarMateriasApiController;
use App\Http\Controllers\Api\Config\ActivarUsuariosApiController;
use App\Http\Controllers\Api\Config\EstadisticaApiController;
use App\Http\Controllers\Api\Config\CalendarioEscolarApiController;
use App\Http\Controllers\Api\Config\HorarioPasadoApiController;
use App\Http\Controllers\Api\Config\RegistroActividadApiController;

// ========= PROFES ←→ MATERIAS (ASIGNACIÓN) =========
use App\Http\Controllers\Api\TeacherSubjectApiController;

// ===================== STATUS =====================
Route::get('/v1/status', fn () => response()->json(['ok' => true, 'version' => 'v1']));

// ===================== AUTENTICACIÓN =====================
Route::prefix('v1')->group(function () {
    Route::post('/auth/login',  [AuthApiController::class, 'login']);
    Route::post('/auth/logout', [AuthApiController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me',      [AuthApiController::class, 'me'])->middleware('auth:sanctum');
});

// ===================== PERFIL =====================
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/perfil',           [PerfilApiController::class, 'show']);
    Route::post('/perfil/password', [PerfilApiController::class, 'updatePassword']);
    Route::post('/perfil/foto',     [PerfilApiController::class, 'updatePhoto']);
});

// ===================== CATÁLOGOS (CRUD) =====================
Route::prefix('v1')->group(function () {
    // ===== Programas =====
    Route::get('/programas',        [ProgramApiController::class, 'index']);
    Route::get('/programas/{id}',   [ProgramApiController::class, 'show'])->whereNumber('id');
    Route::post('/programas',       [ProgramApiController::class, 'store'])->middleware(['auth:sanctum','can:crear programas']);
    Route::match(['put','patch'],'/programas/{id}', [ProgramApiController::class, 'update'])->middleware(['auth:sanctum','can:editar programas'])->whereNumber('id');
    Route::delete('/programas/{id}',[ProgramApiController::class, 'destroy'])->middleware(['auth:sanctum','can:eliminar programas'])->whereNumber('id');

    // ===== Materias =====
    Route::get('/materias',         [SubjectApiController::class, 'index']);
    Route::get('/materias/{id}',    [SubjectApiController::class, 'show'])->whereNumber('id');
    Route::post('/materias',        [SubjectApiController::class, 'store'])->middleware(['auth:sanctum','can:crear materias']);
    Route::match(['put','patch'],'/materias/{id}', [SubjectApiController::class, 'update'])->middleware(['auth:sanctum','can:editar materias'])->whereNumber('id');
    Route::delete('/materias/{id}', [SubjectApiController::class, 'destroy'])->middleware(['auth:sanctum','can:eliminar materias'])->whereNumber('id');

    // ===== Grupos =====
    Route::get('/grupos',           [GrupoApiController::class, 'index']);
    Route::get('/grupos/{id}',      [GrupoApiController::class, 'show'])->whereNumber('id');
    Route::post('/grupos',          [GrupoApiController::class, 'store'])->middleware(['auth:sanctum','can:crear grupos']);
    Route::match(['put','patch'],'/grupos/{id}', [GrupoApiController::class, 'update'])->middleware(['auth:sanctum','can:editar grupos'])->whereNumber('id');
    Route::delete('/grupos/{id}',   [GrupoApiController::class, 'destroy'])->middleware(['auth:sanctum','can:eliminar grupos'])->whereNumber('id');

    // ===== Profesores =====
    Route::get('/profesores',       [ProfesorApiController::class, 'index']);
    Route::get('/profesores/{id}',  [ProfesorApiController::class, 'show'])->whereNumber('id');
    Route::post('/profesores',      [ProfesorApiController::class, 'store'])->middleware(['auth:sanctum','can:crear profesores']);
    Route::match(['put','patch'],'/profesores/{id}', [ProfesorApiController::class, 'update'])->middleware(['auth:sanctum','can:editar profesores'])->whereNumber('id');
    Route::delete('/profesores/{id}',[ProfesorApiController::class, 'destroy'])->middleware(['auth:sanctum','can:eliminar profesores'])->whereNumber('id');
});

// ===================== PROFES → ASIGNAR MATERIAS (CRUD) =====================
Route::prefix('v1/profesores/{profesor_id}')->middleware(['auth:sanctum','can:asignar materias'])->group(function () {

        Route::get('/materias', [TeacherSubjectApiController::class, 'index'])->whereNumber('profesor_id');
        Route::post('/materias', [TeacherSubjectApiController::class, 'store'])->whereNumber('profesor_id');
        Route::delete('/materias/{teacher_subject_id}', [TeacherSubjectApiController::class, 'destroy'])->whereNumber('profesor_id')->whereNumber('teacher_subject_id');
        Route::post('/ajax/materias-por-grupo', [TeacherSubjectApiController::class, 'materiasPorGrupo'])->whereNumber('profesor_id');
        Route::post('/ajax/horas', [TeacherSubjectApiController::class, 'horasProfesor'])->whereNumber('profesor_id');
    });

// ===================== HORARIOS (LECTURA) =====================
Route::prefix('v1/horarios')->group(function () {
    Route::get('/grupos',                        [HorarioGrupoApiController::class, 'index']);
    Route::get('/grupos/{grupo_id}',             [HorarioGrupoApiController::class, 'show'])->whereNumber('grupo_id');
    Route::get('/grupos/{grupo_id}/eventos',     [HorarioGrupoApiController::class, 'eventos'])->whereNumber('grupo_id');

    Route::get('/profesores',                    [HorarioProfesorApiController::class, 'index']);
    Route::get('/profesores/{profesor_id}',      [HorarioProfesorApiController::class, 'show'])->whereNumber('profesor_id');
    Route::get('/profesores/{profesor_id}/eventos', [HorarioProfesorApiController::class, 'eventos'])->whereNumber('profesor_id');

    Route::get('/espacios/salon/{classroom_id}/eventos', [HorarioEspacioApiController::class, 'eventosSalon'])->whereNumber('classroom_id');
    Route::get('/espacios/lab/{lab_id}/eventos',         [HorarioEspacioApiController::class, 'eventosLaboratorio'])->whereNumber('lab_id');
});

// ===================== HORARIO MANUAL (DRAG & DROP) =====================
Route::prefix('v1/horarios/manual')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/asignaciones',         [HorarioManualApiController::class, 'store']);
    Route::put('/asignaciones/{id}',     [HorarioManualApiController::class, 'update'])->whereNumber('id');
    Route::delete('/asignaciones/{id}',  [HorarioManualApiController::class, 'destroy'])->whereNumber('id');

    Route::post('/ajax/mover',           [HorarioManualApiController::class, 'mover']);
    Route::post('/ajax/borrar',          [HorarioManualApiController::class, 'borrar']);
    Route::post('/ajax/crear',           [HorarioManualApiController::class, 'crear']);

    Route::get('/ajax/grupo/{grupo_id}',       [HorarioManualApiController::class, 'dataPorGrupo'])->whereNumber('grupo_id');
    Route::get('/ajax/profesor/{profesor_id}', [HorarioManualApiController::class, 'dataPorProfesor'])->whereNumber('profesor_id');
    Route::get('/ajax/eventos-espacio',        [HorarioManualApiController::class, 'eventosPorEspacio']);
    Route::get('/ajax/opciones',               [HorarioManualApiController::class, 'opciones']);
});

// ===================== INSTITUCIÓN =====================
Route::prefix('v1/institucion')->group(function () {
    Route::get('/edificios',                   [EdificiosApiController::class, 'index']);
    Route::get('/edificios/{id}',              [EdificiosApiController::class, 'show'])->whereNumber('id');
    Route::get('/edificios/{building}/plantas',[EdificiosApiController::class, 'plantas']);

    Route::get('/salones',                     [SalonesApiController::class, 'index']);
    Route::get('/salones/{id}',                [SalonesApiController::class, 'show'])->whereNumber('id');

    Route::get('/laboratorios',                [LaboratoriosApiController::class, 'index']);
    Route::get('/laboratorios/{id}',           [LaboratoriosApiController::class, 'show'])->whereNumber('id');
});

// ===================== CONFIGURACIÓN (ADMIN) =====================
Route::prefix('v1/config')->middleware('auth:sanctum')->group(function () {
    // Usuarios
    Route::get('/usuarios',      [UsuarioApiController::class, 'index']);
    Route::post('/usuarios',     [UsuarioApiController::class, 'store']);
    Route::get('/usuarios/{id}', [UsuarioApiController::class, 'show'])->whereNumber('id');
    Route::put('/usuarios/{id}', [UsuarioApiController::class, 'update'])->whereNumber('id');
    Route::delete('/usuarios/{id}', [UsuarioApiController::class, 'destroy'])->whereNumber('id');

    // Activar/Desactivar
    Route::post('/activar-usuarios/on',  [ActivarUsuariosApiController::class, 'activarTodos']);
    Route::post('/activar-usuarios/off', [ActivarUsuariosApiController::class, 'desactivarTodos']);

    // Roles
    Route::get('/roles',      [RolApiController::class, 'index']);
    Route::post('/roles',     [RolApiController::class, 'store']);
    Route::get('/roles/{id}', [RolApiController::class, 'show'])->whereNumber('id');
    Route::put('/roles/{id}', [RolApiController::class, 'update'])->whereNumber('id');
    Route::delete('/roles/{id}', [RolApiController::class, 'destroy'])->whereNumber('id');
    Route::get('/roles/{id}/permissions',  [RolApiController::class, 'permissions'])->whereNumber('id');
    Route::post('/roles/{id}/permissions', [RolApiController::class, 'assignPermissions'])->whereNumber('id');

    // Eliminar materias
    Route::get('/eliminar-materias', [EliminarMateriasApiController::class, 'index']);
    Route::get('/eliminar-materias/profesor/{id}', [EliminarMateriasApiController::class, 'edit'])->whereNumber('id');
    Route::post('/eliminar-materias/profesor/{id}', [EliminarMateriasApiController::class, 'destroySelected'])->whereNumber('id');
    Route::post('/eliminar-materias/profesor/{id}/ajax/materias-asignadas', [EliminarMateriasApiController::class, 'materiasAsignadas'])->whereNumber('id');
    Route::post('/eliminar-materias/profesor/{id}/ajax/horas', [EliminarMateriasApiController::class, 'horasProfesor'])->whereNumber('id');

    // Estadísticas
    Route::get('/estadisticas', [EstadisticaApiController::class, 'index']);
    Route::get('/estadisticas/export/grupos', [EstadisticaApiController::class, 'exportHorariosGrupos']);
    Route::get('/estadisticas/export/grupos-sin-profesor', [EstadisticaApiController::class, 'exportHorariosGruposSinProfesor']);
    Route::get('/estadisticas/export/profesores', [EstadisticaApiController::class, 'exportHorariosProfesores']);

    // Calendario Escolar
    Route::get('/calendario-escolar', [CalendarioEscolarApiController::class, 'index']);
    Route::post('/calendario-escolar', [CalendarioEscolarApiController::class, 'store']);
    Route::get('/calendario-escolar/{id}', [CalendarioEscolarApiController::class, 'show'])->whereNumber('id');
    Route::put('/calendario-escolar/{id}', [CalendarioEscolarApiController::class, 'update'])->whereNumber('id');
    Route::delete('/calendario-escolar/{id}', [CalendarioEscolarApiController::class, 'destroy'])->whereNumber('id');

    // Horarios Pasados
    Route::get('/horarios-pasados', [HorarioPasadoApiController::class, 'index']);
    Route::post('/horarios-pasados', [HorarioPasadoApiController::class, 'store']);
    Route::get('/horarios-pasados/{id}', [HorarioPasadoApiController::class, 'show'])->whereNumber('id');
    Route::put('/horarios-pasados/{id}', [HorarioPasadoApiController::class, 'update'])->whereNumber('id');
    Route::delete('/horarios-pasados/{id}', [HorarioPasadoApiController::class, 'destroy'])->whereNumber('id');

    // Registro Actividad
    Route::get('/registro-actividad', [RegistroActividadApiController::class, 'index']);
    Route::post('/registro-actividad', [RegistroActividadApiController::class, 'store']);
    Route::get('/registro-actividad/{id}', [RegistroActividadApiController::class, 'show'])->whereNumber('id');
    Route::put('/registro-actividad/{id}', [RegistroActividadApiController::class, 'update'])->whereNumber('id');
    Route::delete('/registro-actividad/{id}', [RegistroActividadApiController::class, 'destroy'])->whereNumber('id');
});

// ===================== 404 API =====================
Route::fallback(function () {
    return response()->json(['message' => 'Endpoint no encontrado'], 404);
});
