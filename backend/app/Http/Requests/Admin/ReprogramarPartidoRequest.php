<?php

namespace App\Http\Requests\Admin;

use App\Enumeraciones\EstadoPartido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReprogramarPartidoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'programado_para' => ['sometimes', 'nullable', 'date'],
            'sede' => ['sometimes', 'nullable', 'string', 'max:160'],
            'arbitro' => ['sometimes', 'nullable', 'string', 'max:160'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'estado' => [
                'sometimes',
                Rule::enum(EstadoPartido::class)->except([EstadoPartido::Finalizado]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'programado_para' => 'fecha y hora',
            'arbitro' => 'árbitro',
        ];
    }
}
