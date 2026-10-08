<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inscribe uno de mis equipos en un torneo.
 *
 * Que el equipo sea realmente mío no se comprueba aquí sino con la política
 * del equipo, en el controlador: la validación dice que el dato existe, la
 * autorización dice que puedo usarlo.
 */
class CrearInscripcionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'equipo_id' => [
                $this->route('torneo')?->modalidad === 'individual' ? 'nullable' : 'required', 'integer',
                Rule::exists('equipos', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'equipo_id' => 'equipo',
        ];
    }
}
