<?php

namespace App\Http\Requests\Panel;

use App\Enumeraciones\Juego;
use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del equipo, tanto al crearlo como al editarlo. El juego solo se elige
 * al crearlo: un equipo de Valorant no pasa a ser de Dota.
 */
class GuardarEquipoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esCreacion = ! $this->route('equipo') instanceof Equipo;
        $obligatorio = $esCreacion ? 'required' : 'sometimes';

        return [
            'juego' => $esCreacion ? ['required', Rule::enum(Juego::class)] : ['prohibited'],
            'nombre' => [$obligatorio, 'string', 'min:3', 'max:60'],
            'abreviatura' => [$obligatorio, 'string', 'min:2', 'max:5', 'alpha_num:ascii'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre', 'abreviatura'] as $campo) {
            if ($this->has($campo) && is_string($this->input($campo))) {
                $this->merge([$campo => trim((string) $this->input($campo))]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'juego.prohibited' => 'El juego de un equipo no se cambia. Crea otro equipo para otro juego.',
            'color.regex' => 'El color va en hexadecimal, por ejemplo #FF4655.',
            'abreviatura.alpha_num' => 'La sigla solo admite letras y números, por ejemplo NZ.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del equipo',
            'abreviatura' => 'sigla',
        ];
    }
}
