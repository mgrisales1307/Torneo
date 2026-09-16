<?php

namespace App\Http\Requests\Dueno;

use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sube el escudo o uno de los dos uniformes del equipo.
 *
 * Las reglas del archivo (peso, formato y dimensiones) las dicta el almacén de
 * imágenes a partir de config/medios.php, para que sean las mismas aquí y en
 * cualquier otra pantalla que suba imágenes.
 */
class SubirImagenDeEquipoRequest extends FormRequest
{
    /**
     * Columna del equipo en la que se guarda cada tipo de imagen.
     */
    private const DESTINOS = [
        'escudo' => ['columna' => 'escudo_ruta', 'limites' => 'escudo'],
        'uniforme_local' => ['columna' => 'uniforme_local_ruta', 'limites' => 'uniforme'],
        'uniforme_visitante' => ['columna' => 'uniforme_visitante_ruta', 'limites' => 'uniforme'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(self::DESTINOS))],
            'imagen' => array_merge(
                ['required'],
                app(AlmacenDeImagenes::class)->reglasDeValidacion($this->tipoDeLimites()),
            ),
        ];
    }

    public function tipo(): string
    {
        return (string) $this->validated('tipo');
    }

    public function columnaDestino(): string
    {
        return self::DESTINOS[$this->tipo()]['columna'];
    }

    public function tipoDeLimites(): string
    {
        $tipo = (string) $this->input('tipo');

        return self::DESTINOS[$tipo]['limites'] ?? 'escudo';
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'imagen.image' => 'El archivo debe ser una imagen JPG, PNG o WEBP.',
            'imagen.mimes' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'imagen.mimetypes' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'imagen.max' => 'La imagen pesa demasiado. Súbela por debajo del límite indicado.',
            'imagen.dimensions' => 'La imagen es demasiado pequeña o demasiado grande.',
        ];
    }
}
