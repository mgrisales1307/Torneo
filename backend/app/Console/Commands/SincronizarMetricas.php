<?php

namespace App\Console\Commands;

use App\Servicios\Metricas\AgregadorDeMetricas;
use Illuminate\Console\Command;

class SincronizarMetricas extends Command
{
    protected $signature = 'metricas:sincronizar';

    protected $description = 'Actualiza los agregados de Metabase y purga eventos de más de 90 días';

    public function handle(AgregadorDeMetricas $agregador): int
    {
        if (! config('metricas.habilitadas')) {
            $this->error('Las métricas están deshabilitadas.');

            return self::FAILURE;
        }
        $agregador->sincronizar();
        $this->info('Metabase actualizado con datos agregados.');

        return self::SUCCESS;
    }
}
