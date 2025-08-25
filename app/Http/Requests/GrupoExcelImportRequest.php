<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GrupoExcelImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crear grupos') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required','file','max:8192','mimes:xlsx,csv,txt'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecciona un archivo.',
            'file.mimes'    => 'Formato no válido. Usa .xlsx o .csv.',
            'file.max'      => 'El archivo es demasiado grande (máx. 8MB).',
        ];
    }
}
