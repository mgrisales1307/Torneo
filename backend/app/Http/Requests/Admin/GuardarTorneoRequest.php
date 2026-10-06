<?php

namespace App\Http\Requests\Admin;

use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Models\Torneo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Datos de un torneo. El estado no viaja aquí: se publica, se inicia y se
 * cancela con acciones propias, que comprueban que el paso sea válido.
 */
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
            'juego' => [$obligatorio, Rule::enum(Juego::class)],
            'nombre' => [$obligatorio, 'string', 'min:4', 'max:120'],
            'formato' => [$obligatorio, Rule::enum(FormatoTorneo::class)],
            'reglas' => ['nullable', 'string', 'max:5000'],

            'cupo_maximo_equipos' => [$obligatorio, 'integer', 'min:2', 'max:64'],
            'mejor_de' => ['sometimes', 'integer', 'in:1,3,5'],
            'mejor_de_final' => ['sometimes', 'integer', 'in:1,3,5'],

            'cantidad_de_grupos' => ['nullable', 'integer', 'min:2', 'max:8'],
            'clasifican_por_grupo' => ['nullable', 'integer', 'min:1', 'max:4'],
            'rondas_suizo' => ['nullable', 'integer', 'min:2', 'max:9'],
            'partidas_battle_royale' => ['nullable', 'integer', 'min:1', 'max:12'],
            'check_in_minutos' => ['sometimes', 'integer', 'min:0', 'max:240'],

            // El dinero viaja en centavos, como entero: así no hay decimales que
            // redondear ni flotantes que pierdan un peso por el camino.
            'costo_inscripcion_centavos' => ['sometimes', 'integer', 'min:0', 'max:100000000000'],
            'moneda' => ['sometimes', 'string', 'size:3', 'alpha:ascii'],
            'premios' => ['nullable', 'array', 'max:3'],
            'premios.*' => ['integer', 'min:0', 'max:100000000000'],

            'inscripciones_abren_en' => ['nullable', 'date'],
            'inscripciones_cierran_en' => ['nullable', 'date', 'after_or_equal:inscripciones_abren_en'],
            'inicia_en' => ['nullable', 'date'],
        ];
    }

    /**
     * Reglas que cruzan campos: el formato tiene que ser del tipo del juego, y
     * cada formato trae sus ajustes obligatorios.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validador): void {
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            /** @var Torneo|null $torneo */
            $torneo = $this->route('torneo') instanceof Torneo ? $this->route('torneo') : null;

            $juego = Juego::tryFrom((string) $this->input('juego', $torneo?->juego->value));
            $formato = FormatoTorneo::tryFrom((string) $this->input('formato', $torneo?->formato->value));

            if ($juego === null || $formato === null) {
                return;
            }

            if ($formato->esBattleRoyale() !== $juego->esBattleRoyale()) {
                $validador->errors()->add('formato', $juego->esBattleRoyale()
                    ? "{$juego->nombre()} se juega en battle royale por puntos."
                    : "{$juego->nombre()} se juega por duelos: elige un formato de cuadro.");

                return;
            }

            $cupo = (int) $this->input('cupo_maximo_equipos', $torneo?->cupo_maximo_equipos);

            if ($formato->esBattleRoyale() && $cupo > $juego->equiposPorPartida()) {
                $validador->errors()->add('cupo_maximo_equipos', sprintf(
                    'En una sala de %s caben como máximo %d equipos.',
                    $juego->nombre(),
                    $juego->equiposPorPartida(),
                ));
            }

            if ($formato === FormatoTorneo::GruposYEliminacion) {
                foreach (['cantidad_de_grupos' => 'cantidad de grupos', 'clasifican_por_grupo' => 'clasificados por grupo'] as $campo => $nombre) {
                    if (blank($this->input($campo, $torneo?->{$campo}))) {
                        $validador->errors()->add($campo, "Indica la $nombre.");
                    }
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('moneda')) {
            $this->merge(['moneda' => mb_strtoupper((string) $this->input('moneda'))]);
        }

        if ($this->has('premios') && is_array($this->input('premios'))) {
            // Los puestos sin premio no se guardan: [500000, 0, 0] queda [500000].
            $premios = array_map('intval', $this->input('premios'));

            while ($premios !== [] && end($premios) === 0) {
                array_pop($premios);
            }

            $this->merge(['premios' => $premios === [] ? null : $premios]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mejor_de.in' => 'Las series son al mejor de 1, 3 o 5.',
            'mejor_de_final.in' => 'La final es al mejor de 1, 3 o 5.',
            'inscripciones_cierran_en.after_or_equal' => 'Las inscripciones no pueden cerrar antes de abrirse.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cupo_maximo_equipos' => 'cupo de equipos',
            'mejor_de' => 'series',
            'mejor_de_final' => 'final',
            'cantidad_de_grupos' => 'cantidad de grupos',
            'clasifican_por_grupo' => 'clasificados por grupo',
            'rondas_suizo' => 'rondas',
            'partidas_battle_royale' => 'partidas',
            'check_in_minutos' => 'check-in',
            'costo_inscripcion_centavos' => 'costo de inscripción',
            'inscripciones_abren_en' => 'apertura de inscripciones',
            'inscripciones_cierran_en' => 'cierre de inscripciones',
            'inicia_en' => 'fecha de inicio',
        ];
    }
}
