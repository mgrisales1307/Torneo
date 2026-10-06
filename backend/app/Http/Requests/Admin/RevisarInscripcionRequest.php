<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevisarInscripcionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Al rechazar, el motivo es obligatorio: el capitán del equipo tiene
            // que saber qué corregir.
            'notas' => [
                $this->routeIs('*.rechazar') ? 'required' : 'nullable',
                'string', 'min:5', 'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'notas' => 'motivo',
        ];
    }
}
