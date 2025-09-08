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
    /** Plantillas */
    private string $tplProfesor;   // plantilla.xlsx
    private string $tplGrupo;      // plantillagrupo.xlsx

    /** Columnas por día (coincide con tus scripts) */
    private array $mapDias = [
        'Lunes'      => 'B',
        'Martes'     => 'C',
        'Miércoles'  => 'D',
        'Miercoles'  => 'D',
        'Jueves'     => 'E',
        'Viernes'    => 'F',
        'Sábado'     => 'G',
        'Sabado'     => 'G',
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
        // Rutas de plantillas (primer intento en resources/, fallback a public/)
        $p1 = resource_path('plantillas/plantilla.xlsx');
        $p2 = public_path('plantilla.xlsx');
        $g1 = resource_path('plantillas/plantillagrupo.xlsx');
        $g2 = public_path('plantillagrupo.xlsx');

        $this->tplProfesor = is_file($p1) ? $p1 : $p2;
        $this->tplGrupo    = is_file($g1) ? $g1 : $g2;
    }

    public function index()
    {
        return view('configuracion.estadisticas.index');
    }

    /** ===================== EXPORTS ===================== */

    /** ZIP: un Excel por PROFESOR (como tu script 1) */
    public function exportHorariosProfesores(): BinaryFileResponse
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

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
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

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    /**
     * ZIP: un Excel por GRUPO (como tu script individual) — STREAMING para evitar timeouts
     */
    public function exportHorariosGrupos(): BinaryFileResponse
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

        // Cursor en vez de get(): procesa grupo por grupo sin cargar todo a memoria
        $cursor = DB::table('schedule_assignments as sa')
            ->join('subjects as s', 's.subject_id', '=', 'sa.subject_id')
            ->join('groups as g', 'g.group_id', '=', 'sa.group_id')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'sa.teacher_id')
            ->leftJoin('classrooms as r', 'r.classroom_id', '=', 'sa.classroom_id')
            ->leftJoin('labs as l', 'l.lab_id', '=', 'sa.lab_id')
            ->orderBy('g.group_name')
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->selectRaw("
                g.group_name,
                COALESCE(p.program_name,'')      AS program_name,
                sa.schedule_day                  AS day,
                sa.start_time                    AS start_time,
                sa.end_time                      AS end_time,
                s.subject_name                   AS subject_name,
                t.teacher_name                   AS teacher_name,
                r.classroom_name                 AS room_name,
                r.building                       AS building,
                l.lab_name                       AS lab_name
            ")
            ->cursor(); // streaming

        $zipName = 'Horarios_Por_Grupo_'.now()->format('Ymd_His').'.zip';
        $zipPath = storage_path('app/tmp/'.$zipName);
        $this->ensureDir(dirname($zipPath));

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'No se pudo crear el ZIP en el servidor.');
        }

        $currentGroup   = null;
        $currentProgram = '';
        $buffer         = [];

        foreach ($cursor as $r) {
            $gname = (string)$r->group_name;

            // Cuando cambia de grupo, volcar archivo del grupo anterior
            if ($currentGroup !== null && $gname !== $currentGroup) {
                $xlsxPath = $this->buildGrupoXlsx(
                    groupName: $currentGroup,
                    programName: $currentProgram,
                    horarios: $buffer,
                    incompletos: false
                );
                if (is_file($xlsxPath)) {
                    $zip->addFile($xlsxPath, basename($xlsxPath));
                }
                // reset
                $buffer = [];
            }

            $currentGroup   = $gname;
            $currentProgram = $currentProgram ?: (string)$r->program_name;

            $buffer[] = [
                'day'         => (string)$r->day,
                'start_time'  => (string)$r->start_time,
                'end_time'    => (string)$r->end_time,
                'subject_name'=> (string)$r->subject_name,
                'teacher_name'=> $r->teacher_name,
                'room_name'   => $r->room_name,
                'building'    => $r->building,
                'lab_name'    => $r->lab_name,
            ];
        }

        // Último grupo en buffer
        if ($currentGroup !== null && !empty($buffer)) {
            $xlsxPath = $this->buildGrupoXlsx(
                groupName: $currentGroup,
                programName: $currentProgram,
                horarios: $buffer,
                incompletos: false
            );
            if (is_file($xlsxPath)) {
                $zip->addFile($xlsxPath, basename($xlsxPath));
            }
        }

        $zip->close();
        $this->cleanupDir(storage_path('app/tmp/xlsx_grupo'));

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    /**
     * ZIP: un Excel por GRUPO con faltantes de profesor — STREAMING y mismo formato
     */
    public function exportHorariosGruposSinProfesor(): BinaryFileResponse
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

        $cursor = DB::table('schedule_assignments as sa')
            ->join('groups as g', 'g.group_id', '=', 'sa.group_id')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->join('subjects as s', 's.subject_id', '=', 'sa.subject_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'sa.teacher_id')
            ->leftJoin('classrooms as r', 'r.classroom_id', '=', 'sa.classroom_id')
            ->leftJoin('labs as l', 'l.lab_id', '=', 'sa.lab_id')
            ->whereNull('sa.teacher_id')
            ->orderBy('g.group_name')
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->selectRaw("
                g.group_name,
                COALESCE(p.program_name,'')      AS program_name,
                sa.schedule_day                  AS day,
                sa.start_time                    AS start_time,
                sa.end_time                      AS end_time,
                s.subject_name                   AS subject_name,
                t.teacher_name                   AS teacher_name,
                r.classroom_name                 AS room_name,
                r.building                       AS building,
                l.lab_name                       AS lab_name
            ")
            ->cursor();

        $zipName = 'Horarios_Por_Grupo_Incompletos_'.now()->format('Ymd_His').'.zip';
        $zipPath = storage_path('app/tmp/'.$zipName);
        $this->ensureDir(dirname($zipPath));

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'No se pudo crear el ZIP en el servidor.');
        }

        $currentGroup   = null;
        $currentProgram = '';
        $buffer         = [];

        foreach ($cursor as $r) {
            $gname = (string)$r->group_name;

            if ($currentGroup !== null && $gname !== $currentGroup) {
                $xlsxPath = $this->buildGrupoXlsx(
                    groupName: $currentGroup,
                    programName: $currentProgram,
                    horarios: $buffer,
                    incompletos: true
                );
                if (is_file($xlsxPath)) {
                    $zip->addFile($xlsxPath, basename($xlsxPath));
                }
                $buffer = [];
            }

            $currentGroup   = $gname;
            $currentProgram = $currentProgram ?: (string)$r->program_name;

            $buffer[] = [
                'day'         => (string)$r->day,
                'start_time'  => (string)$r->start_time,
                'end_time'    => (string)$r->end_time,
                'subject_name'=> (string)$r->subject_name,
                'teacher_name'=> $r->teacher_name, // null
                'room_name'   => $r->room_name,
                'building'    => $r->building,
                'lab_name'    => $r->lab_name,
            ];
        }

        if ($currentGroup !== null && !empty($buffer)) {
            $xlsxPath = $this->buildGrupoXlsx(
                groupName: $currentGroup,
                programName: $currentProgram,
                horarios: $buffer,
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
                r.building          AS building,
                l.lab_name          AS lab_name,
                g.group_name        AS group_name
            ")
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'day'          => (string)$r->day,
                'start_time'   => (string)$r->start_time,
                'end_time'     => (string)$r->end_time,
                'subject_name' => (string)$r->subject_name,
                'group_name'   => (string)$r->group_name,
                'room_name'    => $r->room_name,
                'building'     => $r->building,
                'lab_name'     => $r->lab_name,
            ];
        }
        return $out;
    }

    /** ===================== BUILDERS ===================== */

    /** Crea XLSX para PROFESOR (coloca nombre, clasificación, total horas) */
    private function buildProfesorXlsx(string $teacherName, string $clasificacion, float $totalHoras, array $horarios): string
    {
        $spreadsheet = IOFactory::load($this->tplProfesor);
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

        foreach ($horarios as $h) {
            $dia  = $this->normDia($h['day']);
            $hora = $this->hhmm($h['start_time']);

            if (!isset($this->mapDias[$dia])) continue;
            if (!isset($this->mapHoras[$hora])) continue;

            $col = $this->mapDias[$dia];
            $row = $this->mapHoras[$hora];

            // Aula o Lab con prefijo
            $espacioTxt = '';
            if (!empty($h['lab_name'])) {
                $espacioTxt = 'Lab ' . trim((string)$h['lab_name']);
            } elseif (!empty($h['room_name'])) {
                $espacioTxt = $this->formatAula($h['room_name'], $h['building']); // "Aula B10"
            }

            // Formato profesor: multilínea (materia, grupo, espacio)
            $partes = array_filter([
                $h['subject_name'] ?? '',
                $h['group_name'] ?? '',
                $espacioTxt,
            ], fn($v) => $v !== null && $v !== '');

            $cell = $col . $row;
            $sheet->setCellValue($cell, implode("\n", $partes));
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

    /**
     * Crea XLSX para GRUPO (completo o “incompleto”) usando la MISMA plantilla del individual
     * y MISMO FORMATO: "Materia — Aula B10 — PROFESOR" (o "— Sin profesor").
     */
    private function buildGrupoXlsx(string $groupName, string $programName, array $horarios, bool $incompletos): string
    {
        $spreadsheet = IOFactory::load($this->tplGrupo);
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados igual que el individual
        $sheet->setCellValue('G3', $groupName);
        $sheet->setCellValue('C3', $programName);

        // Rango de horas del individual: 07:00-20:00 por filas a partir de la 6
        $dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $horas = [];
        $t   = \Carbon\Carbon::createFromFormat('H:i', '07:00');
        $end = \Carbon\Carbon::createFromFormat('H:i', '20:00');
        while ($t < $end) {
            $nxt = $t->copy()->addHour();
            $horas[] = $t->format('H:i') . ' - ' . $nxt->format('H:i');
            $t = $nxt;
        }

        // Matriz [horaLabel][dia] => texto
        $tabla = [];
        foreach ($horas as $h) foreach ($dias as $d) { $tabla[$h][$d] = ''; }

        foreach ($horarios as $r) {
            $hLabel = $this->hhmm($r['start_time']) . ' - ' . $this->hhmm($r['end_time']);
            $dia    = $this->normDia($r['day']);
            if (!in_array($hLabel, $horas, true) || !in_array($dia, $dias, true)) continue;

            // Aula/Lab con prefijo
            $espacio = null;
            if (!empty($r['lab_name'])) {
                $espacio = 'Lab ' . trim((string)$r['lab_name']);
            } elseif (!empty($r['room_name'])) {
                $espacio = $this->formatAula($r['room_name'], $r['building']); // "Aula B10"
            }

            // Texto en una sola línea, como el individual
            $texto = $r['subject_name'];
            if (!empty($espacio)) {
                $texto .= ' — ' . $espacio;
            }
            if (!empty($r['teacher_name'])) {
                $texto .= ' — ' . $r['teacher_name'];
            } else {
                $texto .= ' — ' . ($incompletos ? 'Sin profesor' : 'Sin profesor');
            }

            $tabla[$hLabel][$dia] = $texto;
        }

        // Volcado a la plantilla (A6 = hora; B..G = días)
        $fila = 6;
        foreach ($horas as $h) {
            $sheet->setCellValue("A{$fila}", $h);
            $col = 'B';
            foreach ($dias as $d) {
                $sheet->setCellValue("{$col}{$fila}", $tabla[$h][$d]);
                $col++;
            }
            $fila++;
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

    /**
     * Construye "Aula B10" a partir del número de salón y del edificio.
     * - $roomName: "10", "B10", "61", etc.
     * - $building: "Edificio-B", "B", "A", "B-1", etc.
     */
    private function formatAula(?string $roomName, ?string $building): string
    {
        $num = trim((string)$roomName);

        $letra = '';
        $b = trim((string)($building ?? ''));
        if ($b !== '') {
            if (strpos($b, '-') !== false) {
                $letra = substr($b, strrpos($b, '-') + 1);
            } elseif (preg_match('/([A-Z])$/i', $b, $m)) {
                $letra = $m[1];
            } else {
                $letra = $b;
            }
        }
        $letra = strtoupper(trim($letra));

        // Evita duplicar letra si ya viene en el número (p.ej. "B10")
        if ($letra !== '' && preg_match('/^[A-Z]\d+/i', $num)) {
            // ya contiene letra inicial, usa tal cual
            return 'Aula ' . strtoupper($num);
        }

        return 'Aula ' . trim(($letra ? $letra : '') . $num);
    }

    /** ===================== (Opcional) EXPORTS INDIVIDUALES QUE ME PASASTE ===================== */
    /** Si quieres, puedes dejar estos tal cual y sólo adoptan el prefijo "Aula " aquí también. */

    public function exportExcel(int $grupo_id): BinaryFileResponse
    {
        $grupo = DB::table('groups as g')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->where('g.group_id', $grupo_id)
            ->select('g.group_id','g.group_name','p.program_name')
            ->first();

        abort_unless($grupo, 404, 'Grupo no encontrado');

        $rows = DB::table('schedule_assignments as sa')
            ->join('subjects as s', 's.subject_id', '=', 'sa.subject_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'sa.teacher_id')
            ->leftJoin('classrooms as r', 'r.classroom_id', '=', 'sa.classroom_id')
            ->leftJoin('labs as l', 'l.lab_id', '=', 'sa.lab_id')
            ->where('sa.group_id', $grupo_id)
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->select([
                'sa.schedule_day as dia',
                'sa.start_time',
                'sa.end_time',
                's.subject_name',
                't.teacher_name',
                'r.classroom_name',
                'r.building',
                'l.lab_name',
            ])
            ->get();

        $dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $horas = [];
        $t = \Carbon\Carbon::createFromFormat('H:i', '07:00');
        $end = \Carbon\Carbon::createFromFormat('H:i', '20:00');
        while ($t < $end) {
            $nxt = $t->copy()->addHour();
            $horas[] = $t->format('H:i') . ' - ' . $nxt->format('H:i');
            $t = $nxt;
        }

        $tabla = [];
        foreach ($horas as $h) {
            foreach ($dias as $d) {
                $tabla[$h][$d] = '';
            }
        }

        foreach ($rows as $r) {
            $hLabel = date('H:i', strtotime($r->start_time)) . ' - ' . date('H:i', strtotime($r->end_time));
            $dia = ucfirst(strtolower($r->dia));
            if (!in_array($hLabel, $horas) || !in_array($dia, $dias)) continue;

            $espacio = null;
            if (!empty($r->lab_name)) {
                $espacio = 'Lab ' . $r->lab_name;
            } elseif (!empty($r->classroom_name)) {
                $espacio = $this->formatAula($r->classroom_name, $r->building); // <-- prefijo Aula
            }

            $contenido = $r->subject_name;
            if (!empty($espacio)) {
                $contenido .= ' — ' . $espacio;
            }
            if (!empty($r->teacher_name)) {
                $contenido .= ' — ' . $r->teacher_name;
            } else {
                $contenido .= ' — Sin profesor';
            }

            $tabla[$hLabel][$dia] = $contenido;
        }

        $templatePath = $this->tplGrupo; // usa misma plantilla
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('G3', $grupo->group_name);
        $sheet->setCellValue('C3', $grupo->program_name);

        $fila = 6;
        foreach ($horas as $h) {
            $sheet->setCellValue("A{$fila}", $h);
            $col = 'B';
            foreach ($dias as $d) {
                $sheet->setCellValue("{$col}{$fila}", $tabla[$h][$d]);
                $col++;
            }
            $fila++;
        }

        $fileName = 'Horario_'.$grupo->group_name.'_'.now()->format('Ymd_His').'.xlsx';
        $filePath = storage_path("app/tmp/{$fileName}");
        @mkdir(dirname($filePath), 0775, true);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($filePath);

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportExcelProfesor(int $profesor_id): BinaryFileResponse
    {
        $profesor = DB::table('teachers')->where('teacher_id', $profesor_id)->first();
        abort_unless($profesor, 404, 'Profesor no encontrado');

        $rows = $this->fetchHorariosProfesor($profesor_id);

        // Calcular total horas
        $totalHoras = 0;
        foreach ($rows as $r) {
            $totalHoras += $this->diffHoras($r['start_time'], $r['end_time']);
        }

        // Reusar builder de profesor
        $xlsxPath = $this->buildProfesorXlsx(
            teacherName: $profesor->teacher_name,
            clasificacion: $profesor->clasificacion ?? 'Sin clasificar',
            totalHoras: $totalHoras,
            horarios: $rows
        );

        $fileName = basename($xlsxPath);
        return response()->download($xlsxPath, $fileName)->deleteFileAfterSend(true);
    }
}
