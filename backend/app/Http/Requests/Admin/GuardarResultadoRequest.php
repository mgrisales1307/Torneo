<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La organización fija el marcador de una serie: resuelve una disputa o carga
 * un partido que nadie reportó.
 */
class GuardarResultadoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'victorias_a' => ['required', 'integer', 'min:0', 'max:5'],
            'victorias_b' => ['required', 'integer', 'min:0', 'max:5'],
            'detalle' => ['sometimes', 'nullable', 'array', 'max:5'],
            'detalle.*.mapa' => ['nullable', 'string', 'max:40'],
            'detalle.*.ganador' => ['required', 'in:a,b'],
        ];
    }

    /**
     * @return array{victorias_a: int, victorias_b: int, detalle: array<int, array<string, mixed>>|null}
     */
    public function datosDelResultado(): array
    {
        return [
            'victorias_a' => (int) $this->validated('victorias_a'),
            'victorias_b' => (int) $this->validated('victorias_b'),
            'detalle' => $this->validated('detalle'),
        ];
    }
}
