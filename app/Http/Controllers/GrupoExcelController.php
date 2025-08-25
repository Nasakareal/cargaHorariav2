<?php

namespace App\Http\Controllers;

use App\Http\Requests\GrupoExcelImportRequest;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GrupoExcelController extends Controller
{
    public function template(): StreamedResponse
    {
        $headers = ['No.','Área','Abreviatura','Programa','Nivel educativo','Cuatrimestre','Sufijo','Turno','Volumen'];
        $rows = [
            ['(fila decorativa 1)','','','','','','','',''],
            ['(fila decorativa 2)','','','','','','','',''],
            ['1','MTI','TI','INGENIERÍA EN TECNOLOGÍAS DE LA INFORMACIÓN','TSU/ING','3','A','MATUTINO','30'],
            ['2','GST','GST','GASTRONOMIA','LIC','1','B','VESPERTINO','28'],
        ];
        $callback = function () use ($headers, $rows) {
            $FH = fopen('php://output', 'w');
            fprintf($FH, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($FH, $headers);
            foreach ($rows as $r) { fputcsv($FH, $r); }
            fclose($FH);
        };
        return response()->streamDownload($callback, 'plantilla_grupos.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(GrupoExcelImportRequest $request)
    {
        $file = $request->file('file')->getRealPath();
        $reader = IOFactory::createReaderForFile($file);
        $sheet = $reader->load($file)->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        if ($highestCol !== 9) {
            return back()->with('error', 'El archivo no tiene el formato adecuado: deben ser exactamente 9 columnas.');
        }

        $errores = [];
        $creados = 0;

        for ($row = 4; $row <= $highestRow; $row++) {
            $data = [];
            for ($c = 1; $c <= 9; $c++) {
                $data[$c-1] = trim((string)$sheet->getCellByColumnAndRow($c, $row)->getCalculatedValue());
            }
            if (implode('', $data) === '') { continue; }

            $area            = $data[1] ?? null;
            $abreviatura     = $data[2] ?? null;
            $program_name    = isset($data[3]) ? mb_strtoupper($data[3], 'UTF-8') : null;
            $nivel_educativo = $data[4] ?? null;
            $term_number     = isset($data[5]) ? (int) $data[5] : null;
            $group_suffix    = $data[6] ?? null;
            $turn_name       = $data[7] ?? null;
            $volume          = $data[8] ?? null;

            $group_name = mb_strtoupper("{$abreviatura}-{$term_number}{$group_suffix}", 'UTF-8');

            // -------- PREVALIDACIONES EN ESPAÑOL (no insertamos si fallan) --------
            $fallas = [];

            if (!$abreviatura || $abreviatura === '-') {
                $fallas[] = "Falta abreviatura del programa.";
            }
            if (!$group_suffix) {
                $fallas[] = "Falta sufijo del grupo (ej. A, B, C).";
            }
            if (!$term_number || $term_number <= 0) {
                $fallas[] = "El cuatrimestre debe ser un número válido mayor que 0.";
            } else {
                $termExiste = DB::table('terms')->where('term_id', $term_number)->exists();
                if (!$termExiste) {
                    $fallas[] = "El cuatrimestre {$term_number} no existe en la tabla de cuatrimestres (terms).";
                }
            }
            if ($volume !== null && $volume !== '') {
                if (!is_numeric($volume) || (int)$volume < 0) {
                    $fallas[] = "El volumen debe ser un número entero mayor o igual a 0.";
                } else {
                    $volume = (int)$volume;
                }
            } else {
                $volume = 0;
            }
            if (!$program_name) {
                $fallas[] = "Falta el nombre del programa educativo.";
            }

            $program = $program_name
                ? DB::table('programs')->select('program_id','area')->where('program_name', $program_name)->first()
                : null;

            if (!$program) {
                $fallas[] = "El programa '{$program_name}' no existe en la tabla programs.";
            }

            $turn = null;
            $turn_id = null;
            if ($turn_name) {
                $turn = DB::table('shifts')->select('shift_id')->where('shift_name', $turn_name)->first();
                if (!$turn) {
                    $fallas[] = "El turno '{$turn_name}' no existe en la tabla shifts.";
                } else {
                    $turn_id = $turn->shift_id;
                }
            }

            $program_id = $program->program_id ?? null;
            $area_value = ($program && $area === ($program->area ?? null)) ? $area : null;

            if ($group_name && $program_id) {
                $dup = DB::table('groups')->where('group_name', $group_name)->exists();
                if ($dup) {
                    $fallas[] = "El grupo '{$group_name}' ya existe.";
                }
            }

            if (!empty($fallas)) {
                $errores[] = $this->msgFila($row, implode(' ', $fallas));
                continue;
            }

            // -------- INSERCIÓN CON MANEJO DE ERRORES EN ESPAÑOL --------
            try {
                DB::transaction(function () use (
                    $area_value, $nivel_educativo, $term_number, $volume, $turn_id,
                    $program_id, $group_name, &$creados
                ) {
                    $group_id = DB::table('groups')->insertGetId([
                        'group_name'   => $group_name,
                        'program_id'   => $program_id,
                        'area'         => $area_value,
                        'term_id'      => $term_number,
                        'volume'       => $volume,
                        'turn_id'      => $turn_id,
                        'fyh_creacion' => now(),
                        'estado'       => "1",
                    ]);

                    DB::table('educational_levels')->insert([
                        'level_name' => $nivel_educativo,
                        'group_id'   => $group_id,
                    ]);

                    $subjects = DB::table('subjects')->select('subject_id')
                        ->where('program_id', $program_id)
                        ->where('term_id', $term_number)
                        ->get();

                    foreach ($subjects as $s) {
                        DB::table('group_subjects')->insert([
                            'group_id'     => $group_id,
                            'subject_id'   => $s->subject_id,
                            'fyh_creacion' => now(),
                            'estado'       => "1",
                        ]);
                    }

                    $creados++;
                });
            } catch (\Throwable $e) {
                $errores[] = $this->msgFila($row, $this->traducirExcepcion($e, [
                    'group_name' => $group_name,
                    'term_id'    => $term_number,
                    'program'    => $program_name,
                    'turn'       => $turn_name,
                ]));
            }
        }

        if (!empty($errores)) {
            return back()->with('error', "Se importaron {$creados} grupo(s), pero hubo errores:\n- " . implode("\n- ", $errores));
        }
        return back()->with('success', "Grupos registrados con éxito. Total creados: {$creados}");
    }

    // ----------------- Helpers privados -----------------

    private function msgFila(int $row, string $texto): string
    {
        return "Fila {$row}: {$texto}";
    }

    private function traducirExcepcion(\Throwable $e, array $ctx = []): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'SQLSTATE[23000]')) {
            if (str_contains($msg, 'groups_ibfk_3') || str_contains($msg, '`term_id`') && str_contains($msg, '`terms`')) {
                return "El cuatrimestre ({$ctx['term_id']}) no existe o no es válido para este grupo.";
            }
            if (str_contains($msg, 'Duplicate entry')) {
                return "El grupo '{$ctx['group_name']}' ya existe (entrada duplicada).";
            }
            if (str_contains($msg, '`program_id`') && str_contains($msg, '`programs`')) {
                return "El programa '{$ctx['program']}' no existe (clave foránea inválida).";
            }
            if (str_contains($msg, '`turn_id`') && str_contains($msg, '`shifts`')) {
                return "El turno '{$ctx['turn']}' no existe (clave foránea inválida).";
            }
            return "Restricción de integridad fallida (verifica programa, turno y cuatrimestre).";
        }

        if (str_contains($msg, 'Data too long')) {
            return "Alguno de los campos excede la longitud permitida por la base de datos.";
        }
        if (str_contains($msg, 'Incorrect integer value')) {
            return "Alguno de los campos numéricos tiene un valor no válido.";
        }

        return "Error al guardar: " . $msg;
    }
}
