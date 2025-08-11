<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WelcomeController extends Controller
{
    // Público: NO requiere auth
    public function index(Request $request)
    {
        // ===== A) Tabla de grupos con materias sin profesor =====
        // group_subjects = lo que debería existir (plan de grupo)
        // teacher_subjects = lo asignado a un profesor
        $grupos = DB::table('groups as g')
            ->join('group_subjects as gs', 'gs.group_id', '=', 'g.group_id')
            ->join('subjects as s', 's.subject_id', '=', 'gs.subject_id')
            ->leftJoin('teacher_subjects as ts', function ($q) {
                $q->on('ts.group_id',  '=', 'g.group_id')
                  ->on('ts.subject_id','=', 's.subject_id');
            })
            ->groupBy('g.group_id','g.group_name')
            ->orderBy('g.group_name')
            ->select([
                'g.group_id',
                'g.group_name',
                DB::raw('GROUP_CONCAT(CASE WHEN ts.teacher_id IS NULL THEN s.subject_name END ORDER BY s.subject_name SEPARATOR ", ") AS materias_faltantes'),
                DB::raw('SUM(CASE WHEN ts.teacher_id IS NULL THEN 1 ELSE 0 END) AS materias_no_cubiertas'),
                DB::raw('SUM(CASE WHEN ts.teacher_id IS NOT NULL THEN 1 ELSE 0 END) AS materias_asignadas'),
                DB::raw('COUNT(s.subject_id) AS total_materias'),
            ])
            ->get();

        $gruposConFaltantes = $grupos->filter(fn($g) => (int)$g->materias_no_cubiertas > 0)->values();

        // ===== B) Horario público por grupo (selector) =====
        $todosGrupos = DB::table('groups')
            ->orderBy('group_name')
            ->get(['group_id','group_name']);

        // group_id desde query (?group_id=)
        $groupId = (int) $request->query('group_id', 0);
        if ($groupId <= 0 && $todosGrupos->count() > 0) {
            $groupId = (int) $todosGrupos->first()->group_id;
        }

        // ✅ usar la tabla 'shifts' que sí existe
        $grupoSel = $groupId
            ? DB::table('groups as g')
                ->leftJoin('shifts as sh', 'sh.shift_id', '=', 'g.turn_id')
                ->where('g.group_id', $groupId)
                ->first([
                    'g.group_id',
                    'g.group_name',
                    DB::raw('sh.shift_name as turn_name'), // alias para no tocar la vista
                ])
            : null;


        // Días/horas a mostrar (ajusta si usas domingo o distinto rango)
        $dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $horas = [];
        for ($h = 7; $h < 22; $h++) {
            $ini = sprintf('%02d:00', $h);
            $fin = sprintf('%02d:00', $h+1);
            $horas[] = "{$ini} - {$fin}";
        }

        // Base de la tabla
        $tabla = [];
        foreach ($horas as $hLabel) {
            $tabla[$hLabel] = [];
            foreach ($dias as $d) $tabla[$hLabel][$d] = '';
        }

        if ($groupId) {
            // Trae asignaciones del grupo con materia/profe
            $asigs = DB::table('schedule_assignments as sa')
                ->leftJoin('subjects as s','s.subject_id','=','sa.subject_id')
                ->leftJoin('teachers as t','t.teacher_id','=','sa.teacher_id')
                ->where('sa.group_id', $groupId)
                ->where('sa.estado','activo')
                ->orderBy('sa.schedule_day')
                ->orderBy('sa.start_time')
                ->get([
                    'sa.schedule_day','sa.start_time','sa.end_time',
                    's.subject_name','t.teacher_name','sa.teacher_id'
                ]);

            // Llena por bloques de 1h
            foreach ($asigs as $a) {
                $day = $a->schedule_day;
                if (!in_array($day, $dias, true)) continue;

                try {
                    $start = Carbon::createFromFormat('H:i:s', $a->start_time);
                    $end   = Carbon::createFromFormat('H:i:s', $a->end_time);
                } catch (\Throwable $e) { continue; }

                // Para cada hora completa cubierta por la asignación
                $c = $start->copy();
                while ($c->lt($end)) {
                    $slotIni = $c->format('H:i');
                    $slotFin = $c->copy()->addHour()->format('H:i');
                    $label   = "{$slotIni} - {$slotFin}";
                    if (!isset($tabla[$label][$day])) { $c->addHour(); continue; }

                    $materia = $a->subject_name ?? '—';
                    $prof    = $a->teacher_name ?: 'Sin profesor';
                    $text    = e($materia).' <br><small>'.e($prof).'</small>';

                    // Si ya hay algo, añadimos (raro que choquen)
                    if (trim($tabla[$label][$day]) !== '') {
                        $tabla[$label][$day] .= '<hr>'.$text;
                    } else {
                        $tabla[$label][$day] = $text;
                    }

                    $c->addHour();
                }
            }
        }

        return view('welcome', [
            // Tabla de faltantes
            'grupos_con_faltantes' => $gruposConFaltantes,
            'total_grupos_faltantes' => $gruposConFaltantes->count(),
            'total_materias_faltantes' => (int)$gruposConFaltantes->sum('materias_no_cubiertas'),

            // Horario público
            'grupos'   => $todosGrupos,
            'group_id' => $groupId,
            'grupoSel' => $grupoSel,
            'dias'     => $dias,
            'horas'    => $horas,
            'tabla'    => $tabla,
        ]);
    }
}
