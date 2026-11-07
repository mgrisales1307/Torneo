<?php

namespace App\Http\Requests\Panel;

use App\Enumeraciones\Juego;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincula la cuenta de un juego.
 *
 * La vinculación es SIMULADA: no se verifica contra Riot, Steam ni ningún
 * otro servicio, así que no se exige un formato concreto. Basta con un texto
 * razonable, y que no lo use ya otra persona en ese juego.
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
                'required', 'string', 'min:3', 'max:60',
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
            'identificador.min' => sprintf('Escribe al menos 3 caracteres para tu %s.', $juego->nombreDeCuenta()),
            'identificador.max' => sprintf('Tu %s no puede pasar de 60 caracteres.', $juego->nombreDeCuenta()),
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
