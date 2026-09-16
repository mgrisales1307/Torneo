<?php

namespace App\Http\Requests\Autenticacion;

use Illuminate\Foundation\Http\FormRequest;

class IngresoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'correo' => ['required', 'string', 'email:filter', 'max:255'],
            'clave' => ['required', 'string', 'max:255'],
            'recordarme' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('correo')) {
            $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'correo' => 'correo electrónico',
            'clave' => 'contraseña',
        ];
    }
}
