<?php

namespace Database\Seeders;

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Enumeraciones\RolUsuario;
use App\Models\CuentaDeJuego;
use App\Models\Equipo;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Servicios\Equipos\GestorDeEquipos;
use App\Servicios\Inscripciones\GestorDeInscripciones;
use App\Servicios\Pagos\FabricaDePasarelas;
use App\Servicios\Pagos\GestorDeCobros;
use App\Servicios\Torneos\InicioDeTorneo;
use App\Servicios\Torneos\PremiosDeTorneo;
use App\Servicios\Torneos\RegistroDeResultados;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExposicionSeeder extends Seeder
{
    public function __construct(
        private readonly GestorDeEquipos $equipos,
        private readonly GestorDeInscripciones $inscripciones,
        private readonly GestorDeCobros $cobros,
        private readonly FabricaDePasarelas $pasarelas,
        private readonly InicioDeTorneo $inicio,
        private readonly RegistroDeResultados $resultados,
        private readonly PremiosDeTorneo $premios,
    ) {}

    public function run(): void
    {
        $this->call(UsuariosSeeder::class);
        $admin = Usuario::where('correo', 'admin@juegoszone.com')->firstOrFail();
        $jugadores = [];
        foreach (['Parcero', 'Nova', 'Pixel', 'Phoenix', 'Shadow', 'Luna', 'Blaze', 'Kira'] as $indice => $apodo) {
            $correo = $indice === 0 ? 'jugador@juegoszone.com' : 'demo'.$indice.'@nexzone.local';
            $usuario = Usuario::firstOrCreate(['correo' => $correo], [
                'nombre' => 'Jugador '.$apodo, 'apodo' => $apodo, 'clave' => 'jugador123', 'rol' => RolUsuario::Jugador,
            ]);
            foreach (Juego::cases() as $juego) {
                if (! $usuario->cuentasDeJuego()->where('juego', $juego->value)->exists()) {
                    $identificador = match ($juego) {
                        Juego::Valorant, Juego::LeagueOfLegends => $usuario->apodo.'#DEMO',
                        Juego::Dota2 => '7656119'.str_pad((string) $usuario->id, 10, '0', STR_PAD_LEFT),
                        Juego::Pubg => 'Demo_'.$usuario->id,
                        Juego::FreeFire => (string) (400000000 + $usuario->id),
                    };
                    (new CuentaDeJuego)->forceFill(['usuario_id' => $usuario->id, 'juego' => $juego, 'identificador' => $identificador])->save();
                }
            }
            $jugadores[] = $usuario;
        }

        $this->escenario($admin, Juego::Valorant, 'Arena 1v1 — Último cupo', FormatoTorneo::EliminacionSimple, [
            'modalidad' => 'individual', 'cupo_maximo_equipos' => 4, 'premios' => [4000000, 2000000, 1000000],
        ], array_slice($jugadores, 1, 3), 'abierto');
        $this->escenario($admin, Juego::Dota2, 'Duelo de campeones — Listo para iniciar', FormatoTorneo::EliminacionSimple, [
            'modalidad' => 'individual', 'cupo_maximo_equipos' => 4, 'premios' => [5000000, 2500000, 1000000],
        ], array_slice($jugadores, 0, 4), 'lleno');
        $this->escenario($admin, Juego::Valorant, 'Neón Masters — Doble eliminación', FormatoTorneo::EliminacionDoble, [
            'modalidad' => 'individual', 'cupo_maximo_equipos' => 4, 'premios' => [8000000, 3000000, 1500000],
        ], array_slice($jugadores, 0, 4), 'en_curso', 2);
        $this->escenario($admin, Juego::LeagueOfLegends, 'Grieta Champions — Grupos y finales', FormatoTorneo::GruposYEliminacion, [
            'modalidad' => 'individual', 'cupo_maximo_equipos' => 6, 'cantidad_de_grupos' => 2, 'clasifican_por_grupo' => 2,
            'premios' => [6000000, 3000000, 1500000],
        ], array_slice($jugadores, 0, 6), 'en_curso', 6);
        $this->escenario($admin, Juego::Dota2, 'Roshan Challenge — Sistema suizo', FormatoTorneo::Suizo, [
            'modalidad' => 'individual', 'cupo_maximo_equipos' => 4, 'rondas_suizo' => 3, 'premios' => [4000000, 2000000, 1000000],
        ], array_slice($jugadores, 0, 4), 'en_curso', 2);
        $escuadras = [];
        foreach (['Cóndores Demo', 'Dragones Demo', 'Titanes Demo', 'Fénix Demo'] as $indice => $nombre) {
            $equipo = Equipo::where('nombre', $nombre)->where('juego', Juego::FreeFire->value)->first();
            if ($equipo === null) {
                $capitan = $jugadores[$indice];
                $equipo = $this->equipos->crear($capitan, ['juego' => Juego::FreeFire->value, 'nombre' => $nombre, 'abreviatura' => 'D'.($indice + 1)]);
                for ($numero = 1; $numero <= 3; $numero++) {
                    $miembro = Usuario::firstOrCreate(['correo' => 'escuadra'.$indice.'_'.$numero.'@nexzone.local'], [
                        'nombre' => 'Jugador de '.$nombre, 'apodo' => 'Escuadra'.$indice.'_'.$numero,
                        'clave' => 'jugador123', 'rol' => RolUsuario::Jugador,
                    ]);
                    (new CuentaDeJuego)->forceFill(['usuario_id' => $miembro->id, 'juego' => Juego::FreeFire, 'identificador' => (string) (500000000 + $miembro->id)])->save();
                    $this->equipos->unirse($miembro, $equipo->codigo_invitacion);
                }
            }
            $escuadras[] = $equipo;
        }
        if (count($escuadras) === 4) {
            $this->escenario($admin, Juego::FreeFire, 'Booyah Showcase — Escuadras', FormatoTorneo::BattleRoyale, [
                'modalidad' => 'equipos', 'cupo_maximo_equipos' => 4, 'partidas_battle_royale' => 3,
                'premios' => [10000000, 4000000, 2000000],
            ], $escuadras, 'en_curso', 1);
            $this->escenario($admin, Juego::FreeFire, 'Booyah Abierto — Inscribe tu equipo', FormatoTorneo::BattleRoyale, [
                'modalidad' => 'equipos', 'cupo_maximo_equipos' => 4, 'partidas_battle_royale' => 2,
                'premios' => [5000000, 2000000, 1000000],
            ], array_slice($escuadras, 1, 1), 'abierto');
        }
        $this->escenario($admin, Juego::Valorant, 'Copa del ganador — Premio en cartera', FormatoTorneo::EliminacionSimple, [
            'modalidad' => 'individual', 'cupo_maximo_equipos' => 2, 'premios' => [5000000, 1000000],
        ], array_slice($jugadores, 0, 2), 'finalizado');
        $this->escenario($admin, Juego::Pubg, 'Erangel Solo — Inscripciones abiertas', FormatoTorneo::BattleRoyale, [
            'modalidad' => 'individual', 'cupo_maximo_equipos' => 8, 'partidas_battle_royale' => 3,
            'premios' => [8000000, 4000000, 2000000],
        ], [], 'abierto');

        foreach (Torneo::where('estado', EstadoTorneo::Finalizado)->get() as $torneo) {
            DB::transaction(fn () => $this->premios->entregar($torneo), 3);
        }
    }

    /** @param list<Usuario|Equipo> $participantes */
    private function escenario(Usuario $admin, Juego $juego, string $nombre, FormatoTorneo $formato, array $ajustes, array $participantes, string $estado, int $partidos = 0): void
    {
        if (Torneo::where('nombre', $nombre)->exists()) {
            return;
        }
        DB::transaction(function () use ($admin, $juego, $nombre, $formato, $ajustes, $participantes, $estado, $partidos): void {
            $torneo = new Torneo([
                'nombre' => $nombre, 'juego' => $juego, 'formato' => $formato, 'flujo_automatico' => true,
                'cuenta_regresiva_segundos' => 60, 'check_in_minutos' => 0, 'mejor_de' => 1, 'mejor_de_final' => 3,
                'costo_inscripcion_centavos' => 1000000, 'moneda' => 'COP',
                'reglas' => "Demostración local: pagos, premios y retiros con saldo virtual.\nSolo la organización carga los marcadores; la clasificación y el avance son automáticos.\nLa cuenta regresiva comienza al completar todos los cupos confirmados.\nEn equipos, el premio completo pertenece a la persona que realizó la inscripción.",
                ...$ajustes,
            ]);
            $torneo->forceFill(['estado' => EstadoTorneo::InscripcionesAbiertas, 'creado_por_id' => $admin->id])->save();
            foreach ($participantes as $participante) {
                $equipo = $participante instanceof Equipo ? $participante : $this->equipos->individualPara($participante, $juego);
                $usuario = $equipo->capitan;
                $inscripcion = $this->inscripciones->inscribir($torneo, $equipo, $usuario);
                $orden = $this->cobros->ordenPara($inscripcion, $usuario);
                $this->cobros->registrarNotificacion($this->pasarelas->activa()->simularNotificacion($orden, true));
            }
            $torneo->refresh();
            if ($estado === 'abierto') {
                return;
            }
            $torneo->forceFill(['lleno_en' => now()->subMinutes(2)])->save();
            if ($estado === 'lleno') {
                return;
            }
            $this->inicio->iniciar($torneo);
            $limite = $estado === 'finalizado' ? 100 : $partidos;
            for ($i = 0; $i < $limite; $i++) {
                $partido = $torneo->partidos()->where('estado', 'por_jugar')->orderBy('id')->first();
                if ($partido === null) {
                    break;
                }
                if ($juego->esBattleRoyale()) {
                    $ids = $torneo->participantes()->pluck('equipo_id')->all();
                    $resultados = array_map(fn ($id, $indice) => ['equipo_id' => $id, 'posicion' => $indice + 1, 'bajas' => max(0, 6 - $indice * 2)], $ids, array_keys($ids));
                    $this->resultados->registrarBattleRoyale($partido, $admin, $resultados);
                } else {
                    $this->resultados->resolver($partido, $admin, ['victorias_a' => $partido->victoriasParaGanar(), 'victorias_b' => 0]);
                }
            }
        }, 3);
    }
}
