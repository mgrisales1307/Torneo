<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Puesto y bajas de cada equipo en una partida de battle royale.
 */
class GuardarBattleRoyaleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resultados' => ['required', 'array', 'min:2', 'max:20'],
            'resultados.*.equipo_id' => ['required', 'integer'],
            'resultados.*.posicion' => ['required', 'integer', 'min:1', 'max:20'],
            'resultados.*.bajas' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }

    /**
     * @return list<array{equipo_id: int, posicion: int, bajas: int}>
     */
    public function resultados(): array
    {
        return array_values(array_map(fn (array $fila): array => [
            'equipo_id' => (int) $fila['equipo_id'],
            'posicion' => (int) $fila['posicion'],
            'bajas' => (int) $fila['bajas'],
        ], $this->validated('resultados')));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'resultados.*.posicion' => 'puesto',
            'resultados.*.bajas' => 'bajas',
        ];
    }
}
