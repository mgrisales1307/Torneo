<?php

namespace App\Http\Requests\Panel;

use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El capitán reporta el marcador de la serie, con la captura de la pantalla
 * final como respaldo.
 *
 * Aquí se valida la forma. Que el marcador sea posible para la serie (2-1 en
 * un mejor de 3, no 2-2) lo comprueba RegistroDeResultados, que conoce el
 * partido.
 */
class ReportarResultadoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'victorias_a' => ['required', 'integer', 'min:0', 'max:5'],
            'victorias_b' => ['required', 'integer', 'min:0', 'max:5'],
            'detalle' => ['sometimes', 'nullable', 'array', 'max:5'],
            'detalle.*.mapa' => ['nullable', 'string', 'max:40'],
            'detalle.*.ganador' => ['required', 'in:a,b'],
            'comentario' => ['nullable', 'string', 'max:500'],
            'captura' => ['sometimes', 'nullable', ...app(AlmacenDeImagenes::class)->reglasDeValidacion('captura')],
        ];
    }

    /**
     * @return array{victorias_a: int, victorias_b: int, detalle: array<int, array<string, mixed>>|null, comentario: string|null}
     */
    public function datosDelReporte(): array
    {
        return [
            'victorias_a' => (int) $this->validated('victorias_a'),
            'victorias_b' => (int) $this->validated('victorias_b'),
            'detalle' => $this->validated('detalle'),
            'comentario' => $this->validated('comentario'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'captura.image' => 'La captura debe ser una imagen JPG, PNG o WEBP.',
            'captura.max' => 'La captura pesa demasiado: máximo 6 MB.',
            'captura.dimensions' => 'La captura es demasiado pequeña o demasiado grande.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'victorias_a' => 'marcador del primer equipo',
            'victorias_b' => 'marcador del segundo equipo',
        ];
    }
}
