<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HorarioProfesorController extends Controller
{
    protected string $T_DOCENTES = 'teachers';
    protected string $T_GRUPOS   = 'groups';
    protected string $T_MATERIAS = 'subjects';
    protected string $T_HORARIOS = 'schedule_assignments';
    protected string $T_AULAS    = 'salones';
    protected string $T_LABS     = 'laboratorios';

    /** ============================================================
     * GET /profesores → listado con buscador
     * ============================================================ */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $schema = DB::getSchemaBuilder();

        $nameCols = array_values(array_filter(
            ['teacher_name'],
            fn($c) => $schema->hasColumn($this->T_DOCENTES, $c)
        ));
        $docenteExpr = $nameCols
            ? 'COALESCE('.implode(', ', array_map(fn($c)=>"t.$c", $nameCols)).')'
            : "''";

        $query = DB::table($this->T_DOCENTES.' as t')
            ->when($schema->hasColumn($this->T_DOCENTES,'estado'), function($q2){
                $q2->whereIn('t.estado', ['1','ACTIVO','activo']);
            })
            ->when($q !== '', function($qq) use ($q, $docenteExpr){
                $qq->whereRaw("$docenteExpr LIKE ?", ["%{$q}%"]);
            })
            ->selectRaw("t.teacher_id, {$docenteExpr} as docente");

        // orden alfabetico por el expr del nombre
        $profesores = $query->orderByRaw("$docenteExpr asc")
            ->paginate(20)
            ->appends($request->query());

        return view('horarios.profesores.index', [
            'profesores' => $profesores,
            'q'          => $q,
        ]);
    }

    /** ============================================================
     * GET /profesores/{profesor_id} → grid horario del profesor
     * ============================================================ */
    public function show(int $profesor_id)
    {
        $schema = DB::getSchemaBuilder();

        // ===== tablas de espacios =====
        if (!$schema->hasTable($this->T_AULAS)) {
            if ($schema->hasTable('classrooms')) $this->T_AULAS = 'classrooms';
            else $this->T_AULAS = null;
        }
        if (!$schema->hasTable($this->T_LABS)) {
            if     ($schema->hasTable('labs'))          $this->T_LABS = 'labs';
            elseif ($schema->hasTable('laboratories'))  $this->T_LABS = 'laboratories';
            else $this->T_LABS = null;
        }

        // ===== expr nombre docente =====
        $nameCols = array_values(array_filter(
            ['teacher_name','nombre_completo','full_name','name','nombre'],
            fn($c) => $schema->hasColumn($this->T_DOCENTES, $c)
        ));
        $docenteExpr = $nameCols
            ? 'COALESCE('.implode(', ', array_map(fn($c)=>"t.$c", $nameCols)).')'
            : "''";

        // docente
        $profesor = DB::table($this->T_DOCENTES.' as t')
            ->where('t.teacher_id', $profesor_id)
            ->selectRaw("t.teacher_id, {$docenteExpr} as docente")
            ->first();

        abort_unless($profesor, 404, 'Profesor no encontrado');

        // ===== expr nombre materia/grupo =====
        $subCols = array_values(array_filter(
            ['subject_name','nombre','name'],
            fn($c) => $schema->hasColumn($this->T_MATERIAS, $c)
        ));
        $materiaExpr = $subCols
            ? 'COALESCE('.implode(', ', array_map(fn($c)=>"m.$c", $subCols)).')'
            : "''";

        $grpCols = array_values(array_filter(
            ['group_name','nombre','name'],
            fn($c) => $schema->hasColumn($this->T_GRUPOS, $c)
        ));
        $grupoExpr = $grpCols
            ? 'COALESCE('.implode(', ', array_map(fn($c)=>"g.$c", $grpCols)).')'
            : "''";

        // ===== IDs/nombres de AULA/LAB =====
        $aulaIdCol = null; $aulaExpr = 'NULL';
        if ($this->T_AULAS) {
            foreach (['classroom_id','salon_id','id'] as $c) {
                if ($schema->hasColumn($this->T_AULAS, $c)) { $aulaIdCol = $c; break; }
            }
            $aulaNames = array_filter([
                $schema->hasColumn($this->T_AULAS,'classroom_name') ? 'a.classroom_name' : null,
                $schema->hasColumn($this->T_AULAS,'nombre')         ? 'a.nombre'         : null,
                $schema->hasColumn($this->T_AULAS,'name')           ? 'a.name'           : null,
                $schema->hasColumn($this->T_AULAS,'room_name')      ? 'a.room_name'      : null,
            ]);
            if ($aulaNames) $aulaExpr = 'COALESCE('.implode(', ', $aulaNames).')';
        }

        $labIdCol = null; $labExpr = 'NULL';
        if ($this->T_LABS) {
            foreach (['lab_id','laboratorio_id','laboratory_id','id'] as $c) {
                if ($schema->hasColumn($this->T_LABS, $c)) { $labIdCol = $c; break; }
            }
            $labNames = array_filter([
                $schema->hasColumn($this->T_LABS,'lab_name')         ? 'l.lab_name'         : null,
                $schema->hasColumn($this->T_LABS,'laboratory_name')  ? 'l.laboratory_name'  : null,
                $schema->hasColumn($this->T_LABS,'nombre')           ? 'l.nombre'           : null,
                $schema->hasColumn($this->T_LABS,'name')             ? 'l.name'             : null,
            ]);
            if ($labNames) $labExpr = 'COALESCE('.implode(', ', $labNames).')';
        }

        // ===== columnas existentes en schedule_assignments (h.*) =====
        $hasTeacher   = $schema->hasColumn($this->T_HORARIOS,'teacher_id');
        $hasSubject   = $schema->hasColumn($this->T_HORARIOS,'subject_id');
        $hasGroup     = $schema->hasColumn($this->T_HORARIOS,'group_id');
        $hasClassroom = $schema->hasColumn($this->T_HORARIOS,'classroom_id');
        $hasLab       = $schema->hasColumn($this->T_HORARIOS,'lab_id');
        $hasDay       = $schema->hasColumn($this->T_HORARIOS,'schedule_day');
        $hasStart     = $schema->hasColumn($this->T_HORARIOS,'start_time');
        $hasEnd       = $schema->hasColumn($this->T_HORARIOS,'end_time');

        // expresiones (solo con columnas que sí existen)
        $hTeacherExpr = $hasTeacher   ? 'h.teacher_id'   : 'NULL';
        $hSubjectExpr = $hasSubject   ? 'h.subject_id'   : 'NULL';
        $hGroupExpr   = $hasGroup     ? 'h.group_id'     : 'NULL';
        $hRoomExpr    = $hasClassroom ? 'h.classroom_id' : 'NULL';
        $hLabExpr     = $hasLab       ? 'h.lab_id'       : 'NULL';
        $hDayExpr     = $hasDay       ? 'h.schedule_day' : 'NULL';
        $hStartExpr   = $hasStart     ? 'h.start_time'   : 'NULL';
        $hEndExpr     = $hasEnd       ? 'h.end_time'     : 'NULL';

        // ===== query principal =====
        $rows = DB::table($this->T_HORARIOS.' as h')
            ->when($hasSubject, function($q) use ($hSubjectExpr) {
                $q->leftJoin($this->T_MATERIAS.' as m', function ($j) use ($hSubjectExpr) {
                    $j->on('m.subject_id', '=', DB::raw($hSubjectExpr));
                });
            })
            ->when($hasGroup, function($q) use ($hGroupExpr) {
                $q->leftJoin($this->T_GRUPOS.' as g', function ($j) use ($hGroupExpr) {
                    $j->on('g.group_id', '=', DB::raw($hGroupExpr));
                });
            })
            ->when($this->T_AULAS && $aulaIdCol && $hasClassroom, function($q) use ($aulaIdCol, $hRoomExpr) {
                $q->leftJoin($this->T_AULAS.' as a', function ($j) use ($aulaIdCol, $hRoomExpr) {
                    $j->on("a.$aulaIdCol", '=', DB::raw($hRoomExpr));
                });
            })
            ->when($this->T_LABS && $labIdCol && $hasLab, function($q) use ($labIdCol, $hLabExpr) {
                $q->leftJoin($this->T_LABS.' as l', function ($j) use ($labIdCol, $hLabExpr) {
                    $j->on("l.$labIdCol", '=', DB::raw($hLabExpr));
                });
            })
            ->where(function($w) use ($profesor_id, $hasTeacher) {
                if ($hasTeacher) $w->where('h.teacher_id', $profesor_id);
                else $w->whereRaw('1=0'); // por si acaso
            })
            ->when($schema->hasColumn($this->T_HORARIOS, 'estado'), function($q){
                $q->whereIn('h.estado', ['1','ACTIVO','activo']);
            })
            ->selectRaw("
                h.*,
                {$hSubjectExpr}  as subject_id,
                {$hTeacherExpr}  as teacher_id,
                {$hGroupExpr}    as group_id,
                {$hRoomExpr}     as classroom_id,
                {$hLabExpr}      as lab_id,

                {$hDayExpr}      as dia_semana,
                {$hStartExpr}    as hora_inicio,
                {$hEndExpr}      as hora_fin,

                {$materiaExpr}   as materia,
                {$grupoExpr}     as grupo,
                {$aulaExpr}      as aula_nombre,
                {$labExpr}       as lab_nombre,

                (CASE WHEN {$hLabExpr} IS NOT NULL THEN 1 ELSE 0 END) as es_lab
            ")
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        // ===== SIEMPRE lunes a sábado, 07:00–20:00 =====
        $dias  = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $horas = $this->buildHourSlots('07:00:00', '20:00:00', 60); // 07-08 ... 19-20


        // ===== matriz [hora][día] => HTML =====
        $tabla = [];
        foreach ($horas as $hLabel) foreach ($dias as $d) { $tabla[$hLabel][$d] = ''; }

        foreach ($rows as $r) {
            $diaCanon = $this->canonicalDay($r->dia_semana);
            if (!in_array($diaCanon, $dias, true)) continue;

            foreach ($horas as $hLabel) {
                [$hStart, $hEnd] = explode(' - ', $hLabel);
                $hStart .= ':00'; $hEnd .= ':00';
                if (!$this->overlaps($hStart, $hEnd, $r->hora_inicio, $r->hora_fin)) continue;

                $espacio = (intval($r->es_lab) === 1)
                    ? ($r->lab_nombre ?: 'Laboratorio')
                    : ($r->aula_nombre ?: 'Aula');

                $linea = e(($r->materia ?: 'Materia')) . ' — ' . e(($r->grupo ?: 'Grupo')) . ' — ' . e($espacio);

                $tabla[$hLabel][$diaCanon] = trim($tabla[$hLabel][$diaCanon]) === ''
                    ? $linea
                    : $tabla[$hLabel][$diaCanon] . '<br>' . $linea;
            }
        }

        // ===== selector de profesores =====
        $profesores = DB::table($this->T_DOCENTES.' as t')
            ->selectRaw("t.teacher_id, {$docenteExpr} as docente")
            ->orderByRaw("$docenteExpr asc")
            ->get();

        return view('horarios.profesores.show', [
            'profesor'   => $profesor,
            'dias'       => $dias,
            'horas'      => $horas,
            'tabla'      => $tabla,
            'profesores' => $profesores,
        ]);
    }



    /** ============================================================
     * GET /profesores/{profesor_id}/eventos → feed JSON FullCalendar
     * ============================================================ */
    public function eventos(int $profesor_id)
    {
        $schema = DB::getSchemaBuilder();

        $materiaExpr = $schema->hasColumn($this->T_MATERIAS, 'subject_name') || $schema->hasColumn($this->T_MATERIAS, 'nombre') || $schema->hasColumn($this->T_MATERIAS, 'name')
            ? 'COALESCE(m.subject_name, m.nombre, m.name)'
            : "NULL";

        $grupoExpr = $schema->hasColumn($this->T_GRUPOS, 'group_name') || $schema->hasColumn($this->T_GRUPOS, 'nombre') || $schema->hasColumn($this->T_GRUPOS, 'name')
            ? 'COALESCE(g.group_name, g.nombre, g.name)'
            : "NULL";

        $aulaExpr = $schema->hasColumn($this->T_AULAS, 'nombre') || $schema->hasColumn($this->T_AULAS, 'name')
            ? 'COALESCE(a.nombre, a.name)'
            : "NULL";
        $labExpr  = $schema->hasColumn($this->T_LABS, 'nombre') || $schema->hasColumn($this->T_LABS, 'name')
            ? 'COALESCE(l.nombre, l.name)'
            : "NULL";

        $rows = DB::table($this->T_HORARIOS.' as h')
            ->leftJoin($this->T_MATERIAS.' as m', function ($j) {
                $j->on('m.subject_id', '=', DB::raw('COALESCE(h.subject_id, h.materia_id)'));
            })
            ->leftJoin($this->T_GRUPOS.' as g', function ($j) {
                $j->on('g.group_id', '=', DB::raw('COALESCE(h.group_id, h.grupo_id)'));
            })
            ->leftJoin($this->T_AULAS.' as a', function ($j) {
                $j->on('a.salon_id', '=', DB::raw('COALESCE(h.room_id, h.salon_id)'));
            })
            ->leftJoin($this->T_LABS.' as l', function ($j) {
                $j->on('l.laboratorio_id', '=', DB::raw('COALESCE(h.lab_id, h.laboratorio_id)'));
            })
            ->where(function($w) use ($profesor_id) {
                $w->where('h.teacher_id', $profesor_id)
                  ->orWhere('h.profesor_id', $profesor_id)
                  ->orWhere('h.docente_id', $profesor_id);
            })
            ->selectRaw("
                COALESCE(h.dia_semana,  h.day_of_week, h.dow, h.schedule_day) as dia_semana,
                COALESCE(h.hora_inicio, h.start_time)                           as hora_inicio,
                COALESCE(h.hora_fin,    h.end_time,   h.hora_final)             as hora_fin,

                COALESCE(h.group_id,    h.grupo_id)                             as group_id,
                COALESCE(h.subject_id,  h.materia_id)                           as subject_id,
                COALESCE(h.room_id,     h.salon_id)                             as salon_id,
                COALESCE(h.lab_id,      h.laboratorio_id)                       as laboratorio_id,

                {$materiaExpr} as materia,
                {$grupoExpr}   as grupo,
                {$aulaExpr}    as aula_nombre,
                {$labExpr}     as lab_nombre,

                COALESCE(h.es_lab, h.is_lab, (CASE WHEN h.lab_id IS NOT NULL OR h.laboratorio_id IS NOT NULL THEN 1 ELSE 0 END)) as es_lab
            ")
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        $events = $rows->map(function($r){
            $dow = $this->toFullCalendarDow($r->dia_semana);
            $espacio = (intval($r->es_lab) === 1)
                ? ($r->lab_nombre ?: 'Laboratorio')
                : ($r->aula_nombre ?: 'Aula');

            return [
                'title'      => trim(($r->materia ?? 'Materia') . ($r->grupo ? " • {$r->grupo}" : '')),
                'daysOfWeek' => [$dow], // 0=domingo ... 6=sábado
                'startTime'  => substr($r->hora_inicio, 0, 8),
                'endTime'    => substr($r->hora_fin,    0, 8),
                'extendedProps' => [
                    'group_id'   => $r->group_id,
                    'subject_id' => $r->subject_id,
                    'espacio'    => $espacio,
                    'aula_id'    => $r->salon_id,
                    'laboratorio_id' => $r->laboratorio_id,
                    'es_lab'     => intval($r->es_lab) === 1,
                ],
            ];
        })->values();

        return response()->json($events);
    }

    /** ================= Helpers ================= */

    protected function canonicalDay($d): string
    {
        $k = mb_strtolower(trim((string)$d), 'UTF-8');
        // si viene numérico: 1=lunes ... 7=domingo
        if (is_numeric($k)) {
            $n = intval($k) % 7;
            return [0=>'Domingo',1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado'][$n];
        }
        return match ($k) {
            '1','lunes' => 'Lunes',
            '2','martes' => 'Martes',
            '3','miercoles','miércoles' => 'Miércoles',
            '4','jueves' => 'Jueves',
            '5','viernes' => 'Viernes',
            '6','sabado','sábado' => 'Sábado',
            '0','7','domingo' => 'Domingo',
            default => 'Lunes',
        };
    }

    protected function collectDiasCanon($rows): array
    {
        $orden = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
        $set = [];
        foreach ($rows as $r) {
            $set[$this->canonicalDay($r->dia_semana)] = true;
        }
        $dias = array_keys($set);
        usort($dias, fn($a,$b)=>array_search($a,$orden)<=>array_search($b,$orden));
        return $dias;
    }

    protected function boundsFromAssignments($rows): array
    {
        if (!$rows || $rows->isEmpty()) return [null, null];
        $mins = $rows->pluck('hora_inicio')->filter()->all();
        $maxs = $rows->pluck('hora_fin')->filter()->all();
        return [ $mins ? min($mins) : null, $maxs ? max($maxs) : null ];
    }

    protected function buildHourSlots(string $start, string $end, int $stepMinutes = 60): array
    {
        $out = [];
        $t = Carbon::createFromFormat('H:i:s', $start);
        $E = Carbon::createFromFormat('H:i:s', $end);
        while ($t < $E) {
            $nxt = $t->copy()->addMinutes($stepMinutes);
            $out[] = $t->format('H:i') . ' - ' . $nxt->format('H:i');
            $t = $nxt;
        }
        return $out;
    }

    protected function overlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return ($aStart < $bEnd) && ($aEnd > $bStart);
    }

    /** 0=domingo..6=sábado (FullCalendar) aceptando num o texto */
    protected function toFullCalendarDow($v): int
    {
        if (is_numeric($v)) {
            $n = intval($v);
            // 1..7 (lun..dom) → 1..0
            return match ($n % 7) {
                1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 0 => 0,
            };
        }
        return match (mb_strtolower(trim((string)$v), 'UTF-8')) {
            'lunes' => 1, 'martes' => 2, 'miercoles','miércoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado','sábado' => 6, 'domingo' => 0,
            default => 1,
        };
    }
}
