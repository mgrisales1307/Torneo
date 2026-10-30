<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\Metricas\PanelDeMetabase;
use Illuminate\Http\JsonResponse;

class MetricasAdminController extends Controller
{
    public function __invoke(PanelDeMetabase $panel): JsonResponse
    {
        return response()->json($panel->configuracion())->header('Cache-Control', 'no-store, private');
    }
}
