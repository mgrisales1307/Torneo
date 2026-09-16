<?php

namespace App\Http\Requests\Admin;

use App\Enumeraciones\TipoEventoPartido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Resultado de un partido: el marcador y, opcionalmente, el detalle de goles y
 * tarjetas.
 *
 * Aquí se valida la forma de los datos. Que el jugador sea de uno de los dos
 * equipos y que el marcador cuadre con los goles lo comprueba el servicio
 * RegistroDeResultados, que es quien conoce el partido.
 */
class GuardarResultadoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'goles_local' => ['required', 'integer', 'min:0', 'max:99'],
            'goles_visitante' => ['required', 'integer', 'min:0', 'max:99'],
            'observaciones' => ['nullable', 'string', 'max:1000'],

            'eventos' => ['sometimes', 'array', 'max:100'],
            'eventos.*.equipo_id' => ['required', 'integer', 'exists:equipos,id'],
            'eventos.*.jugador_id' => ['required', 'integer', 'exists:jugadores,id'],
            'eventos.*.tipo' => ['required', Rule::enum(TipoEventoPartido::class)],
            'eventos.*.minuto' => ['nullable', 'integer', 'min:0', 'max:130'],
            'eventos.*.detalle' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'goles_local' => 'goles del local',
            'goles_visitante' => 'goles del visitante',
            'eventos.*.equipo_id' => 'equipo del evento',
            'eventos.*.jugador_id' => 'jugador del evento',
            'eventos.*.tipo' => 'tipo de evento',
            'eventos.*.minuto' => 'minuto',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDelResultado(): array
    {
        return [
            'goles_local' => (int) $this->validated('goles_local'),
            'goles_visitante' => (int) $this->validated('goles_visitante'),
            'observaciones' => $this->validated('observaciones'),
            'eventos' => $this->validated('eventos', []),
        ];
    }
}
