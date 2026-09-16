<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GenerarCalendarioRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Reemplazar el calendario borra los partidos anteriores, así que
            // hay que pedirlo explícitamente.
            'reiniciar' => ['sometimes', 'boolean'],
        ];
    }
}
