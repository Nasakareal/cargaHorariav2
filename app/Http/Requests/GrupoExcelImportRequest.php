<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GrupoExcelImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('importar grupos') ?? true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:51200',sss
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecciona un archivo.',
            'file.mimes'    => 'Formato no válido (usa XLSX/XLS/CSV).',
            'file.max'      => 'El archivo es demasiado grande.',
        ];
    }
}
