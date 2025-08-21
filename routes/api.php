<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ================= DASHBOARD =================
use App\Http\Controllers\Api\DashboardApiController;

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


// ========= PROFES ←→ MATERIAS (ASIGNACIÓN) =========
use App\Http\Controllers\Api\TeacherSubjectApiController;

// ===================== STATUS =====================
Route::get('/v1/status', fn () => response()->json(['ok' => true, 'version' => 'v1']));

// ===================== DASHBOARD =====================
Route::prefix('v1')->group(function () {
    Route::get('/dashboard/resumen', [DashboardApiController::class, 'resumen']);
    Route::get('/dashboard/grupos-faltantes', [DashboardApiController::class, 'gruposConFaltantes']);
});

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
Route::prefix('v1/horarios/manual')
    ->middleware(['auth:sanctum','can:bloquear horario'])
    ->group(function () {

    Route::post('/asignaciones',        [HorarioManualApiController::class, 'store'])->middleware('can:crear horario laboratorio');
    Route::put('/asignaciones/{id}',    [HorarioManualApiController::class, 'update'])->whereNumber('id')->middleware('can:editar horario laboratorio');
    Route::delete('/asignaciones/{id}', [HorarioManualApiController::class, 'destroy'])->whereNumber('id')->middleware('can:borrar horario laboratorio');

    Route::post('/ajax/mover',          [HorarioManualApiController::class, 'mover'])->middleware('can:editar horario laboratorio');
    Route::post('/ajax/borrar',         [HorarioManualApiController::class, 'borrar'])->middleware('can:borrar horario laboratorio');
    Route::post('/ajax/crear',          [HorarioManualApiController::class, 'crear'])->middleware('can:crear horario laboratorio');

    Route::get('/ajax/grupo/{grupo_id}',       [HorarioManualApiController::class, 'dataPorGrupo'])->whereNumber('grupo_id')->middleware('can:ver horario laboratorio');
    Route::get('/ajax/profesor/{profesor_id}', [HorarioManualApiController::class, 'dataPorProfesor'])->whereNumber('profesor_id')->middleware('can:ver horario laboratorio');
    Route::get('/ajax/eventos-espacio',        [HorarioManualApiController::class, 'eventosPorEspacio'])->middleware('can:ver horario laboratorio');
    Route::get('/ajax/opciones',               [HorarioManualApiController::class, 'opciones'])->middleware('can:ver horario laboratorio');
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


// ===================== 404 API =====================
Route::fallback(function () {
    return response()->json(['message' => 'Endpoint no encontrado'], 404);
});
