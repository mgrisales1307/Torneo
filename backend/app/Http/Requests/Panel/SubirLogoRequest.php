<?php

namespace App\Http\Requests\Panel;

use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sube el logo del equipo.
 *
 * Las reglas del archivo (peso, formato y dimensiones) las dicta el almacén de
 * imágenes a partir de config/medios.php, para que sean las mismas aquí y en
 * cualquier otra pantalla que suba imágenes.
 */
class SubirLogoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', ...app(AlmacenDeImagenes::class)->reglasDeValidacion('logo')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image' => 'El archivo debe ser una imagen JPG, PNG o WEBP.',
            'logo.mimes' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'logo.mimetypes' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'logo.max' => 'El logo pesa demasiado: máximo 2 MB.',
            'logo.dimensions' => 'El logo debe medir entre 120 y 2000 píxeles por lado.',
        ];
    }
}
