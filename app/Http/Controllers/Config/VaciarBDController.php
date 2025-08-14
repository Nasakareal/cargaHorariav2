<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use App\Models\ActividadGeneral;

class VaciarBDController extends Controller
{
    /* Mapa central de recursos -> tabla y PK */
    protected array $map = [
        'grupos' => [
            'table' => 'groups',
            'pk'    => 'group_id',
        ],
        'materias' => [
            'table' => 'subjects',
            'pk'    => 'subject_id',
        ],
        'profesores' => [
            'table' => 'teachers',
            'pk'    => 'teacher_id',
        ],
        'asignaciones' => [
            'table' => 'teacher_subjects',
            'pk'    => 'teacher_subject_id',
        ],
        'horario_escolar' => [
            'table' => 'schedule_assignments',
            'pk'    => 'id',
        ],
        'horario_escolar_manual' => [
            'table' => 'manual_schedule_assignments',
            'pk'    => 'id',
        ],
    ];

    /* ======== ÍNDICE (conteos) ======== */
    public function index()
    {
        $resumen = [];

        foreach ($this->map as $key => $cfg) {
            $tabla  = $cfg['table'];
            $existe = Schema::hasTable($tabla);
            $conteo = $existe ? DB::table($tabla)->count() : null;

            $resumen[$key] = [
                'tabla'  => $tabla,
                'existe' => $existe,
                'count'  => $conteo,
            ];
        }

        return view('configuracion.vaciar_bd.index', compact('resumen'));
    }

    /* ======== ACCIONES: BORRAR TODO (sin shows) ======== */

    public function gruposTruncate(Request $r)
    {
        $this->mustCanVaciar();
        return $this->deleteGroupsAll('Vació todos los grupos');
    }

    public function materiasTruncate(Request $r)
    {
        $this->mustCanVaciar();
        return $this->deleteSubjectsAll('Vació todas las materias');
    }

    public function profesoresTruncate(Request $r)
    {
        $this->mustCanVaciar();
        return $this->deleteTeachersAll('Vació todos los profesores');
    }

    public function asignacionesTruncate(Request $r)
    {
        $this->mustCanVaciar();
        return $this->deleteAllRows('asignaciones', 'Vació todas las asignaciones');
    }

    public function horarioEscolarTruncate(Request $r)
    {
        $this->mustCanVaciar();
        return $this->deleteAllRows('horario_escolar', 'Vació el horario escolar (auto)');
    }

    public function horarioEscolarManualTruncate(Request $r)
    {
        $this->mustCanVaciar();
        return $this->deleteAllRows('horario_escolar_manual', 'Vació el horario escolar manual');
    }

    /* ======== HELPERS ESPECÍFICOS POR RECURSO ======== */

    /* Materias: primero dependientes -> luego subjects */
    protected function deleteSubjectsAll(string $mensajeLog)
    {
        if (!Schema::hasTable('subjects')) {
            return back()->with('error', "La tabla 'subjects' no existe.");
        }

        try {
            DB::beginTransaction();

            /* Borra dependientes si existen y si tienen la columna subject_id */
            $this->deleteIfColumnNotNull('schedule_assignments', 'subject_id');
            $this->deleteIfColumnNotNull('manual_schedule_assignments', 'subject_id');
            $this->deleteIfColumnNotNull('subject_labs', 'subject_id');
            $this->deleteIfColumnNotNull('teacher_subjects', 'subject_id');
            $this->deleteIfColumnNotNull('program_term_subjects', 'subject_id');

            /* Finalmente, subjects */
            $borrados = DB::table('subjects')->delete();

            DB::commit();

            ActividadGeneral::registrar('DELETE_ALL', 'subjects', null, "{$mensajeLog} ({$borrados} registros)");
            return back()->with('success', "Se eliminaron {$borrados} registro(s) de 'subjects'.");
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al borrar materias', ['code'=>$e->getCode(), 'msg'=>$e->getMessage()]);
            return back()->with('error', "Error de base de datos al vaciar 'subjects'.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al borrar materias', ['msg'=>$e->getMessage()]);
            return back()->with('error', "Ocurrió un error inesperado al vaciar 'subjects'.");
        }
    }

    /* Profesores: primero dependientes (schedule/teacher_subjects) -> luego teachers */
/** Profesores: limpia referenciadas y luego borra teachers */
protected function deleteTeachersAll(string $mensajeLog)
{
    if (!Schema::hasTable('teachers')) {
        return back()->with('error', "La tabla 'teachers' no existe.");
    }

    try {
        DB::beginTransaction();

        /** 1) Descubrir TODAS las tablas/columnas que referencian a teachers.teacher_id */
        $refs = $this->fkReferencesTo('teachers', 'teacher_id'); // [['table'=>'...','column'=>'...'], ...]

        /** Tablas puente/relacionales seguras para DELETE */
        $whitelistDelete = [
            'teacher_subjects',
            'schedule_assignments',
            'manual_schedule_assignments',
        ];

        /** Subconsulta de IDs de teachers existentes */
        $teacherIdsSub = DB::table('teachers')->select('teacher_id');

        foreach ($refs as $ref) {
            $tbl = $ref['table'];
            $col = $ref['column'];

            if (!Schema::hasTable($tbl) || !Schema::hasColumn($tbl, $col)) {
                continue;
            }

            if (in_array($tbl, $whitelistDelete, true)) {
                // Tablas puente: borrar filas que apunten a cualquier teacher_id
                DB::table($tbl)->whereIn($col, $teacherIdsSub)->delete();
            } else {
                // Otras tablas: si el FK es nullable, mejor ponerlo en NULL
                if ($this->isColumnNullable($tbl, $col)) {
                    DB::table($tbl)->whereIn($col, $teacherIdsSub)->update([$col => null]);
                } else {
                    // Si NO es nullable y bloquea, como último recurso borra SOLO esas filas
                    // (evita truncar toda la tabla por accidente)
                    DB::table($tbl)->whereIn($col, $teacherIdsSub)->delete();
                }
            }
        }

        /** 2) Además, limpia columnas auxiliares que no siempre tienen FK explícito */
        $this->deleteIfColumnNotNull('schedule_assignments', 'teacher_subject_id');
        $this->deleteIfColumnNotNull('manual_schedule_assignments', 'teacher_subject_id');

        /** 3) Finalmente, borra TODOS los teachers */
        $borrados = DB::table('teachers')->delete();

        DB::commit();

        ActividadGeneral::registrar('DELETE_ALL', 'teachers', null, "{$mensajeLog} ({$borrados} registros)");
        return back()->with('success', "Se eliminaron {$borrados} registro(s) de 'teachers'.");
    } catch (\Illuminate\Database\QueryException $e) {
        DB::rollBack();
        Log::error('Error BD al borrar profesores', ['code'=>$e->getCode(), 'msg'=>$e->getMessage()]);
        return back()->with('error', "Error de base de datos al vaciar 'teachers'.");
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('Error inesperado al borrar profesores', ['msg'=>$e->getMessage()]);
        return back()->with('error', "Ocurrió un error inesperado al vaciar 'teachers'.");
    }
}

    /** Devuelve todas las (tabla, columna) que tienen FK -> parentTable.parentColumn */
    protected function fkReferencesTo(string $parentTable, string $parentColumn): array
    {
        $db = DB::getDatabaseName();

        $rows = DB::select(
            "SELECT TABLE_NAME AS tbl, COLUMN_NAME AS col
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? 
               AND REFERENCED_TABLE_NAME = ?
               AND REFERENCED_COLUMN_NAME = ?",
            [$db, $parentTable, $parentColumn]
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = ['table' => $r->tbl, 'column' => $r->col];
        }
        return $out;
    }

    /** ¿La columna es NULLABLE? (consulta INFORMATION_SCHEMA.COLUMNS) */
    protected function isColumnNullable(string $table, string $column): bool
    {
        $db = DB::getDatabaseName();

        $rows = DB::select(
            "SELECT IS_NULLABLE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1",
            [$db, $table, $column]
        );

        if (!$rows) return false;
        return strtoupper($rows[0]->IS_NULLABLE ?? 'NO') === 'YES';
    }

    /* Grupos: primero dependientes (schedule/teacher_subjects) -> luego groups */
    protected function deleteGroupsAll(string $mensajeLog)
    {
        if (!Schema::hasTable('groups')) {
            return back()->with('error', "La tabla 'groups' no existe.");
        }

        try {
            DB::beginTransaction();

            $this->deleteIfColumnNotNull('schedule_assignments', 'group_id');
            $this->deleteIfColumnNotNull('manual_schedule_assignments', 'group_id');
            $this->deleteIfColumnNotNull('teacher_subjects', 'group_id');

            $borrados = DB::table('groups')->delete();

            DB::commit();

            ActividadGeneral::registrar('DELETE_ALL', 'groups', null, "{$mensajeLog} ({$borrados} registros)");
            return back()->with('success', "Se eliminaron {$borrados} registro(s) de 'groups'.");
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al borrar grupos', ['code'=>$e->getCode(), 'msg'=>$e->getMessage()]);
            return back()->with('error', "Error de base de datos al vaciar 'groups'.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al borrar grupos', ['msg'=>$e->getMessage()]);
            return back()->with('error', "Ocurrió un error inesperado al vaciar 'groups'.");
        }
    }

    /* Genérico: borra todos los registros de la tabla indicada en $map */
    protected function deleteAllRows(string $key, string $mensajeLog)
    {
        $cfg   = $this->map[$key] ?? null;
        $tabla = $cfg['table'] ?? null;

        if (!$tabla || !Schema::hasTable($tabla)) {
            return back()->with('error', "La tabla para '{$key}' no existe o no está configurada.");
        }

        try {
            DB::beginTransaction();

            $borrados = DB::table($tabla)->delete();

            DB::commit();

            ActividadGeneral::registrar('DELETE_ALL', $tabla, null, "{$mensajeLog} ({$borrados} registros)");
            return back()->with('success', "Se eliminaron {$borrados} registro(s) de '{$tabla}'.");
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al borrar todo', ['tabla'=>$tabla, 'code'=>$e->getCode(), 'msg'=>$e->getMessage()]);
            return back()->with('error', "Error de base de datos al vaciar '{$tabla}'.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al borrar todo', ['tabla'=>$tabla, 'msg'=>$e->getMessage()]);
            return back()->with('error', "Ocurrió un error inesperado al vaciar '{$tabla}'.");
        }
    }

    /* Utilidad: borra si existe la tabla y la columna, usando WHERE NOT NULL (evita truncar todo por accidente) */
    protected function deleteIfColumnNotNull(string $tabla, string $col): void
    {
        if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, $col)) {
            DB::table($tabla)->whereNotNull($col)->delete();
        }
    }

    /* Defensa en capas */
    protected function mustCanVaciar(): void
    {
        if (!Auth::user()?->can('vaciar bd')) {
            abort(403, 'No tienes permiso para vaciar la base de datos.');
        }
    }
}
