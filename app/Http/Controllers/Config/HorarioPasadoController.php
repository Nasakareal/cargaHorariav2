<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\ActividadGeneral;

class HorarioPasadoController extends Controller
{
    /** Listado de “paquetes” (agrupados por fecha de registro) */
    public function index()
    {
        $paquetes = DB::table('schedule_history')
            ->selectRaw('DATE(fecha_registro) AS fecha, COUNT(*) AS total, MAX(quarter_name_en) AS quarter_name_en')
            ->groupByRaw('DATE(fecha_registro)')
            ->orderByDesc('fecha')
            ->get();

        return view('configuracion.horarios_pasados.index', compact('paquetes'));
    }

    /** Vista de confirmación para archivar todo lo actual */
    public function create()
    {
        $actuales = DB::table('schedule_assignments')->count();
        return view('configuracion.horarios_pasados.create', compact('actuales'));
    }

    /**
     * Archiva: copia schedule_assignments -> schedule_history y limpia schedule_assignments.
     * Inserta snapshots (teacher_name, group_name, subject_name) SOLO si las columnas existen.
     */
    public function store(Request $request)
    {
        $this->authorize('crear horarios pasados');

        $request->validate([
            'quarter_name_en' => 'nullable|string|max:255',
            'confirm'         => 'required|in:1',
        ], [
            'confirm.in' => 'Debes confirmar la acción.',
        ]);

        // Detecta si existen columnas snapshot en schedule_history
        $hasTeacherName = Schema::hasColumn('schedule_history', 'teacher_name');
        $hasGroupName   = Schema::hasColumn('schedule_history', 'group_name');
        $hasSubjectName = Schema::hasColumn('schedule_history', 'subject_name');

        try {
            DB::beginTransaction();

            // Construcción de columnas destino
            $baseCols = [
                'schedule_id','subject_id','teacher_id','group_id',
                'classroom_id','lab_id','start_time','end_time',
                'schedule_day','estado','fyh_creacion','fyh_actualizacion',
                'tipo_espacio','quarter_name_en','fecha_registro',
            ];

            $destCols = $baseCols;
            if ($hasTeacherName) $destCols[] = 'teacher_name';
            if ($hasGroupName)   $destCols[] = 'group_name';
            if ($hasSubjectName) $destCols[] = 'subject_name';

            // SELECT base
            $selectBase = "
                sa.schedule_id, sa.subject_id, sa.teacher_id, sa.group_id,
                sa.classroom_id, sa.lab_id, sa.start_time, sa.end_time,
                sa.schedule_day, sa.estado, sa.fyh_creacion, sa.fyh_actualizacion,
                sa.tipo_espacio, ? AS quarter_name_en, NOW() AS fecha_registro
            ";

            // SELECT snapshots (si existen columnas destino)
            $selectSnap = '';
            if ($hasTeacherName || $hasGroupName || $hasSubjectName) {
                // LEFT JOIN a tablas actuales para “congelar” nombres
                $selectSnap =
                    ($hasTeacherName ? ", t.teacher_name" : "") .
                    ($hasGroupName   ? ", g.group_name"   : "") .
                    ($hasSubjectName ? ", s.subject_name" : "");
            }

            $sql = "
                INSERT INTO schedule_history (" . implode(',', $destCols) . ")
                SELECT
                    {$selectBase}
                    {$selectSnap}
                FROM schedule_assignments sa
                " . (($hasTeacherName || $hasGroupName || $hasSubjectName) ? "
                    LEFT JOIN teachers t ON t.teacher_id = sa.teacher_id
                    LEFT JOIN `groups` g ON g.group_id   = sa.group_id
                    LEFT JOIN subjects s ON s.subject_id = sa.subject_id
                " : "") . "
            ";

            DB::statement($sql, [$request->input('quarter_name_en')]);

            // Limpia los horarios actuales para nuevo periodo
            DB::table('schedule_assignments')->delete();

            ActividadGeneral::registrar('ARCHIVAR_HORARIO', 'schedule_history', null, 'Archivó horarios actuales y limpió tabla de trabajo.');

            DB::commit();

            return redirect()
                ->route('configuracion.horarios-pasados.index')
                ->with('success', 'Horarios archivados correctamente y tabla actual limpiada.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Archivar horarios', ['msg' => $e->getMessage()]);
            return back()->with('error', 'No se pudo archivar: ' . $e->getMessage());
        }
    }

    /** Muestra un paquete específico (por fecha) con filtros grupo/profesor */
    public function show(Request $request, $fecha)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha)) {
            return redirect()->route('configuracion.horarios-pasados.index')
                ->with('error', 'Paquete inválido.');
        }

        $grupos = DB::table('groups')->select('group_id','group_name')->orderBy('group_name')->get();
        $profes = DB::table('teachers')->select('teacher_id','teacher_name')->orderBy('teacher_name')->get();

        $groupId   = (int) $request->query('group_id', 0);
        $teacherId = (int) $request->query('teacher_id', 0);

        // ¿Existen columnas snapshot?
        $hasTeacherName = Schema::hasColumn('schedule_history', 'teacher_name');
        $hasSubjectName = Schema::hasColumn('schedule_history', 'subject_name');

        $q = DB::table('schedule_history as sh')
            // Mantén LEFT JOINs para cuando SÍ existan maestros/materias actuales,
            // pero usa COALESCE para privilegiar el snapshot.
            ->leftJoin('subjects as s', 's.subject_id', '=', 'sh.subject_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'sh.teacher_id')
            ->whereRaw('DATE(sh.fecha_registro) = ?', [$fecha]);

        if ($groupId > 0) {
            $q->where('sh.group_id', $groupId);
        } elseif ($teacherId > 0) {
            $q->where('sh.teacher_id', $teacherId);
        }

        // Selección preferente de snapshots si existen
        $selectSubject = $hasSubjectName ? DB::raw('COALESCE(sh.subject_name, s.subject_name) as subject_name')
                                         : DB::raw('s.subject_name');
        $selectTeacher = $hasTeacherName ? DB::raw('COALESCE(sh.teacher_name, t.teacher_name) as teacher_name')
                                         : DB::raw('t.teacher_name');

        $registros = $q
            ->orderByRaw("FIELD(sh.schedule_day,'Lunes','Martes','Miércoles','Miercoles','Jueves','Viernes','Sábado','Sabado','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')")
            ->orderBy('sh.start_time')
            ->get([
                'sh.schedule_day','sh.start_time','sh.end_time',
                $selectSubject, $selectTeacher
            ]);

        // ===== Normalización de días y armado de tabla =====
        $horas = [];
        $diasCanon = [];
        $filas = [];

        foreach ($registros as $r) {
            $hora = date('H:i', strtotime((string)$r->start_time));
            $dia  = $this->canonizarDia((string)$r->schedule_day);

            if (!in_array($hora, $horas, true))     $horas[] = $hora;
            if (!in_array($dia,  $diasCanon, true)) $diasCanon[] = $dia;

            $filas[] = [
                'hora' => $hora,
                'dia'  => $dia,
                'contenido' => 'Materia: '.e($r->subject_name ?? '-').'<br>Profesor: '.e($r->teacher_name ?? '-'),
            ];
        }

        sort($horas);
        $ordenDias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
        $diasCanon = array_values(array_intersect($ordenDias, $diasCanon));

        $tabla = [];
        foreach ($horas as $h) {
            foreach ($diasCanon as $d) {
                $tabla[$h][$d] = '';
            }
        }

        foreach ($filas as $f) {
            if (!isset($tabla[$f['hora']][$f['dia']])) continue;
            $tabla[$f['hora']][$f['dia']] = $tabla[$f['hora']][$f['dia']]
                ? ($tabla[$f['hora']][$f['dia']] . '<hr>' . $f['contenido'])
                : $f['contenido'];
        }

        $cuatri = DB::table('schedule_history')
            ->whereRaw('DATE(fecha_registro) = ?', [$fecha])
            ->max('quarter_name_en');

        return view('configuracion.horarios_pasados.show', [
            'fecha'       => $fecha,
            'grupos'      => $grupos,
            'profes'      => $profes,
            'groupId'     => $groupId,
            'teacherId'   => $teacherId,
            'horas'       => $horas,
            'dias'        => $diasCanon,
            'tabla'       => $tabla,
            'quarterName' => $cuatri,
        ]);
    }

    /** Editar nombre (quarter_name_en) del paquete (por fecha) */
    public function edit($fecha)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha)) {
            return redirect()->route('configuracion.horarios-pasados.index')
                ->with('error', 'Paquete inválido.');
        }

        $cuatri = DB::table('schedule_history')
            ->whereDate('fecha_registro', $fecha)
            ->max('quarter_name_en');

        return view('configuracion.horarios_pasados.edit', [
            'fecha'       => $fecha,
            'quarterName' => $cuatri,
        ]);
    }

    /** Actualiza el nombre para TODOS los registros de ese paquete (fecha) */
    public function update(Request $request, $fecha)
    {
        $this->authorize('editar horarios pasados');

        $request->validate([
            'quarter_name_en' => 'required|string|max:255',
        ], [
            'quarter_name_en.required' => 'El cuatrimestre (en inglés) es obligatorio.',
        ]);

        try {
            $af = DB::table('schedule_history')
                ->whereDate('fecha_registro', $fecha)
                ->update([
                    'quarter_name_en'   => $request->quarter_name_en,
                    'fyh_actualizacion' => now(),
                ]);

            ActividadGeneral::registrar(
                'RENOMBRAR_PAQUETE',
                'schedule_history',
                null,
                "Renombró paquete {$fecha} a '{$request->quarter_name_en}' ({$af} filas)."
            );

            return redirect()
                ->route('configuracion.horarios-pasados.index')
                ->with('success', 'Nombre del paquete actualizado.');
        } catch (\Throwable $e) {
            Log::error('Update paquete', ['msg' => $e->getMessage()]);
            return back()->with('error', 'No se pudo actualizar el nombre del paquete.');
        }
    }

    /** Elimina un paquete completo (por fecha) */
    public function destroy($fecha)
    {
        $this->authorize('eliminar horarios pasados');

        try {
            $af = DB::table('schedule_history')->whereDate('fecha_registro', $fecha)->delete();

            ActividadGeneral::registrar(
                'ELIMINAR_PAQUETE',
                'schedule_history',
                null,
                "Eliminó paquete {$fecha} ({$af} filas)."
            );

            return redirect()->route('configuracion.horarios-pasados.index')
                ->with('success', 'Paquete eliminado.');
        } catch (\Throwable $e) {
            Log::error('Destroy paquete', ['msg' => $e->getMessage()]);
            return back()->with('error', 'No se pudo eliminar el paquete.');
        }
    }

    private function canonizarDia(string $d): string
    {
        $k = mb_strtolower(trim($d), 'UTF-8');
        $map = [
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miércoles' => 'Miércoles',
            'miercoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sábado' => 'Sábado',
            'sabado' => 'Sábado',
            'domingo' => 'Domingo',
            'monday' => 'Lunes',
            'tuesday' => 'Martes',
            'wednesday' => 'Miércoles',
            'thursday' => 'Jueves',
            'friday' => 'Viernes',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo',
        ];
        return $map[$k] ?? 'Lunes';
    }
}
