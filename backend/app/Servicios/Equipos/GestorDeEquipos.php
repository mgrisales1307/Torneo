<?php

namespace App\Servicios\Equipos;

use App\Enumeraciones\Juego;
use App\Excepciones\ReglaDeNegocio;
use App\Models\Equipo;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Altas, bajas y cargos dentro de un equipo. Todas las reglas de la plantilla
 * —cuántos caben, cuántos suplentes, que no cambie mientras compite— se
 * comprueban aquí, así que no importa desde qué pantalla se llegue.
 */
class GestorDeEquipos
{
    /**
     * @param  array{juego: string, nombre: string, abreviatura: string, color?: string|null}  $datos
     */
    public function crear(Usuario $capitan, array $datos): Equipo
    {
        return DB::transaction(function () use ($capitan, $datos): Equipo {
            $equipo = new Equipo([
                'nombre' => $datos['nombre'],
                'abreviatura' => $datos['abreviatura'],
                'color' => $datos['color'] ?? '#FF4655',
            ]);

            $equipo->forceFill([
                'juego' => Juego::from($datos['juego']),
                'capitan_id' => $capitan->getKey(),
            ])->save();

            $equipo->miembros()->attach($capitan->getKey(), ['es_suplente' => false]);

            return $equipo;
        });
    }

    public function unirse(Usuario $usuario, string $codigo): Equipo
    {
        return DB::transaction(function () use ($usuario, $codigo): Equipo {
            /** @var Equipo|null $equipo */
            $equipo = Equipo::query()
                ->where('codigo_invitacion', Str::upper(trim($codigo)))
                ->lockForUpdate()
                ->first();

            if ($equipo === null) {
                throw ReglaDeNegocio::porqueSi('No hay ningún equipo con ese código.', 'codigo');
            }

            if ($equipo->tieneMiembro($usuario)) {
                throw ReglaDeNegocio::porqueSi("Ya eres parte de {$equipo->nombre}.", 'codigo');
            }

            $this->verificarQueNoCompite($equipo);

            $miembros = $equipo->miembros()->get();

            if ($miembros->count() >= $equipo->juego->maximoDeMiembros()) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    '%s está completo: %d titulares y %d suplentes.',
                    $equipo->nombre,
                    $equipo->juego->titulares(),
                    $equipo->juego->suplentesMaximos(),
                ), 'codigo');
            }

            $titulares = $miembros->where('pivot.es_suplente', false)->count();

            $equipo->miembros()->attach($usuario->getKey(), [
                'es_suplente' => $titulares >= $equipo->juego->titulares(),
            ]);

            return $equipo;
        });
    }

    /**
     * El capitán pasa a alguien de titular a suplente o al revés.
     */
    public function cambiarPuesto(Equipo $equipo, Usuario $miembro, bool $suplente): void
    {
        $this->verificarQueEsMiembro($equipo, $miembro);
        $this->verificarQueNoCompite($equipo);

        $miembros = $equipo->miembros()->get();
        $actual = (bool) $miembros->firstWhere('id', $miembro->getKey())?->pivot->es_suplente;

        if ($actual === $suplente) {
            return;
        }

        if ($suplente) {
            $suplentes = $miembros->where('pivot.es_suplente', true)->count();

            if ($suplentes >= $equipo->juego->suplentesMaximos()) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    'En %s se admiten como máximo %d suplentes.',
                    $equipo->juego->nombre(),
                    $equipo->juego->suplentesMaximos(),
                ));
            }
        } else {
            $titulares = $miembros->where('pivot.es_suplente', false)->count();

            if ($titulares >= $equipo->juego->titulares()) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    'Ya hay %d titulares. Pasa a alguien a suplente primero.',
                    $equipo->juego->titulares(),
                ));
            }
        }

        $equipo->miembros()->updateExistingPivot($miembro->getKey(), ['es_suplente' => $suplente]);
    }

    public function cederCapitania(Equipo $equipo, Usuario $nuevo): void
    {
        $this->verificarQueEsMiembro($equipo, $nuevo);

        $equipo->forceFill(['capitan_id' => $nuevo->getKey()])->save();
    }

    /**
     * Sirve tanto para que el capitán saque a alguien como para que un
     * miembro se vaya por su cuenta.
     */
    public function retirar(Equipo $equipo, Usuario $miembro): void
    {
        $this->verificarQueEsMiembro($equipo, $miembro);

        if ($equipo->esCapitan($miembro)) {
            throw ReglaDeNegocio::porqueSi(
                'El capitán no puede salir del equipo. Cede la capitanía a otro miembro o elimina el equipo.'
            );
        }

        $this->verificarQueNoCompite($equipo);

        $equipo->miembros()->detach($miembro->getKey());
    }

    public function nuevoCodigo(Equipo $equipo): Equipo
    {
        $equipo->forceFill(['codigo_invitacion' => Equipo::codigoNuevo()])->save();

        return $equipo;
    }

    private function verificarQueEsMiembro(Equipo $equipo, Usuario $usuario): void
    {
        if (! $equipo->tieneMiembro($usuario)) {
            throw ReglaDeNegocio::porqueSi("{$usuario->apodo} no es parte de {$equipo->nombre}.");
        }
    }

    private function verificarQueNoCompite(Equipo $equipo): void
    {
        if ($equipo->estaCompitiendo()) {
            throw ReglaDeNegocio::porqueSi(
                "{$equipo->nombre} está jugando un torneo: la plantilla no cambia hasta que termine."
            );
        }
    }
}
