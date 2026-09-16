<?php

namespace App\Http\Requests\Dueno;

use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Foundation\Http\FormRequest;

class SubirFotoDeJugadorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'foto' => array_merge(
                ['required'],
                app(AlmacenDeImagenes::class)->reglasDeValidacion('foto_jugador'),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.image' => 'El archivo debe ser una imagen JPG, PNG o WEBP.',
            'foto.mimes' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'foto.mimetypes' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'foto.max' => 'La foto pesa demasiado.',
            'foto.dimensions' => 'La foto es demasiado pequeña o demasiado grande.',
        ];
    }
}
