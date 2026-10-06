<?php

namespace App\Http\Requests\Panel;

use App\Enumeraciones\Juego;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincula la cuenta de un juego: el formato depende del juego de la ruta.
 */
class GuardarCuentaDeJuegoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $juego = $this->juego();

        return [
            'identificador' => [
                'required', 'string', 'max:60',
                'regex:'.$juego->formatoDeCuenta(),
                Rule::unique('cuentas_de_juego', 'identificador')
                    ->where('juego', $juego->value)
                    ->whereNot('usuario_id', $this->user()->getKey()),
            ],
        ];
    }

    public function juego(): Juego
    {
        $juego = $this->route('juego');

        return $juego instanceof Juego ? $juego : Juego::from((string) $juego);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('identificador')) {
            $this->merge(['identificador' => trim((string) $this->input('identificador'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $juego = $this->juego();

        return [
            'identificador.regex' => sprintf(
                'Ese no parece un %s válido. Ejemplo: %s.',
                $juego->nombreDeCuenta(),
                $juego->ejemploDeCuenta(),
            ),
            'identificador.unique' => sprintf('Ese %s ya está vinculado a otra cuenta.', $juego->nombreDeCuenta()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['identificador' => $this->juego()->nombreDeCuenta()];
    }
}
