<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class UnirseAEquipoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'size:8', 'alpha_num:ascii'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.size' => 'El código de invitación tiene 8 caracteres.',
            'codigo.alpha_num' => 'El código de invitación solo lleva letras y números.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['codigo' => 'código de invitación'];
    }
}
