<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El capitán cambia el puesto de un miembro o le cede la capitanía.
 */
class ActualizarMiembroRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'es_suplente' => ['required_without:capitan', 'boolean'],
            'capitan' => ['sometimes', 'accepted'],
        ];
    }
}
