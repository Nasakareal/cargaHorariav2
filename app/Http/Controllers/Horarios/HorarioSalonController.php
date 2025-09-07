<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HorarioSalonController extends Controller
{
    protected string $T_HORARIOS = 'schedule_assignments';
    protected string $T_MATERIAS = 'subjects';
    protected string $T_DOCENTES = 'teachers';
    protected string $T_AULAS    = 'classrooms';
    protected string $T_LABS     = 'labs';

    public function index(Request $request)
    {
        $q    = trim((string) $request->get('q', ''));
        $tipo = trim((string) $request->get('tipo', ''));

        $schema = DB::getSchemaBuilder();
        $aulaCols = array_values(array_filter(['classroom_name','nombre','name'], fn($c) => $schema->hasColumn($this->T_AULAS, $c)));
        $labCols  = array_values(array_filter(['lab_name','nombre','name'],        fn($c) => $schema->hasColumn($this->T_LABS,  $c)));

        $aulaBase = $aulaCols
            ? 'COALESCE('.implode(', ', array_map(fn($c)=>"a.$c", $aulaCols)).')'
            : "CONCAT('Aula ', a.classroom_id)";

        $labBase  = $labCols
            ? 'COALESCE('.implode(', ', array_map(fn($c)=>"l.$c", $labCols)).')'
            : "CONCAT('Lab ', l.lab_id)";

        $aulaExpr = $schema->hasColumn($this->T_AULAS, 'building')
            ? "CONCAT(COALESCE(RIGHT(a.building,1), ''), ' ', ($aulaBase))"
            : $aulaBase;

        $labExpr  = $schema->hasColumn($this->T_LABS, 'building')
            ? "CONCAT(($labBase), COALESCE(RIGHT(l.building,1), ''))"
            : $labBase;

        $aulas = DB::table($this->T_AULAS.' as a')
            ->selectRaw("a.classroom_id as id, {$aulaExpr} as nombre, 'aula' as tipo")
            ->when($q !== '', function ($qq) use ($aulaCols, $aulaBase, $schema) {
                $qq->where(function($w) use ($aulaCols, $aulaBase, $schema) {
                    foreach ($aulaCols as $c) {
                        $w->orWhere("a.$c", 'like', '%'.request('q').'%');
                    }
                    if ($schema->hasColumn('classrooms', 'building')) {
                        $w->orWhereRaw("CONCAT(COALESCE(RIGHT(a.building,1), ''), ' ', ($aulaBase)) LIKE ?",['%'.request('q').'%']);
                    }
                });
            })
            ->get();

        $labs = DB::table($this->T_LABS.' as l')
            ->selectRaw("l.lab_id as id, {$labExpr} as nombre, 'lab' as tipo")
            ->when($q !== '', function ($qq) use ($labCols, $labBase, $schema) {
                $qq->where(function($w) use ($labCols, $labBase, $schema) {
                    foreach ($labCols as $c) {
                        $w->orWhere("l.$c", 'like', '%'.request('q').'%');
                    }
                    if ($schema->hasColumn('labs', 'building')) {
                        $w->orWhereRaw("CONCAT(($labBase), COALESCE(RIGHT(l.building,1), '')) LIKE ?", ['%'.request('q').'%']);
                    }
                });
            })
            ->get();

        $espacios = $tipo === 'aula' ? $aulas : ($tipo === 'lab' ? $labs : $aulas->merge($labs));
        $espacios = $espacios->sortBy('nombre', SORT_NATURAL|SORT_FLAG_CASE)->values();

        return view('horarios.salones.index', [
            'espacios' => $espacios,
            'filtros'  => ['q' => $q, 'tipo' => $tipo],
        ]);
    }


    /**
     * Muestra el horario de un espacio específico (aula o lab) en tabla.
     */
    public function show(string $tipo, int $espacio_id)
    {
        abort_unless(in_array($tipo, ['aula', 'lab'], true), 404, 'Tipo de espacio inválido');

        $schema = DB::getSchemaBuilder();
        $subCols = array_values(array_filter(['subject_name','nombre','name'], fn($c) => $schema->hasColumn($this->T_MATERIAS, $c)));
        $teaCols = array_values(array_filter(['teacher_name','nombre_completo','full_name','name','nombre'], fn($c) => $schema->hasColumn($this->T_DOCENTES, $c)));
        $aulaCols= array_values(array_filter(['classroom_name','nombre','name'], fn($c) => $schema->hasColumn($this->T_AULAS, $c)));
        $labCols = array_values(array_filter(['lab_name','nombre','name'], fn($c) => $schema->hasColumn($this->T_LABS, $c)));

        $materiaExpr = $subCols ? 'COALESCE('.implode(', ', array_map(fn($c)=>"m.$c", $subCols)).')' : "''";
        $docenteExpr = $teaCols ? 'COALESCE('.implode(', ', array_map(fn($c)=>"t.$c", $teaCols)).')'     : "''";

        $aulaBase = $aulaCols ? 'COALESCE('.implode(', ', array_map(fn($c)=>"a.$c", $aulaCols)).')' : "CONCAT('Aula ', a.classroom_id)";
        $labBase  = $labCols  ? 'COALESCE('.implode(', ', array_map(fn($c)=>"l.$c", $labCols)).')'  : "CONCAT('Lab ', l.lab_id)";

        $aulaExpr = $schema->hasColumn($this->T_AULAS, 'building')
            ? "CONCAT(($aulaBase), COALESCE(RIGHT(a.building,1), ''))"
            : $aulaBase;

        $labExpr  = $schema->hasColumn($this->T_LABS, 'building')
            ? "CONCAT(($labBase), COALESCE(RIGHT(l.building,1), ''))"
            : $labBase;


        if ($tipo === 'aula') {
            $espacio = DB::table($this->T_AULAS.' as a')
                ->where('a.classroom_id', $espacio_id)
                ->selectRaw("a.classroom_id as id, {$aulaExpr} as nombre, 'aula' as tipo")
                ->first();
        } else {
            $espacio = DB::table($this->T_LABS.' as l')
                ->where('l.lab_id', $espacio_id)
                ->selectRaw("l.lab_id as id, {$labExpr} as nombre, 'lab' as tipo")
                ->first();
        }
        abort_unless($espacio, 404, 'Espacio no encontrado');

        $rows = DB::table($this->T_HORARIOS.' as s')
            ->leftJoin($this->T_MATERIAS.' as m', 'm.subject_id', '=', 's.subject_id')
            ->leftJoin($this->T_DOCENTES.' as t', 't.teacher_id', '=', 's.teacher_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 's.group_id')
            ->when($tipo === 'aula', fn($q) => $q->where('s.classroom_id', $espacio_id))
            ->when($tipo === 'lab',  fn($q) => $q->where('s.lab_id', $espacio_id))
            ->when($schema->hasColumn($this->T_HORARIOS,'estado'), function($q){
                $q->whereIn('s.estado', ['1','ACTIVO','activo']);
            })
            ->selectRaw("
                s.assignment_id,
                s.schedule_day,
                s.start_time, s.end_time,
                s.subject_id, {$materiaExpr} as materia,
                s.teacher_id, {$docenteExpr} as docente,
                s.group_id, g.group_name
            ")
            ->orderBy('s.start_time')
            ->get();

        $dias  = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        [$minAsg, $maxAsg] = $this->boundsFromAssignments($rows);
        $min = $this->minTime([$minAsg, '07:00:00']);
        $max = $this->maxTime([$maxAsg, '20:00:00']);
        $horas = $this->buildHourSlots($min, $max, 60);

        $tabla = [];
        foreach ($horas as $h) foreach ($dias as $d) { $tabla[$h][$d] = ''; }

        foreach ($rows as $r) {
            $dia = $this->canonicalDay($r->schedule_day);
            if (!in_array($dia, $dias, true)) continue;

            foreach ($horas as $hLabel) {
                [$hStart, $hEnd] = explode(' - ', $hLabel);
                $hStart .= ':00'; $hEnd .= ':00';

                if (!$this->overlaps($hStart, $hEnd, $r->start_time, $r->end_time)) continue;

                $materia = $r->materia ?: 'Materia';
                $grupo   = $r->group_name ? "Grupo {$r->group_name}" : 'Grupo —';
                $doc     = $r->docente ?: 'Sin profesor';

                $linea = e($materia).' — '.e($grupo).' — '.e($doc);
                $tabla[$hLabel][$dia] = trim($tabla[$hLabel][$dia]) === '' ? $linea : $tabla[$hLabel][$dia].'<br>'.$linea;
            }
        }

        $espacios = $this->listaEspaciosParaSelector();

        return view('horarios.salones.show', [
            'espacio' => $espacio,
            'tipo'    => $tipo,
            'dias'    => $dias,
            'horas'   => $horas,
            'tabla'   => $tabla,
            'espacios'=> $espacios,
        ]);
    }

    /**
     * Eventos (json) para FullCalendar de un espacio (aula/lab).
     */
    public function eventos(string $tipo, int $espacio_id)
    {
        abort_unless(in_array($tipo, ['aula','lab'], true), 404, 'Tipo inválido');

        $schema = DB::getSchemaBuilder();

        $subCols = array_values(array_filter(['subject_name','nombre','name'], fn($c) => $schema->hasColumn($this->T_MATERIAS, $c)));
        $teaCols = array_values(array_filter(['teacher_name','nombre_completo','full_name','name','nombre'], fn($c) => $schema->hasColumn($this->T_DOCENTES, $c)));

        $materiaExpr = $subCols ? 'COALESCE('.implode(', ', array_map(fn($c)=>"m.$c", $subCols)).')' : "''";
        $docenteExpr = $teaCols ? 'COALESCE('.implode(', ', array_map(fn($c)=>"t.$c", $teaCols)).')'     : "''";

        $rows = DB::table($this->T_HORARIOS.' as s')
            ->leftJoin($this->T_MATERIAS.' as m', 'm.subject_id', '=', 's.subject_id')
            ->leftJoin($this->T_DOCENTES.' as t', 't.teacher_id', '=', 's.teacher_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 's.group_id')
            ->when($tipo === 'aula', fn($q) => $q->where('s.classroom_id', $espacio_id))
            ->when($tipo === 'lab',  fn($q) => $q->where('s.lab_id',       $espacio_id))
            ->when($schema->hasColumn($this->T_HORARIOS,'estado'), function($q){
                $q->whereIn('s.estado', ['1','ACTIVO','activo']);
            })
            ->selectRaw("
                s.schedule_day,
                s.start_time, s.end_time,
                s.subject_id, {$materiaExpr} as materia,
                s.teacher_id, {$docenteExpr} as docente,
                s.group_id, g.group_name
            ")
            ->orderBy('s.schedule_day')
            ->orderBy('s.start_time')
            ->get();

        $events = $rows->map(function($r){
            $dow = $this->dayToDow($r->schedule_day); // 0..6
            $titulo = trim(($r->materia ?: 'Materia')
                        . ($r->group_name ? " • Grupo {$r->group_name}" : '')
                        . ($r->docente ? " • {$r->docente}" : ''));

            return [
                'title'         => $titulo,
                'daysOfWeek'    => [$dow], // 0=domingo ... 6=sábado
                'startTime'     => substr($r->start_time ?? '00:00:00', 0, 8),
                'endTime'       => substr($r->end_time   ?? '00:00:00', 0, 8),
                'startRecur'    => null,
                'endRecur'      => null,
                'extendedProps' => [
                    'subject_id' => $r->subject_id,
                    'teacher_id' => $r->teacher_id,
                    'group_id'   => $r->group_id,
                ],
            ];
        })->values();

        return response()->json($events);
    }

    /** ================= Helpers ================= */
    protected function listaEspaciosParaSelector()
    {
        $schema = DB::getSchemaBuilder();
        $aulaCols = array_values(array_filter(['classroom_name','nombre','name'], fn($c) => $schema->hasColumn($this->T_AULAS, $c)));
        $labCols  = array_values(array_filter(['lab_name','nombre','name'],        fn($c) => $schema->hasColumn($this->T_LABS,  $c)));
        $aulaBase = $aulaCols
            ? 'COALESCE('.implode(', ', array_map(fn($c)=>"a.$c", $aulaCols)).')'
            : "CONCAT('Aula ', a.classroom_id)";

        $labBase  = $labCols
            ? 'COALESCE('.implode(', ', array_map(fn($c)=>"l.$c", $labCols)).')'
            : "CONCAT('Lab ', l.lab_id)";

        $aulaExpr = $schema->hasColumn($this->T_AULAS, 'building')
            ? "CONCAT(($aulaBase), COALESCE(RIGHT(a.building,1), ''))"
            : $aulaBase;

        $labExpr  = $schema->hasColumn($this->T_LABS, 'building')
            ? "CONCAT(($labBase), COALESCE(RIGHT(l.building,1), ''))"
            : $labBase;

        $aulas = DB::table($this->T_AULAS.' as a')
            ->selectRaw("a.classroom_id as id, {$aulaExpr} as nombre, 'aula' as tipo")
            ->get();

        $labs = DB::table($this->T_LABS.' as l')
            ->selectRaw("l.lab_id as id, {$labExpr} as nombre, 'lab' as tipo")
            ->get();

        return $aulas->merge($labs)
            ->sortBy('nombre', SORT_NATURAL|SORT_FLAG_CASE)
            ->values();
    }


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

    protected function dayToDow(?string $d): int
    {
        // Devuelve 0..6 (FullCalendar)
        $c = $this->canonicalDay($d);
        return match ($c) {
            'Domingo'   => 0,
            'Lunes'     => 1,
            'Martes'    => 2,
            'Miércoles' => 3,
            'Jueves'    => 4,
            'Viernes'   => 5,
            'Sábado'    => 6,
            default     => 1,
        };
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
}
