<?php

namespace App\Servicios\Metricas;

use Illuminate\Support\Facades\DB;

/** Firma únicamente el panel provisionado por el servidor; nunca un ID del navegador. */
class PanelDeMetabase
{
    /** @return array<string, mixed> */
    public function configuracion(): array
    {
        if (! config('metricas.habilitadas')) {
            return ['configurado' => false];
        }

        $panel = (int) DB::table('configuracion_metricas')->where('clave', 'metabase_panel')->value('valor');
        $url = rtrim((string) config('metricas.metabase.url'), '/');
        $clave = (string) config('metricas.metabase.clave');

        if (! $panel || ! $url || strlen($clave) < 32) {
            return ['configurado' => false];
        }

        $codificar = fn (string $valor): string => rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
        $cabecera = $codificar(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $carga = $codificar(json_encode([
            'resource' => ['dashboard' => $panel],
            'params' => (object) [],
            'exp' => now()->addMinutes(10)->timestamp,
        ], JSON_THROW_ON_ERROR));
        $firma = $codificar(hash_hmac('sha256', $cabecera.'.'.$carga, $clave, true));

        return [
            'configurado' => true,
            'url' => $url,
            'jwt' => $cabecera.'.'.$carga.'.'.$firma,
            'actualizado_en' => DB::table('configuracion_metricas')->where('clave', 'ultima_sincronizacion')->value('valor'),
        ];
    }
}
