<?php

namespace App\Http\Requests\Admin;

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Models\Torneo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarTorneoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esCreacion = ! $this->route('torneo') instanceof Torneo;
        $obligatorio = $esCreacion ? 'required' : 'sometimes';

        return [
            'nombre' => [$obligatorio, 'string', 'min:4', 'max:120'],
            'temporada' => ['nullable', 'string', 'max:60'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'formato' => [$obligatorio, Rule::in(FormatoTorneo::valoresImplementados())],
            'estado' => ['sometimes', Rule::enum(EstadoTorneo::class)],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'sede' => ['nullable', 'string', 'max:160'],

            'cupo_maximo_equipos' => [$obligatorio, 'integer', 'min:2', 'max:64'],
            'minimo_jugadores_por_equipo' => [$obligatorio, 'integer', 'min:5', 'max:30'],
            'maximo_jugadores_por_equipo' => [
                $obligatorio, 'integer', 'min:5', 'max:40',
                'gte:minimo_jugadores_por_equipo',
            ],

            'puntos_por_victoria' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'puntos_por_empate' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'puntos_por_derrota' => ['sometimes', 'integer', 'min:0', 'max:10'],

            // El costo viaja en centavos, como entero: así no hay decimales que
            // redondear ni flotantes que pierdan un peso por el camino.
            'costo_inscripcion_centavos' => ['sometimes', 'integer', 'min:0', 'max:100000000000'],
            'moneda' => ['sometimes', 'string', 'size:3', 'alpha:ascii'],

            'inscripciones_abren_en' => ['nullable', 'date'],
            'inscripciones_cierran_en' => ['nullable', 'date', 'after_or_equal:inscripciones_abren_en'],
            'inicia_en' => ['nullable', 'date'],
            'finaliza_en' => ['nullable', 'date', 'after_or_equal:inicia_en'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('moneda')) {
            $this->merge(['moneda' => mb_strtoupper((string) $this->input('moneda'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'formato.in' => 'Por ahora el torneo puede ser de liga a una vuelta o de ida y vuelta.',
            'maximo_jugadores_por_equipo.gte' => 'El máximo de jugadores no puede ser menor que el mínimo.',
            'inscripciones_cierran_en.after_or_equal' => 'Las inscripciones no pueden cerrar antes de abrirse.',
            'finaliza_en.after_or_equal' => 'El torneo no puede terminar antes de empezar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cupo_maximo_equipos' => 'cupo máximo de equipos',
            'minimo_jugadores_por_equipo' => 'mínimo de jugadores por equipo',
            'maximo_jugadores_por_equipo' => 'máximo de jugadores por equipo',
            'costo_inscripcion_centavos' => 'costo de inscripción',
            'inscripciones_abren_en' => 'apertura de inscripciones',
            'inscripciones_cierran_en' => 'cierre de inscripciones',
            'inicia_en' => 'fecha de inicio',
            'finaliza_en' => 'fecha de cierre',
        ];
    }
}
