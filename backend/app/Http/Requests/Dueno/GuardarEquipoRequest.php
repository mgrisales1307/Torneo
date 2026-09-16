<?php

namespace App\Http\Requests\Dueno;

use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del equipo, tanto al crearlo como al editarlo.
 */
class GuardarEquipoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $equipo = $this->route('equipo');
        $esCreacion = ! $equipo instanceof Equipo;
        $obligatorio = $esCreacion ? 'required' : 'sometimes';

        return [
            'nombre' => [$obligatorio, 'string', 'min:3', 'max:80'],
            'abreviatura' => [$obligatorio, 'string', 'min:2', 'max:4', 'alpha_num:ascii'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'estadio' => ['nullable', 'string', 'max:160'],
            'fundado_en' => ['nullable', 'date', 'before_or_equal:today', 'after:1850-01-01'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'color_primario' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secundario' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre', 'abreviatura', 'ciudad', 'estadio'] as $campo) {
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
            'color_primario.regex' => 'El color primario debe venir en formato hexadecimal, por ejemplo #0B1D17.',
            'color_secundario.regex' => 'El color secundario debe venir en formato hexadecimal, por ejemplo #EAB857.',
            'abreviatura.alpha_num' => 'La abreviatura solo admite letras y números, por ejemplo JZF.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del equipo',
            'abreviatura' => 'abreviatura',
            'fundado_en' => 'fecha de fundación',
        ];
    }

    /**
     * Valores ya validados, listos para asignar al modelo.
     *
     * @return array<string, mixed>
     */
    public function datosDelEquipo(): array
    {
        return $this->safe()->only([
            'nombre',
            'abreviatura',
            'ciudad',
            'estadio',
            'fundado_en',
            'descripcion',
            'color_primario',
            'color_secundario',
            'activo',
        ]);
    }
}
