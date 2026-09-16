<?php

namespace App\Http\Requests\Dueno;

use App\Enumeraciones\EstadoJugador;
use App\Enumeraciones\PosicionJugador;
use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un jugador.
 *
 * Las dos reglas que evitan una plantilla inconsistente:
 *
 * - El dorsal es único dentro del equipo. Los jugadores dados de baja no
 *   estorban, porque al darles de baja se libera su número.
 * - El documento no se puede repetir en el mismo equipo, para que nadie quede
 *   inscrito dos veces.
 */
class GuardarJugadorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $jugador = $this->route('jugador');
        $esCreacion = ! $jugador instanceof Jugador;
        $obligatorio = $esCreacion ? 'required' : 'sometimes';
        $equipoId = $this->equipo()->getKey();

        $soloDelEquipoYVigentes = fn (Builder $consulta): Builder => $consulta
            ->where('equipo_id', $equipoId)
            ->whereNull('deleted_at');

        return [
            'nombres' => [$obligatorio, 'string', 'min:2', 'max:120'],
            'apellidos' => [$obligatorio, 'string', 'min:2', 'max:120'],
            'documento_identidad' => [
                $obligatorio, 'string', 'min:5', 'max:40', 'regex:/^[A-Za-z0-9.\-]+$/',
                Rule::unique('jugadores', 'documento_identidad')
                    ->where($soloDelEquipoYVigentes)
                    ->ignore($jugador),
            ],
            'fecha_nacimiento' => [
                $obligatorio, 'date',
                'before_or_equal:'.now()->subYears(14)->toDateString(),
                'after_or_equal:'.now()->subYears(70)->toDateString(),
            ],
            'posicion' => [$obligatorio, Rule::enum(PosicionJugador::class)],
            'numero_camiseta' => [
                'nullable', 'integer', 'min:1', 'max:99',
                Rule::unique('jugadores', 'numero_camiseta')
                    ->where($soloDelEquipoYVigentes)
                    ->ignore($jugador),
            ],
            'estado' => ['sometimes', Rule::enum(EstadoJugador::class)],
            'es_capitan' => ['sometimes', 'boolean'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email:filter', 'max:255'],
        ];
    }

    /**
     * El equipo sale de la ruta al crear, y del propio jugador al editar. Nunca
     * del cuerpo de la petición: si viniera de ahí, cualquiera podría meter un
     * jugador en un equipo ajeno.
     */
    public function equipo(): Equipo
    {
        $equipo = $this->route('equipo');

        if ($equipo instanceof Equipo) {
            return $equipo;
        }

        $jugador = $this->route('jugador');

        if ($jugador instanceof Jugador) {
            return $jugador->equipo;
        }

        abort(404);
    }

    /**
     * Un equipo no puede pasarse del máximo de jugadores del torneo más
     * estricto en el que ya está inscrito y pagando.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                if ($this->route('jugador') instanceof Jugador) {
                    return;
                }

                $equipo = $this->equipo();

                $torneo = $equipo->inscripciones()
                    ->pagadas()
                    ->with('torneo')
                    ->get()
                    ->pluck('torneo')
                    ->filter()
                    ->sortBy('maximo_jugadores_por_equipo')
                    ->first();

                if ($torneo === null) {
                    return;
                }

                $activos = $equipo->jugadores()->convocables()->count();

                if ($activos >= $torneo->maximo_jugadores_por_equipo) {
                    $validador->errors()->add('nombres', sprintf(
                        'El equipo ya tiene los %d jugadores que admite el torneo «%s».',
                        $torneo->maximo_jugadores_por_equipo,
                        $torneo->nombre,
                    ));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombres', 'apellidos', 'documento_identidad'] as $campo) {
            if ($this->has($campo) && is_string($this->input($campo))) {
                $this->merge([$campo => trim((string) $this->input($campo))]);
            }
        }

        if ($this->has('correo') && filled($this->input('correo'))) {
            $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_camiseta.unique' => 'Ese dorsal ya lo tiene otro jugador del equipo.',
            'documento_identidad.unique' => 'Ya hay un jugador con ese documento en el equipo.',
            'documento_identidad.regex' => 'El documento solo admite letras, números, puntos y guiones.',
            'fecha_nacimiento.before_or_equal' => 'El jugador debe tener al menos 14 años.',
            'fecha_nacimiento.after_or_equal' => 'Revisa la fecha de nacimiento: no parece correcta.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'numero_camiseta' => 'número de camiseta',
            'documento_identidad' => 'documento de identidad',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'es_capitan' => 'capitán',
        ];
    }
}
