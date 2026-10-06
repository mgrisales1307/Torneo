<?php

namespace App\Servicios\Pagos;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

/**
 * Lo que hay que entregarle al navegador para que el capitán del equipo pueda
 * pagar: a dónde ir y hasta cuándo sirve.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SesionDeCobro implements Arrayable
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        public string $pasarela,
        public string $referencia,
        public string $url,
        public ?Carbon $expiraEn = null,
        public array $datos = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pasarela' => $this->pasarela,
            'referencia' => $this->referencia,
            'url' => $this->url,
            'expira_en' => $this->expiraEn?->toIso8601String(),
            'datos' => $this->datos,
        ];
    }
}
