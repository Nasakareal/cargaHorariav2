<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // Podrías mostrar un resumen: cuántos registros actuales hay en schedule_assignments
        $actuales = DB::table('schedule_assignments')->count();
        return view('configuracion.horarios_pasados.create', compact('actuales'));
    }

    /** Archiva: copia schedule_assignments -> schedule_history y limpia schedule_assignments */
    public function store(Request $request)
    {
        $this->authorize('crear horarios pasados');

        $request->validate([
            'quarter_name_en' => 'nullable|string|max:255',
            'confirm'         => 'required|in:1',
        ], [
            'confirm.in' => 'Debes confirmar la acción.',
        ]);

        try {
            DB::beginTransaction();

            // Inserta TODO el horario actual en el historial
            // Nota: usamos los nombres de columnas tal como tu PHP puro.
            DB::statement("
                INSERT INTO schedule_history
                    (schedule_id, subject_id, teacher_id, group_id, classroom_id, lab_id, start_time, end_time, schedule_day, estado, fyh_creacion, fyh_actualizacion, tipo_espacio, quarter_name_en, fecha_registro)
                SELECT
                    schedule_id, subject_id, teacher_id, group_id, classroom_id, lab_id, start_time, end_time, schedule_day, estado, fyh_creacion, fyh_actualizacion, tipo_espacio, ?, NOW()
                FROM schedule_assignments
            ", [$request->input('quarter_name_en')]);

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

        $q = DB::table('schedule_history as sh')
            ->leftJoin('subjects as s', 's.subject_id', '=', 'sh.subject_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'sh.teacher_id')
            ->whereRaw('DATE(sh.fecha_registro) = ?', [$fecha]);

        if ($groupId > 0) {
            $q->where('sh.group_id', $groupId);
        } elseif ($teacherId > 0) {
            $q->where('sh.teacher_id', $teacherId);
        }

        $registros = $q
            ->orderByRaw("FIELD(sh.schedule_day,'Lunes','Martes','Miércoles','Miercoles','Jueves','Viernes','Sábado','Sabado','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')")
            ->orderBy('sh.start_time')
            ->get([
                'sh.schedule_day','sh.start_time','sh.end_time',
                's.subject_name','t.teacher_name'
            ]);

        // ===== Normalización de días y armado de tabla =====
        $horas = [];
        $diasCanon = [];   // días ya normalizados (español)
        $filas = [];

        foreach ($registros as $r) {
            $hora = date('H:i', strtotime((string)$r->start_time));
            $dia  = $this->canonizarDia((string)$r->schedule_day);

            if (!in_array($hora, $horas, true))      $horas[] = $hora;
            if (!in_array($dia,  $diasCanon, true))  $diasCanon[] = $dia;

            $filas[] = [
                'hora' => $hora,
                'dia'  => $dia,
                'contenido' => 'Materia: '.e($r->subject_name ?? '-').'<br>Profesor: '.e($r->teacher_name ?? '-'),
            ];
        }

        sort($horas);
        $ordenDias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
        // Mantén sólo los días presentes, respetando el orden deseado
        $diasCanon = array_values(array_intersect($ordenDias, $diasCanon));

        // Matriz vacía
        $tabla = [];
        foreach ($horas as $h) {
            foreach ($diasCanon as $d) {
                $tabla[$h][$d] = '';
            }
        }

        // Rellenar matriz
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
            'dias'        => $diasCanon,   // <- ya normalizados
            'tabla'       => $tabla,
            'quarterName' => $cuatri,
        ]);
    }


    /** Editar “nombre” (quarter_name_en) del paquete (por fecha) */
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
                    'quarter_name_en' => $request->quarter_name_en,
                    'fyh_actualizacion' => now(),
                ]);

            ActividadGeneral::registrar('RENOMBRAR_PAQUETE', 'schedule_history', null, "Renombró paquete {$fecha} a '{$request->quarter_name_en}' ({$af} filas).");

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

            ActividadGeneral::registrar('ELIMINAR_PAQUETE', 'schedule_history', null, "Eliminó paquete {$fecha} ({$af} filas).");

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
        return $map[$k] ?? 'Lunes'; // fallback razonable
    }

}
