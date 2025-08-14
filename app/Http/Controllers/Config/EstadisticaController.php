<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use ZipArchive;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EstadisticaController extends Controller
{
    private string $templatePath;

    /** Columnas por día (coincide con tus scripts) */
    private array $mapDias = [
        'Lunes'      => 'B',
        'Martes'     => 'C',
        'Miércoles'  => 'D',
        'Miercoles'  => 'D', // por si viene sin tilde
        'Jueves'     => 'E',
        'Viernes'    => 'F',
        'Sábado'     => 'G',
        'Sabado'     => 'G', // por si viene sin tilde
    ];

    /** Filas por hora (Profesor y Grupos “completos”) */
    private array $mapHoras = [
        '07:00' => 6,
        '08:00' => 7,
        '09:00' => 8,
        '10:00' => 9,
        '11:00' => 10,
        '12:00' => 11,
        '13:00' => 12,
        '14:00' => 13,
        '15:00' => 14,
        '16:00' => 15,
        '17:00' => 16,
        '18:00' => 17,
        '19:00' => 18,
    ];

    /** Filas por hora (Grupos SIN profesor — plantilla distinta) */
    private array $mapHorasIncompletos = [
        '07:00' => 7,
        '08:00' => 10,
        '09:00' => 13,
        '10:00' => 16,
        '11:00' => 19,
        '12:00' => 22,
        '13:00' => 25,
        '14:00' => 28,
        '15:00' => 31,
        '16:00' => 34,
        '17:00' => 37,
        '18:00' => 40,
        '19:00' => 43,
    ];

    public function __construct()
    {
        $this->templatePath = resource_path('plantillas/plantilla.xlsx');
    }

    public function index()
    {
        return view('configuracion.estadisticas.index');
    }

    /** ===================== EXPORTS ===================== */

    /** ZIP: un Excel por PROFESOR (como tu script 1) */
    public function exportHorariosProfesores()
    {
        // Evita timeout y da aire a PhpSpreadsheet
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

        $profes = DB::table('schedule_assignments as sa')
            ->join('teachers as t', 't.teacher_id', '=', 'sa.teacher_id')
            ->select('t.teacher_id', 't.teacher_name', 't.clasificacion')
            ->distinct()
            ->orderBy('t.teacher_name')
            ->get();

        if ($profes->isEmpty()) {
            return redirect()->route('configuracion.estadisticas.index')
                ->with('error', 'No se encontraron profesores con horarios.');
        }

        $zipName = 'Horarios_Por_Profesor_'.now()->format('Ymd_His').'.zip';
        $zipPath = storage_path('app/tmp/'.$zipName);
        $this->ensureDir(dirname($zipPath));

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return redirect()->route('configuracion.estadisticas.index')
                ->with('error', 'No se pudo crear el ZIP en el servidor.');
        }

        foreach ($profes as $p) {
            $rows = $this->fetchHorariosProfesor($p->teacher_id);
            if (empty($rows)) { continue; }

            $totalHoras = 0;
            foreach ($rows as $r) {
                $totalHoras += $this->diffHoras($r['start_time'], $r['end_time']);
            }

            $xlsxPath = $this->buildProfesorXlsx(
                teacherName: $p->teacher_name,
                clasificacion: $p->clasificacion ?: 'Sin clasificar',
                totalHoras: $totalHoras,
                horarios: $rows
            );

            if (is_file($xlsxPath)) {
                $zip->addFile($xlsxPath, basename($xlsxPath));
            }
        }

        $zip->close();
        $this->cleanupDir(storage_path('app/tmp/xlsx_prof'));

        // Devuelve descarga (puede tardar: ya no hay timeout)
        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }


    /** ZIP: un Excel por GRUPO (como tu script 2) */
    public function exportHorariosGrupos(): BinaryFileResponse
    {
        $rows = $this->fetchHorariosGrupoTodos();
        if (empty($rows)) {
            return back()->with('error', 'No se encontraron horarios.');
        }

        // Agrupar por nombre de grupo (tal cual tu script)
        $porGrupo = [];
        foreach ($rows as $r) {
            $gname = $r['group_name'] ?? 'SIN_NOMBRE';
            $porGrupo[$gname][] = $r;
        }

        $zipName = 'Horarios_Por_Grupo_'.now()->format('Ymd_His').'.zip';
        $zipPath = storage_path('app/tmp/'.$zipName);
        $this->ensureDir(dirname($zipPath));

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE);

        foreach ($porGrupo as $groupName => $items) {
            if (empty($items)) continue;

            $xlsxPath = $this->buildGrupoXlsx(
                groupName: $groupName,
                horarios: $items,
                incompletos: false
            );

            $zip->addFile($xlsxPath, basename($xlsxPath));
        }

        $zip->close();
        $this->cleanupDir(storage_path('app/tmp/xlsx_grupo'));

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    /** ZIP: un Excel por GRUPO con faltantes de profesor (como tu script 3) */
    public function exportHorariosGruposSinProfesor()
    {
        // Evita timeout si hay muchos grupos
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

        $rows = $this->fetchHorariosGrupoIncompletos();
        if (empty($rows)) {
            return redirect()->route('configuracion.estadisticas.index')
                ->with('error', 'No se encontraron grupos con materias sin profesor.');
        }

        // Agrupar por nombre de grupo
        $porGrupo = [];
        foreach ($rows as $r) {
            $gname = $r['group_name'] ?? 'SIN_NOMBRE';
            $porGrupo[$gname][] = $r;
        }

        $zipName = 'Horarios_Por_Grupo_Incompletos_'.now()->format('Ymd_His').'.zip';
        $zipPath = storage_path('app/tmp/'.$zipName);
        $this->ensureDir(dirname($zipPath));

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return redirect()->route('configuracion.estadisticas.index')
                ->with('error', 'No se pudo crear el ZIP en el servidor.');
        }

        foreach ($porGrupo as $groupName => $items) {
            if (empty($items)) continue;

            $xlsxPath = $this->buildGrupoXlsx(
                groupName: $groupName,
                horarios: $items,
                incompletos: true
            );

            if (is_file($xlsxPath)) {
                $zip->addFile($xlsxPath, basename($xlsxPath));
            }
        }

        $zip->close();
        $this->cleanupDir(storage_path('app/tmp/xlsx_grupo'));

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }


    /** ===================== DATA ===================== */

    private function fetchHorariosProfesor(int $teacherId): array
    {
        $rows = DB::table('schedule_assignments as sa')
            ->join('subjects as s', 's.subject_id', '=', 'sa.subject_id')
            ->join('groups as g', 'g.group_id', '=', 'sa.group_id')
            ->join('shifts as sh', 'g.turn_id', '=', 'sh.shift_id')
            ->leftJoin('classrooms as r', 'r.classroom_id', '=', 'sa.classroom_id')
            ->leftJoin('labs as l', 'l.lab_id', '=', 'sa.lab_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'sa.teacher_id')
            ->where('t.teacher_id', $teacherId)
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->selectRaw("
                t.teacher_id,
                t.teacher_name,
                sa.schedule_day     AS day,
                sa.start_time       AS start_time,
                sa.end_time         AS end_time,
                sa.tipo_espacio     AS tipo_espacio,
                s.subject_name      AS subject_name,
                sh.shift_name       AS shift_name,
                r.classroom_name    AS room_name,
                l.lab_name          AS lab_name,
                RIGHT(r.building, 1) AS building_last_char,
                g.group_name        AS group_name
            ")
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'day'               => (string)$r->day,
                'start_time'        => (string)$r->start_time,
                'end_time'          => (string)$r->end_time,
                'subject_name'      => (string)$r->subject_name,
                'group_name'        => (string)$r->group_name,
                'room_name'         => $r->room_name,
                'lab_name'          => $r->lab_name,
                'building_last_char'=> $r->building_last_char,
            ];
        }
        return $out;
    }

    private function fetchHorariosGrupoTodos(): array
    {
        $rows = DB::table('schedule_assignments as sa')
            ->join('subjects as s', 's.subject_id', '=', 'sa.subject_id')
            ->join('groups as g', 'g.group_id', '=', 'sa.group_id')
            ->join('shifts as sh', 'g.turn_id', '=', 'sh.shift_id')
            ->leftJoin('classrooms as r', 'r.classroom_id', '=', 'sa.classroom_id')
            ->leftJoin('labs as l', 'l.lab_id', '=', 'sa.lab_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'sa.teacher_id')
            ->orderBy('g.group_name')
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->selectRaw("
                g.group_id,
                g.group_name,
                sa.schedule_day     AS day,
                sa.start_time       AS start_time,
                sa.end_time         AS end_time,
                sa.tipo_espacio     AS tipo_espacio,
                s.subject_name      AS subject_name,
                sh.shift_name       AS shift_name,
                r.classroom_name    AS room_name,
                l.lab_name          AS lab_name,
                RIGHT(r.building, 1) AS building_last_char,
                t.teacher_name      AS teacher_name
            ")
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'group_name'        => (string)$r->group_name,
                'day'               => (string)$r->day,
                'start_time'        => (string)$r->start_time,
                'end_time'          => (string)$r->end_time,
                'subject_name'      => (string)$r->subject_name,
                'teacher_name'      => $r->teacher_name,
                'room_name'         => $r->room_name,
                'lab_name'          => $r->lab_name,
                'building_last_char'=> $r->building_last_char,
            ];
        }
        return $out;
    }

    private function fetchHorariosGrupoIncompletos(): array
    {
        $rows = DB::table('schedule_assignments as sa')
            ->join('subjects as s', 's.subject_id', '=', 'sa.subject_id')
            ->join('groups as g', 'g.group_id', '=', 'sa.group_id')
            ->join('shifts as sh', 'g.turn_id', '=', 'sh.shift_id')
            ->leftJoin('classrooms as r', 'r.classroom_id', '=', 'sa.classroom_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'sa.teacher_id')
            ->whereIn('g.group_id', function($q){
                $q->select('group_id')->from('schedule_assignments')->whereNull('teacher_id');
            })
            ->orderBy('g.group_name')
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->selectRaw("
                g.group_id,
                g.group_name,
                sa.schedule_day     AS day,
                sa.start_time       AS start_time,
                sa.end_time         AS end_time,
                sa.tipo_espacio     AS tipo_espacio,
                s.subject_name      AS subject_name,
                sh.shift_name       AS shift_name,
                r.classroom_name    AS room_name,
                RIGHT(r.building, 1) AS building_last_char,
                t.teacher_name      AS teacher_name
            ")
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'group_name'        => (string)$r->group_name,
                'day'               => (string)$r->day,
                'start_time'        => (string)$r->start_time,
                'end_time'          => (string)$r->end_time,
                'subject_name'      => (string)$r->subject_name,
                'teacher_name'      => $r->teacher_name, // puede ser null
                'room_name'         => $r->room_name,
                'building_last_char'=> $r->building_last_char,
            ];
        }
        return $out;
    }

    /** ===================== BUILDERS ===================== */

    /** Crea XLSX para PROFESOR (coloca nombre, clasificación, total horas) */
    private function buildProfesorXlsx(string $teacherName, string $clasificacion, float $totalHoras, array $horarios): string
    {
        $spreadsheet = IOFactory::load($this->templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados como en tu script
        $sheet->setCellValue('C3', $teacherName);
        $sheet->setCellValue('F25', $clasificacion);
        $sheet->setCellValue('A3', 'Nombre del ' . $clasificacion . ':');

        $sheet->getStyle('F25')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue('G4', $totalHoras);
        $sheet->getStyle('G4')->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('G4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        // Pinta celdas según mapas
        foreach ($horarios as $h) {
            $dia  = $this->normDia($h['day']);
            $hora = $this->hhmm($h['start_time']);

            if (!isset($this->mapDias[$dia])) continue;
            if (!isset($this->mapHoras[$hora])) continue;

            $col = $this->mapDias[$dia];
            $row = $this->mapHoras[$hora];

            $contenido = $h['subject_name'] . "\n" . $h['group_name'] . "\n";
            if (!empty($h['room_name'])) {
                $contenido .= 'Aula: ' . $h['room_name'];
                if (!empty($h['building_last_char'])) $contenido .= ' (' . $h['building_last_char'] . ')';
            } elseif (!empty($h['lab_name'])) {
                $contenido .= 'Lab: ' . $h['lab_name'];
            }

            $cell = $col . $row;
            $sheet->setCellValue($cell, $contenido);
            $sheet->getStyle($cell)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }

        $dir = storage_path('app/tmp/xlsx_prof');
        $this->ensureDir($dir);
        $filename = $dir . '/Horario_' . $this->safeName($teacherName) . '.xlsx';

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($filename);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $filename;
    }

    /** Crea XLSX para GRUPO (completo o “incompleto” con mapa de filas especial) */
    private function buildGrupoXlsx(string $groupName, array $horarios, bool $incompletos): string
    {
        $spreadsheet = IOFactory::load($this->templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $mapHoras = $incompletos ? $this->mapHorasIncompletos : $this->mapHoras;

        foreach ($horarios as $h) {
            $dia  = $this->normDia($h['day']);
            $hora = $this->hhmm($h['start_time']);

            if (!isset($this->mapDias[$dia])) continue;
            if (!isset($mapHoras[$hora])) continue;

            $col = $this->mapDias[$dia];
            $row = $mapHoras[$hora];

            $prof  = $h['teacher_name'] ?: ($incompletos ? 'SIN PROFESOR' : '');
            $contenido = $h['subject_name'] . "\n" . $prof . "\n";

            if (!empty($h['room_name'])) {
                $contenido .= 'Aula: ' . $h['room_name'];
                if (!empty($h['building_last_char'])) $contenido .= ' (' . $h['building_last_char'] . ')';
            } elseif (!empty($h['lab_name'] ?? null)) {
                $contenido .= 'Lab: ' . ($h['lab_name'] ?? '');
            }

            $cell = $col . $row;
            $sheet->setCellValue($cell, $contenido);
            $sheet->getStyle($cell)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }

        $dir = storage_path('app/tmp/xlsx_grupo');
        $this->ensureDir($dir);
        $filename = $dir . '/Horario_' . ($incompletos ? 'Incompleto_' : '') . $this->safeName($groupName) . '.xlsx';

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($filename);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $filename;
    }

    /** ===================== UTILS ===================== */

    private function hhmm(string $time): string
    {
        // Asegura formato HH:MM
        return date('H:i', strtotime($time));
    }

    private function normDia(string $dia): string
    {
        $d = trim($dia);
        // Normaliza capitalización y tildes comunes
        $d = str_replace(['miercoles','sabado'], ['Miércoles','Sábado'], ucfirst(mb_strtolower($d, 'UTF-8')));
        if (!isset($this->mapDias[$d]) && isset($this->mapDias[ucfirst($d)])) {
            $d = ucfirst($d);
        }
        return $d;
    }

    private function diffHoras(string $inicio, string $fin): float
    {
        $s = strtotime($inicio);
        $e = strtotime($fin);
        if (!$s || !$e || $e <= $s) return 0.0;
        return ($e - $s) / 3600;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob(rtrim($dir, '/'). '/*') as $f) { @unlink($f); }
        @rmdir($dir);
    }

    private function safeName(string $s): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $s);
    }
}
