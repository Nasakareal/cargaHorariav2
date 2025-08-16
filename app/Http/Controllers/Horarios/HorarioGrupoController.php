<?php

namespace App\Http\Controllers\Api\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HorarioGrupoApiController extends Controller
{
    /** GET /api/v1/horarios/grupos  (lista paginada) */
    public function index()
    {
        // groups.turn_id → shifts.shift_id → shifts.shift_name (si existe la tabla shifts)
        $usaShifts = Schema::hasTable('shifts')
            && Schema::hasColumn('groups', 'turn_id')
            && Schema::hasColumn('shifts', 'shift_id')
            && Schema::hasColumn('shifts', 'shift_name');

        $q = DB::table('groups as g');

        if ($usaShifts) {
            $q->leftJoin('shifts as s', 's.shift_id', '=', 'g.turn_id');
            $q->select([
                'g.group_id as id',
                'g.group_name as nombre',
                DB::raw('s.shift_name as turno'),
                'g.program_id',
            ]);
        } else {
            // Fallback por si no hay tabla shifts
            $colTurno = Schema::hasColumn('groups','shift') ? 'g.shift'
                      : (Schema::hasColumn('groups','turno') ? 'g.turno' : null);

            if ($colTurno) {
                $q->select([
                    'g.group_id as id',
                    'g.group_name as nombre',
                    DB::raw("$colTurno as turno"),
                    'g.program_id',
                ]);
            } else {
                $q->select([
                    'g.group_id as id',
                    'g.group_name as nombre',
                    DB::raw('NULL as turno'),
                    'g.program_id',
                ]);
            }
        }

        // Si tienen columna estado, solo activos
        if (Schema::hasColumn('groups', 'estado')) {
            $q->whereIn('g.estado', ['1','ACTIVO','activo']);
        }

        $rows = $q->orderBy('g.group_name')->paginate(50);
        return response()->json($rows);
    }

    /** GET /api/v1/horarios/grupos/{grupo_id}  (detalle simple) */
    public function show($grupo_id)
    {
        $usaShifts = Schema::hasTable('shifts')
            && Schema::hasColumn('groups', 'turn_id')
            && Schema::hasColumn('shifts', 'shift_id')
            && Schema::hasColumn('shifts', 'shift_name');

        $q = DB::table('groups as g');

        if ($usaShifts) {
            $q->leftJoin('shifts as s', 's.shift_id', '=', 'g.turn_id');
            $q->addSelect([
                'g.group_id as id',
                'g.group_name as nombre',
                DB::raw('s.shift_name as turno'),
            ]);
        } else {
            $colTurno = Schema::hasColumn('groups','shift') ? 'g.shift'
                      : (Schema::hasColumn('groups','turno') ? 'g.turno' : null);

            if ($colTurno) {
                $q->addSelect([
                    'g.group_id as id',
                    'g.group_name as nombre',
                    DB::raw("$colTurno as turno"),
                ]);
            } else {
                $q->addSelect([
                    'g.group_id as id',
                    'g.group_name as nombre',
                    DB::raw('NULL as turno'),
                ]);
            }
        }

        $g = $q->where('g.group_id', $grupo_id)->first();
        if (!$g) return response()->json(['message' => 'Grupo no encontrado'], 404);

        return response()->json($g);
    }

    /** GET /api/v1/horarios/grupos/{grupo_id}/eventos */
    public function eventos($grupo_id)
    {
        $q = DB::table('schedule_assignments as sa')
            ->leftJoin('subjects as sub',   'sub.subject_id',   '=', 'sa.subject_id')
            ->leftJoin('teachers as t',     't.teacher_id',     '=', 'sa.teacher_id')
            ->leftJoin('classrooms as c',   'c.classroom_id',   '=', 'sa.classroom_id')
            ->leftJoin('labs as l',         'l.lab_id',         '=', 'sa.lab_id')
            ->where('sa.group_id', $grupo_id)
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time');

        // Solo activos si existe columna estado
        if (Schema::hasColumn('schedule_assignments', 'estado')) {
            $q->whereIn('sa.estado', ['1','ACTIVO','activo']);
        }

        // Alias compatibles con Flutter (camelCase y snake_case donde aplica)
        $rows = $q->selectRaw("
                sa.assignment_id as id,
                sa.group_id,

                sa.subject_id,
                sub.subject_name as subject_name,
                sub.subject_name as subjectName,

                sa.teacher_id,
                t.teacher_name as teacher_name,
                t.teacher_name as teacherName,

                sa.classroom_id,
                c.classroom_name as classroom_name,
                c.classroom_name as classroomName,

                sa.lab_id,
                l.lab_name as lab_name,
                l.lab_name as labName,

                sa.start_time,
                sa.end_time,

                sa.schedule_day as day,

                sa.tipo_espacio as tipoEspacio
            ")
            ->get();

        return response()->json($rows);
    }
}
