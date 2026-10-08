<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\EstadoTorneo;
use App\Models\Torneo;
use App\Servicios\Carteras\GestorDeCarteras;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PremiosDeTorneo
{
    public function __construct(private readonly ClasificacionFinal $clasificacion, private readonly GestorDeCarteras $carteras) {}

    /** Se ejecuta en la transacción del resultado; reconcilia correcciones sin duplicar premios. */
    public function entregar(Torneo $torneo): void
    {
        $previos = DB::table('premios_entregados')->where('torneo_id', $torneo->getKey())->get();
        $nuevos = [];
        $inscripciones = $torneo->participantes()->with('equipo')->get()->keyBy('equipo_id');
        $clasificacion = $torneo->estado === EstadoTorneo::Finalizado ? $this->clasificacion->equipos($torneo) : [];
        foreach ($clasificacion as $indice => $equipoId) {
            $monto = (int) ($torneo->premios[$indice] ?? 0);
            $inscripcion = $inscripciones->get($equipoId);
            if ($monto > 0 && $inscripcion !== null) {
                $nuevos[] = ['puesto' => $indice + 1, 'equipo_id' => $equipoId,
                    'usuario_id' => $inscripcion->usuario_inscriptor_id ?? $inscripcion->equipo->capitan_id, 'monto_centavos' => $monto];
            }
        }
        $diferencias = [];
        foreach ($previos as $previo) {
            $diferencias[$previo->usuario_id] = ($diferencias[$previo->usuario_id] ?? 0) - $previo->monto_centavos;
        }
        foreach ($nuevos as $nuevo) {
            $diferencias[$nuevo['usuario_id']] = ($diferencias[$nuevo['usuario_id']] ?? 0) + $nuevo['monto_centavos'];
        }
        ksort($diferencias);
        foreach ($diferencias as $usuarioId => $monto) {
            if ($monto !== 0) {
                $this->carteras->mover($usuarioId, $torneo->moneda, $monto, $previos->isEmpty() ? 'premio' : 'ajuste_premio',
                    'Premio de '.$torneo->nombre, 'premio:'.Str::uuid(), $torneo->getKey());
            }
        }
        DB::table('premios_entregados')->where('torneo_id', $torneo->getKey())->delete();
        foreach ($nuevos as $nuevo) {
            DB::table('premios_entregados')->insert([...$nuevo, 'torneo_id' => $torneo->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
