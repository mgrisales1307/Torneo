<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\LlaveDelCuadro;
use App\Enumeraciones\TipoDeFase;
use App\Models\Torneo;

class ClasificacionFinal
{
    public function __construct(private readonly TablasDeClasificacion $tablas) {}

    /** @return list<int> */
    public function equipos(Torneo $torneo): array
    {
        $fase = $torneo->fases()->reorder()->orderByDesc('orden')->first();
        if ($fase === null) {
            return [];
        }
        if ($fase->tipo->tieneTabla()) {
            return array_column(array_column($this->tablas->calcular($fase)['general'] ?? [], 'equipo'), 'id');
        }
        $partidos = $fase->partidos()->finalizados()->orderByDesc('ronda')->orderBy('id')->get();
        $final = $partidos->first(fn ($partido): bool => $partido->siguiente_ganador_id === null && $partido->llave !== LlaveDelCuadro::TercerPuesto);
        if ($final === null) {
            return [];
        }
        $orden = [$final->ganador_id, $final->perdedorId()];
        $tercero = $partidos->firstWhere('llave', LlaveDelCuadro::TercerPuesto);
        if ($tercero !== null) {
            $orden[] = $tercero->ganador_id;
            $orden[] = $tercero->perdedorId();
        } elseif ($fase->tipo === TipoDeFase::EliminacionDoble) {
            foreach ($partidos->where('llave', LlaveDelCuadro::Inferior) as $partido) {
                $orden[] = $partido->perdedorId();
            }
        }
        foreach ($partidos as $partido) {
            $orden[] = $partido->perdedorId();
        }
        $orden = [...$orden, ...$torneo->participantes()->pluck('equipo_id')->all()];

        return array_values(array_unique(array_filter($orden)));
    }
}
