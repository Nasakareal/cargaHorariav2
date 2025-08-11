<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HorarioGrupoController extends Controller
{
    /** ============================================================
     *  Config rápida de tablas (ajusta solo si en tu BD se llaman distinto)
     *  ============================================================ */
    protected string $T_GRUPOS   = 'groups';
    protected string $T_TURNOS   = 'shifts';
    protected string $T_HORARIOS = 'schedule_assignments';
    protected string $T_MATERIAS = 'materias';
    protected string $T_DOCENTES = 'teachers';
    protected string $T_AULAS    = 'salones';
    protected string $T_LABS     = 'laboratorios';

    /** ============================================================
     *  GET /grupos  → listado con filtros básicos
     *  ============================================================ */
    public function index(Request $request)
    {
        $q     = trim((string) $request->get('q', ''));
        $turno = trim((string) $request->get('turno', ''));

        $grupos = DB::table('groups as g')
            ->leftJoin('shifts as s', 's.shift_id', '=', 'g.turn_id')
            // solo activos si existe el campo estado (1 / ACTIVO / activo)
            ->when(DB::getSchemaBuilder()->hasColumn('groups','estado'), function($q2){
                $q2->whereIn('g.estado', ['1','ACTIVO','activo']);
            })
            // filtro texto
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('g.group_name', 'like', "%{$q}%");
            })
            // filtro por turno exacto
            ->when($turno !== '', function ($qq) use ($turno) {
                $qq->where('s.shift_name', $turno);
            })
            ->select([
                'g.group_id',
                'g.group_name',
                's.shift_name as turno',
            ])
            ->orderBy('g.group_name')
            ->paginate(20)
            ->appends($request->query());

        return view('horarios.grupos.index', [
            'grupos'  => $grupos,
            'filtros' => ['q' => $q, 'turno' => $turno],
        ]);
    }



    /** ============================================================
     *  GET /grupos/{grupo_id}  → detalle (grid con FullCalendar)
     *  ============================================================ */
public function show(int $grupo_id)
{
    // 1) Grupo + turno
    $grupo = DB::table('groups as g')
        ->leftJoin('shifts as s', 's.shift_id', '=', 'g.turn_id')
        ->where('g.group_id', $grupo_id)
        ->select(['g.group_id','g.group_name','s.shift_name as turno'])
        ->first();

    abort_unless($grupo, 404, 'Grupo no encontrado');
    $turno = $grupo->turno ?? 'MATUTINO';

    // 2) Config de disponibilidad
    $dispTurno = config("horarios.disponibles.$turno", []);
    $dias      = config("horarios.dias_semana.$turno", ['Lunes','Martes','Miércoles','Jueves','Viernes']);

    // 3) Asignaciones del grupo (dinámico por columnas reales)
    $schema = DB::getSchemaBuilder();

    $subCols = array_values(array_filter(
        ['subject_name','nombre','name'],
        fn($c) => $schema->hasColumn('subjects', $c)
    ));
    $materiaExpr = $subCols
        ? 'COALESCE('.implode(', ', array_map(fn($c)=>"m.$c", $subCols)).')'
        : "''";

    $teaCols = array_values(array_filter(
        ['teacher_name','nombre_completo','full_name','name','nombre'],
        fn($c) => $schema->hasColumn('teachers', $c)
    ));
    $docenteExpr = $teaCols
        ? 'COALESCE('.implode(', ', array_map(fn($c)=>"t.$c", $teaCols)).')'
        : "''";

    $selTipoEspacio = $schema->hasColumn('schedule_assignments','tipo_espacio')
        ? 's.tipo_espacio'
        : "NULL as tipo_espacio";

    $selClassroom = $schema->hasColumn('schedule_assignments','classroom_id')
        ? 's.classroom_id'
        : "NULL as classroom_id";

    $selLab = $schema->hasColumn('schedule_assignments','lab_id')
        ? 's.lab_id'
        : "NULL as lab_id";

    $rows = DB::table('schedule_assignments as s')
        ->leftJoin('subjects as m', 'm.subject_id','=','s.subject_id')
        ->leftJoin('teachers as t', 't.teacher_id','=','s.teacher_id')
        ->where('s.group_id', $grupo_id)
        ->when($schema->hasColumn('schedule_assignments','estado'), function($q){
            $q->whereIn('s.estado', ['1','ACTIVO','activo']);
        })
        ->selectRaw("
            s.assignment_id,
            s.subject_id,
            {$materiaExpr} as materia,
            s.teacher_id,
            {$docenteExpr} as docente,
            {$selClassroom},
            {$selLab},
            {$selTipoEspacio},
            s.schedule_day,
            s.start_time,
            s.end_time
        ")
        ->orderBy('s.start_time')
        ->get();

    // 4) Rango de horas (min/max) y slots de 60 min
    [$minCfg, $maxCfg] = $this->boundsFromConfig($dispTurno, $dias);
    [$minAsg, $maxAsg] = $this->boundsFromAssignments($rows);
    $min  = $this->minTime([$minCfg, $minAsg, '07:00:00']);
    $max  = $this->maxTime([$maxCfg, $maxAsg, '20:00:00']);
    $horas = $this->buildHourSlots($min, $max, 60);

    // 5) Matriz [hora][día] => HTML
    $tabla = [];
    foreach ($horas as $hLabel) foreach ($dias as $d) { $tabla[$hLabel][$d] = ''; }

    foreach ($rows as $r) {
        $diaCanon = $this->canonicalDay($r->schedule_day);
        if (!in_array($diaCanon, $dias, true)) continue;

        foreach ($horas as $hLabel) {
            [$hStart, $hEnd] = explode(' - ', $hLabel);
            $hStart .= ':00'; $hEnd .= ':00';
            if (!$this->overlaps($hStart, $hEnd, $r->start_time, $r->end_time)) continue;

            $espacio = !empty($r->lab_id)
                ? 'Lab '.$r->lab_id
                : (!empty($r->classroom_id) ? 'Aula '.$r->classroom_id : '—');

            $doc = $r->docente ?: 'Sin profesor';
            $linea = e(($r->materia ?: 'Materia')) . ' — ' . e($espacio) . ' — ' . e($doc);

            $tabla[$hLabel][$diaCanon] = trim($tabla[$hLabel][$diaCanon]) === ''
                ? $linea
                : $tabla[$hLabel][$diaCanon] . '<br>' . $linea;
        }
    }

    // 6) Selector de grupos
    $grupos = DB::table('groups')->select('group_id','group_name')->orderBy('group_name')->get();

    return view('horarios.grupos.show', [
        'grupo'     => $grupo,
        'turno'     => $turno,
        'dias'      => $dias,
        'horas'     => $horas,
        'tabla'     => $tabla,
        'dispTurno' => $dispTurno,
        'grupos'    => $grupos,
    ]);
}


    /** ============================================================
     *  GET /grupos/{grupo_id}/eventos  → feed JSON para FullCalendar
     *  Formato: eventos recurrentes con daysOfWeek/startTime/endTime
     *  ============================================================ */
    public function eventos(int $grupo_id)
    {
        /**/ /* Trae las “clases” del grupo con materia, docente y aula/lab */
        $rows = DB::table("{$this->T_HORARIOS} as h")
            /** Materia */
            ->leftJoin("{$this->T_MATERIAS} as m", function ($j) {
                $j->on('m.subject_id', '=', DB::raw('COALESCE(h.subject_id, h.materia_id)'));
            })
            /** Docente */
            ->leftJoin("{$this->T_DOCENTES} as t", function ($j) {
                $j->on('t.teacher_id', '=', DB::raw('COALESCE(h.teacher_id, h.profesor_id, h.docente_id)'));
            })
            /** Aula y/o Laboratorio (opcional, depende de tu modelo) */
            ->leftJoin("{$this->T_AULAS} as a", function ($j) {
                $j->on('a.salon_id', '=', DB::raw('COALESCE(h.room_id, h.salon_id)'));
            })
            ->leftJoin("{$this->T_LABS} as l", function ($j) {
                $j->on('l.laboratorio_id', '=', DB::raw('COALESCE(h.lab_id, h.laboratorio_id)'));
            })
            ->where(function ($w) use ($grupo_id) {
                $w->where('h.group_id', $grupo_id)
                  ->orWhere('h.grupo_id', $grupo_id);
            })
            ->selectRaw("
                h.*,
                /* normaliza IDs usados en joins */
                COALESCE(h.subject_id,  h.materia_id)                         as subject_id,
                COALESCE(h.teacher_id,  h.profesor_id, h.docente_id)          as teacher_id,
                COALESCE(h.room_id,     h.salon_id)                           as salon_id,
                COALESCE(h.lab_id,      h.laboratorio_id)                     as laboratorio_id,

                /* normaliza campos de tiempo y día */
                COALESCE(h.dia_semana,  h.day_of_week, h.dow)                 as dia_semana,
                COALESCE(h.hora_inicio, h.start_time)                         as hora_inicio,
                COALESCE(h.hora_fin,    h.end_time,   h.hora_final)           as hora_fin,

                /* normaliza materia/docente nombres */
                COALESCE(m.nombre, m.name, m.subject_name)                    as materia,
                COALESCE(t.nombre_completo, t.name, t.full_name, t.nombre)    as docente,

                /* etiqueta de espacio */
                COALESCE(a.nombre, a.name, CONCAT('Aula ', COALESCE(h.room_id, h.salon_id))) as aula_nombre,
                COALESCE(l.nombre, l.name, CONCAT('Lab ',  COALESCE(h.lab_id,  h.laboratorio_id))) as lab_nombre,

                /* indicador de laboratorio */
                COALESCE(h.es_lab, h.is_lab, (CASE WHEN h.lab_id IS NOT NULL OR h.laboratorio_id IS NOT NULL THEN 1 ELSE 0 END)) as es_lab
            ")
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        /**/ /* Mapea a eventos recurrentes de FullCalendar */
        $events = $rows->map(function ($r) {
            $dow = $this->toFullCalendarDow((int) $r->dia_semana);
            $titulo = trim(($r->materia ?? 'Materia') . ($r->docente ? " • {$r->docente}" : ''));
            $espacio = (intval($r->es_lab) === 1)
                ? ($r->lab_nombre ?: 'Laboratorio')
                : ($r->aula_nombre ?: 'Aula');

            return [
                'title'       => $titulo,
                'daysOfWeek'  => [$dow],                 /** 0=domingo ... 6=sábado */
                'startTime'   => substr($r->hora_inicio, 0, 8), /** HH:MM:SS */
                'endTime'     => substr($r->hora_fin,    0, 8),
                'startRecur'  => null,                   /** opcional si quieres acotar un rango */
                'endRecur'    => null,
                'extendedProps' => [
                    'subject_id'  => $r->subject_id,
                    'teacher_id'  => $r->teacher_id,
                    'es_lab'      => intval($r->es_lab) === 1,
                    'espacio'     => $espacio,
                    'aula_id'     => $r->salon_id,
                    'laboratorio_id' => $r->laboratorio_id,
                ],
            ];
        })->values();

        return response()->json($events);
    }

    /** ============================================================
     *  Helpers
     *  ============================================================ */

    /** ===== Helpers ===== **/

    protected function canonicalDay(?string $d): string
    {
        $k = mb_strtolower(trim((string)$d), 'UTF-8');
        return match ($k) {
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles', 'miércoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sabado', 'sábado' => 'Sábado',
            'domingo' => 'Domingo',
            default => 'Lunes',
        };
    }

    protected function boundsFromConfig(array $dispTurno, array $dias): array
    {
        $mins = []; $maxs = [];
        foreach ($dias as $d) {
            if (!isset($dispTurno[$d])) continue;
            $slots = isset($dispTurno[$d]['start']) ? [$dispTurno[$d]] : $dispTurno[$d];
            foreach ($slots as $s) {
                $mins[] = $s['start'] ?? null;
                $maxs[] = $s['end']   ?? null;
            }
        }
        $mins = array_filter($mins); $maxs = array_filter($maxs);
        return [ $mins ? min($mins) : null, $maxs ? max($maxs) : null ];
    }

    protected function boundsFromAssignments($rows): array
    {
        if (!$rows || $rows->isEmpty()) return [null, null];
        $mins = $rows->pluck('start_time')->filter()->all();
        $maxs = $rows->pluck('end_time')->filter()->all();
        return [ $mins ? min($mins) : null, $maxs ? max($maxs) : null ];
    }

    protected function minTime(array $cands): string
    {
        $cands = array_filter($cands);
        return $cands ? min($cands) : '07:00:00';
    }

    protected function maxTime(array $cands): string
    {
        $cands = array_filter($cands);
        return $cands ? max($cands) : '20:00:00';
    }

    protected function buildHourSlots(string $start, string $end, int $stepMinutes = 60): array
    {
        $out = [];
        $t = \Carbon\Carbon::createFromFormat('H:i:s', $start);
        $E = \Carbon\Carbon::createFromFormat('H:i:s', $end);
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
}
