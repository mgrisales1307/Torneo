<?php

namespace App\Http\Requests\Autenticacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistroRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // El apodo es público y forma parte de la dirección del perfil, así
            // que solo admite letras, números, guion y guion bajo.
            'apodo' => [
                'required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('usuarios', 'apodo'),
            ],
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'correo' => [
                'required', 'string', 'email:filter', 'max:255',
                // Se compara en minúsculas porque el correo se normaliza antes
                // de guardarlo: así «Ana@x.com» no crea una segunda cuenta.
                Rule::unique('usuarios', 'correo'),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
            'clave' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('correo')) {
            $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
        }

        if ($this->has('apodo')) {
            $this->merge(['apodo' => trim((string) $this->input('apodo'))]);
        }

        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'apodo.regex' => 'El apodo solo admite letras, números, guion y guion bajo.',
            'apodo.unique' => 'Ese apodo ya está en uso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'apodo' => 'apodo',
            'nombre' => 'nombre',
            'correo' => 'correo electrónico',
            'clave' => 'contraseña',
            'telefono' => 'teléfono',
        ];
    }
}
