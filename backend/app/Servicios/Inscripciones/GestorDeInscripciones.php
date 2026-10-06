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
 *   pendiente_pago ──pago aprobado──► pendiente_revision ──organización──► aprobada ──► check-in
 *          │                                   │
 *          └────────── retirada ◄───────────────┴──────────► rechazada
 */
class GestorDeInscripciones
{
    /**
     * Inscribe un equipo en un torneo.
     *
     * La comprobación del cupo y la creación van dentro de una transacción con
     * el torneo bloqueado: si dos capitanes piden el último lugar en el mismo
     * instante, uno de los dos recibe el error en vez de quedar los dos dentro.
     */
    public function inscribir(Torneo $torneo, Equipo $equipo): Inscripcion
    {
        return DB::transaction(function () use ($torneo, $equipo): Inscripcion {
            /** @var Torneo $torneo */
            $torneo = Torneo::query()->whereKey($torneo->getKey())->lockForUpdate()->firstOrFail();

            if ($equipo->juego !== $torneo->juego) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    'Este torneo es de %s y %s juega %s.',
                    $torneo->juego->nombre(),
                    $equipo->nombre,
                    $equipo->juego->nombre(),
                ));
            }

            $this->verificarVentana($torneo);
            $this->verificarPlantilla($torneo, $equipo);

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
                'check_in_en' => null,
                'semilla' => null,
                'grupo' => null,
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

            if ($torneo->estado->haEmpezado()) {
                throw ReglaDeNegocio::porqueSi('El torneo ya empezó: no entran más equipos.');
            }

            if ($torneo->inscripciones()->aprobadas()->count() >= $torneo->cupo_maximo_equipos) {
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

        if ($inscripcion->estaAprobada() && $inscripcion->torneo->estado->haEmpezado()) {
            throw ReglaDeNegocio::porqueSi('No se puede rechazar a un equipo que ya está jugando el torneo.');
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
     * El capitán retira a su propio equipo del torneo.
     */
    public function retirar(Inscripcion $inscripcion): Inscripcion
    {
        if ($inscripcion->estado->estaCerrada()) {
            throw ReglaDeNegocio::porqueSi('Esta inscripción ya está cerrada.');
        }

        if ($inscripcion->torneo->estado->haEmpezado()) {
            throw ReglaDeNegocio::porqueSi(
                'El torneo ya empezó: comunícate con la organización para retirar al equipo.'
            );
        }

        $inscripcion->forceFill([
            'estado' => EstadoInscripcion::Retirada,
            'revisado_en' => now(),
        ])->save();

        return $inscripcion;
    }

    /**
     * El capitán confirma que el equipo está listo para jugar. Sin esto, en
     * un torneo con check-in, el equipo no entra al cuadro.
     */
    public function hacerCheckIn(Inscripcion $inscripcion): Inscripcion
    {
        $torneo = $inscripcion->torneo;

        if (! $inscripcion->estaAprobada()) {
            throw ReglaDeNegocio::porqueSi('Solo hacen check-in los equipos con la inscripción aprobada.');
        }

        if (! $torneo->usaCheckIn()) {
            throw ReglaDeNegocio::porqueSi('Este torneo no usa check-in: el equipo ya está dentro.');
        }

        if (! $torneo->checkInAbierto()) {
            $abre = $torneo->checkInAbreEn();

            throw ReglaDeNegocio::porqueSi(
                $abre !== null && $abre->isFuture()
                    ? sprintf('El check-in abre el %s a las %s.', $abre->format('d/m'), $abre->format('H:i'))
                    : 'El check-in de este torneo ya cerró.'
            );
        }

        if (! $inscripcion->hizoCheckIn()) {
            $inscripcion->forceFill(['check_in_en' => now()])->save();
        }

        return $inscripcion;
    }

    private function verificarVentana(Torneo $torneo): void
    {
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
    }

    /**
     * La plantilla tiene que estar completa, cada jugador con su cuenta del
     * juego vinculada, y nadie puede jugar el mismo torneo con dos equipos.
     */
    private function verificarPlantilla(Torneo $torneo, Equipo $equipo): void
    {
        $juego = $torneo->juego;
        $miembros = $equipo->miembros()->with('cuentasDeJuego')->get();

        if ($miembros->count() < $juego->titulares()) {
            throw ReglaDeNegocio::porqueSi(sprintf(
                '%s necesita al menos %d jugadores y tiene %d.',
                $equipo->nombre,
                $juego->titulares(),
                $miembros->count(),
            ));
        }

        $sinCuenta = $miembros
            ->filter(fn (Usuario $miembro): bool => $miembro->cuentaPara($juego) === null)
            ->pluck('apodo');

        if ($sinCuenta->isNotEmpty()) {
            throw ReglaDeNegocio::porqueSi(sprintf(
                'Falta vincular el %s de: %s.',
                $juego->nombreDeCuenta(),
                $sinCuenta->join(', ', ' y '),
            ));
        }

        $repetido = Inscripcion::query()
            ->where('torneo_id', $torneo->getKey())
            ->whereNot('equipo_id', $equipo->getKey())
            ->vigentes()
            ->whereHas('equipo.miembros', fn ($consulta) => $consulta->whereIn('usuarios.id', $miembros->modelKeys()))
            ->with(['equipo.miembros' => fn ($consulta) => $consulta->whereIn('usuarios.id', $miembros->modelKeys())])
            ->first();

        if ($repetido !== null) {
            throw ReglaDeNegocio::porqueSi(sprintf(
                '%s ya juega este torneo con %s.',
                $repetido->equipo->miembros->pluck('apodo')->join(', ', ' y '),
                $repetido->equipo->nombre,
            ));
        }
    }
}
