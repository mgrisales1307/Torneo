<?php

namespace App\Servicios\Inscripciones;

use App\Enumeraciones\EstadoInscripcion;
use App\Excepciones\ReglaDeNegocio;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Dueño de las transiciones de una inscripción. Ningún controlador cambia el
 * estado a mano: todos pasan por aquí, y por eso las reglas del torneo se
 * comprueban siempre, sin importar desde qué pantalla se llegó.
 *
 *   pendiente_pago ──pago aprobado──► pendiente_revision ──organización──► aprobada
 *          │                                   │
 *          └────────── retirada ◄───────────────┴──────────► rechazada
 */
class GestorDeInscripciones
{
    /**
     * Inscribe un equipo en un torneo.
     *
     * La comprobación del cupo y la creación van dentro de una transacción con
     * el torneo bloqueado: si dos dueños piden el último lugar en el mismo
     * instante, uno de los dos recibe el error en vez de quedar los dos dentro.
     */
    public function inscribir(Torneo $torneo, Equipo $equipo): Inscripcion
    {
        return DB::transaction(function () use ($torneo, $equipo): Inscripcion {
            /** @var Torneo $torneo */
            $torneo = Torneo::query()->whereKey($torneo->getKey())->lockForUpdate()->firstOrFail();

            if (! $torneo->estado->admiteInscripciones()) {
                throw ReglaDeNegocio::porqueSi('Este torneo no tiene las inscripciones abiertas.');
            }

            $ahora = now();

            if ($torneo->inscripciones_abren_en !== null && $ahora->lt($torneo->inscripciones_abren_en)) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    'Las inscripciones de este torneo abren el %s.',
                    $torneo->inscripciones_abren_en->format('d/m/Y'),
                ));
            }

            if ($torneo->inscripciones_cierran_en !== null && $ahora->gt($torneo->inscripciones_cierran_en)) {
                throw ReglaDeNegocio::porqueSi('El plazo de inscripción de este torneo ya se cerró.');
            }

            if ($torneo->cuposDisponibles() < 1) {
                throw ReglaDeNegocio::porqueSi('El torneo ya tiene el cupo de equipos completo.');
            }

            $jugadoresActivos = $equipo->jugadores()->convocables()->count();

            if ($jugadoresActivos < $torneo->minimo_jugadores_por_equipo) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    'El torneo exige al menos %d jugadores activos y %s tiene %d.',
                    $torneo->minimo_jugadores_por_equipo,
                    $equipo->nombre,
                    $jugadoresActivos,
                ));
            }

            if ($jugadoresActivos > $torneo->maximo_jugadores_por_equipo) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    'El torneo admite como máximo %d jugadores y %s tiene %d activos.',
                    $torneo->maximo_jugadores_por_equipo,
                    $equipo->nombre,
                    $jugadoresActivos,
                ));
            }

            $inscripcion = Inscripcion::query()
                ->where('torneo_id', $torneo->getKey())
                ->where('equipo_id', $equipo->getKey())
                ->first();

            if ($inscripcion !== null && ! $inscripcion->estado->estaCerrada()) {
                throw ReglaDeNegocio::porqueSi('Este equipo ya está inscrito en el torneo.');
            }

            $inscripcion ??= new Inscripcion;

            $inscripcion->forceFill([
                'torneo_id' => $torneo->getKey(),
                'equipo_id' => $equipo->getKey(),
                'monto_centavos' => $torneo->costo_inscripcion_centavos,
                'moneda' => $torneo->moneda,
                'estado' => $torneo->esGratuito()
                    ? EstadoInscripcion::PendienteRevision
                    : EstadoInscripcion::PendientePago,
                'pagada_en' => $torneo->esGratuito() ? now() : null,
                'revisado_por_id' => null,
                'revisado_en' => null,
                'notas_revision' => null,
            ])->save();

            return $inscripcion;
        });
    }

    /**
     * La llama el gestor de cobros cuando la pasarela confirma el pago.
     */
    public function marcarComoPagada(Inscripcion $inscripcion): Inscripcion
    {
        if ($inscripcion->estado !== EstadoInscripcion::PendientePago) {
            return $inscripcion;
        }

        $inscripcion->forceFill([
            'estado' => EstadoInscripcion::PendienteRevision,
            'pagada_en' => now(),
        ])->save();

        return $inscripcion;
    }

    public function aprobar(Inscripcion $inscripcion, Usuario $revisor, ?string $notas = null): Inscripcion
    {
        if ($inscripcion->estado === EstadoInscripcion::Aprobada) {
            return $inscripcion;
        }

        if ($inscripcion->estado !== EstadoInscripcion::PendienteRevision) {
            throw ReglaDeNegocio::porqueSi(
                'Solo se aprueban las inscripciones que ya pagaron y están en revisión.'
            );
        }

        return DB::transaction(function () use ($inscripcion, $revisor, $notas): Inscripcion {
            /** @var Torneo $torneo */
            $torneo = Torneo::query()->whereKey($inscripcion->torneo_id)->lockForUpdate()->firstOrFail();

            if ($torneo->partidos()->exists()) {
                throw ReglaDeNegocio::porqueSi(
                    'El calendario de este torneo ya está generado: no se pueden aprobar más equipos sin volver a generarlo.'
                );
            }

            $aprobadas = $torneo->inscripciones()->aprobadas()->count();

            if ($aprobadas >= $torneo->cupo_maximo_equipos) {
                throw ReglaDeNegocio::porqueSi('El torneo ya tiene el cupo de equipos completo.');
            }

            $inscripcion->forceFill([
                'estado' => EstadoInscripcion::Aprobada,
                'revisado_por_id' => $revisor->getKey(),
                'revisado_en' => now(),
                'notas_revision' => $notas,
            ])->save();

            return $inscripcion;
        });
    }

    public function rechazar(Inscripcion $inscripcion, Usuario $revisor, string $notas): Inscripcion
    {
        if ($inscripcion->estado->estaCerrada()) {
            throw ReglaDeNegocio::porqueSi('Esta inscripción ya está cerrada.');
        }

        if ($inscripcion->estaAprobada() && $inscripcion->torneo->partidos()->exists()) {
            throw ReglaDeNegocio::porqueSi(
                'No se puede rechazar un equipo que ya tiene partidos en el calendario.'
            );
        }

        $inscripcion->forceFill([
            'estado' => EstadoInscripcion::Rechazada,
            'revisado_por_id' => $revisor->getKey(),
            'revisado_en' => now(),
            'notas_revision' => $notas,
        ])->save();

        return $inscripcion;
    }

    /**
     * El dueño retira a su propio equipo del torneo.
     */
    public function retirar(Inscripcion $inscripcion): Inscripcion
    {
        if ($inscripcion->estado->estaCerrada()) {
            throw ReglaDeNegocio::porqueSi('Esta inscripción ya está cerrada.');
        }

        if ($inscripcion->torneo->partidos()->delEquipo($inscripcion->equipo_id)->exists()) {
            throw ReglaDeNegocio::porqueSi(
                'El equipo ya tiene partidos programados: comunícate con la organización para retirarlo.'
            );
        }

        $inscripcion->forceFill([
            'estado' => EstadoInscripcion::Retirada,
            'revisado_en' => now(),
        ])->save();

        return $inscripcion;
    }
}
