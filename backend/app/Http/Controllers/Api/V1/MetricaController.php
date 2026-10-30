<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarMetricaRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MetricaController extends Controller
{
    public function __invoke(RegistrarMetricaRequest $peticion): Response
    {
        if (! config('metricas.habilitadas') || $peticion->header('DNT') === '1' || $peticion->header('Sec-GPC') === '1') {
            return response()->noContent();
        }

        $datos = $peticion->safe()->except('consentimiento');
        DB::table('eventos_metrica')->insertOrIgnore([
            ...$datos,
            'valor' => $datos['valor'] ?? null,
            'created_at' => now(),
        ]);
        if ($datos['tipo'] !== 'pagina') {
            DB::table('eventos_metrica')->where('id', $datos['id'])->where('sesion', $datos['sesion'])
                ->where('tipo', $datos['tipo'])->update(['valor' => $datos['valor']]);
        }

        return response()->noContent();
    }
}
