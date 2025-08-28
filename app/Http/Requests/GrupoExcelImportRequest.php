<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GrupoExcelImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'    => 'required_without:archivo|file|mimes:xlsx,xls,csv,txt|max:51200',
            'archivo' => 'required_without:file|file|mimes:xlsx,xls,csv,txt|max:51200',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required_without'    => 'Selecciona un archivo (campo "file" o "archivo").',
            'archivo.required_without' => 'Selecciona un archivo (campo "file" o "archivo").',
            'file.mimes'               => 'Formato no válido (usa XLSX/XLS/CSV).',
            'archivo.mimes'            => 'Formato no válido (usa XLSX/XLS/CSV).',
        ];
    }
}
