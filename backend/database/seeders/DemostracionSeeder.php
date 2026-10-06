<?php

namespace Database\Seeders;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Enumeraciones\RolUsuario;
use App\Models\CuentaDeJuego;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Partido;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Servicios\Equipos\GestorDeEquipos;
use App\Servicios\Torneos\InicioDeTorneo;
use App\Servicios\Torneos\RegistroDeResultados;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Llena una base recién creada con torneos de los cinco juegos, cada uno en un
 * momento distinto, para que todas las pantallas tengan algo que mostrar:
 *
 *   Valorant  Copa Neón           eliminación doble, en curso, con un reporte
 *                                 esperando que Parcero lo confirme
 *             Relámpago Valorant  inscripciones abiertas y gratis
 *   LoL       Grieta del Pacífico grupos terminados y eliminatorias en curso
 *   Dota 2    Ancients Cup        eliminación simple, finalizado, con campeón
 *             Suizo de Roshan     sistema suizo, primera ronda jugada
 *   PUBG      Erangel Colombia    battle royale, dos de cuatro partidas
 *   Free Fire Booyah Fest         inscripciones pagas, con el check-in abierto
 *
 * Los cuadros y resultados se generan con los mismos servicios que usa la
 * aplicación, así que la demostración nunca contradice las reglas.
 *
 * Corre solo en una base recién creada. Para cargarla sobre una base que ya
 * existe, sin borrar las cuentas:
 *
 *     php artisan db:seed --class=DemostracionSeeder
 *
 * Los resultados salen de un generador con semilla fija: cada base nueva
 * queda igual a la anterior.
 */
class DemostracionSeeder extends Seeder
{
    private const CLAVE = 'jugador123';

    private const APODOS = [
        'Tayrona', 'Cumbiambero', 'Quimbaya', 'Guatapé', 'Chiva', 'Arepa', 'Tinto', 'Bambuco',
        'Cóndor', 'Ceiba', 'Mochila', 'Guaduas', 'Sombrero', 'Palenque', 'Llanero', 'Totumo',
        'Chontaduro', 'Mompox', 'Panela', 'Frailejón', 'Barranquero', 'Tolú', 'Silleta', 'Yarumo',
        'Macondo', 'Guacamaya', 'Champeta', 'Joropo', 'Turpial', 'Cholao', 'Bullerengue', 'Morichal',
        'Capachos', 'Tamal', 'Neiva', 'Galeras', 'Nevado',
    ];

    private const EQUIPOS = [
        'valorant' => [
            ['Cóndores Andinos', 'CDA', '#FF4655'], ['Neón Medellín', 'NEON', '#2DE2E6'],
            ['Caribe Kings', 'CK', '#F9C80E'], ['Volcán Rojo', 'VR', '#E4572E'],
            ['Paisas Clan', 'PSC', '#7B2CBF'], ['Tayrona Gaming', 'TYG', '#06D6A0'],
            ['Llanos Esports', 'LLE', '#EF476F'], ['Nevado Five', 'NV5', '#4CC9F0'],
        ],
        'lol' => [
            ['Grieta Andina', 'GA', '#C89B3C'], ['Barón Caleño', 'BCL', '#0AC8B9'],
            ['Dragones del Cauca', 'DDC', '#E63946'], ['Nexo Bogotá', 'NXB', '#457B9D'],
            ['Jungla Amazonas', 'JAZ', '#2A9D8F'], ['Mid o Feed', 'MOF', '#F4A261'],
            ['Ward Caribe', 'WDC', '#8338EC'], ['Inhibidores', 'INH', '#FB5607'],
        ],
        'dota2' => [
            ['Ancestros', 'ANC', '#C23C2A'], ['Roshan Hunters', 'RSH', '#E9C46A'],
            ['Radiant Pacífico', 'RDP', '#80ED99'], ['Dire Montaña', 'DIM', '#9D4EDD'],
            ['Mid Lane Medellín', 'MLM', '#FF7B00'], ['Aegis Sur', 'AGS', '#48CAE4'],
            ['Tormenta Ígnea', 'TIG', '#D00000'], ['Courier Express', 'CEX', '#90BE6D'],
        ],
        'pubg' => [
            ['Erangel Squad', 'ERS', '#F2A900'], ['Miramar Wolves', 'MMW', '#D62828'],
            ['Pochinki Boys', 'PCK', '#588157'], ['Chicken Dinner', 'CHD', '#F77F00'],
            ['Zona Azul', 'ZAZ', '#4361EE'], ['Paracaídas', 'PRC', '#B5179E'],
            ['Sanhok Rangers', 'SKR', '#2D6A4F'], ['Air Drop', 'ADP', '#E85D04'],
            ['Vikendi Frost', 'VKF', '#90E0EF'], ['Bunker 9', 'BK9', '#6C757D'],
        ],
        'freefire' => [
            ['Booyah Bogotá', 'BBO', '#FF9F1C'], ['Kalahari Crew', 'KLC', '#E07A5F'],
            ['Bermuda Boys', 'BMB', '#3D5A80'], ['Purgatorio', 'PGT', '#9B2226'],
            ['Gloo Wall', 'GLW', '#00B4D8'], ['Headshot Caribe', 'HSC', '#FFB703'],
            ['Alpes Squad', 'ALP', '#8D99AE'], ['Clash Squad', 'CLS', '#EF233C'],
            ['Nexterra', 'NXT', '#06D6A0'], ['Fuego Paisa', 'FPS', '#F3722C'],
        ],
    ];

    private const MAPAS_VALORANT = ['Ascent', 'Bind', 'Haven', 'Lotus', 'Split', 'Sunset', 'Pearl'];

    private Usuario $organizador;

    /**
     * @var array<string, list<Equipo>>
     */
    private array $equipos = [];

    public function __construct(
        private readonly GestorDeEquipos $gestorDeEquipos,
        private readonly InicioDeTorneo $inicio,
        private readonly RegistroDeResultados $resultados,
    ) {}

    public function run(): void
    {
        mt_srand(2026);

        $this->organizador = Usuario::query()->where('rol', RolUsuario::Empleado->value)->firstOrFail();

        $jugadores = $this->crearJugadores();
        $this->crearEquipos($jugadores);

        $this->copaNeon();
        $this->relampagoValorant();
        $this->grietaDelPacifico();
        $this->ancientsCup();
        $this->suizoDeRoshan();
        $this->erangelColombia();
        $this->booyahFest();
    }

    /**
     * Los tres jugadores de prueba más 37 generados, todos con la cuenta de
     * los cinco juegos. Cuarenta alcanzan justo para ocho equipos de cinco o
     * diez escuadras de cuatro en cada juego.
     *
     * @return list<Usuario>
     */
    private function crearJugadores(): array
    {
        // Las cuentas de prueba se aseguran aquí también: así la demostración
        // se puede cargar sobre una base que ya existía, sin borrarla.
        $demostracion = array_map(fn (array $cuenta): Usuario => Usuario::query()->updateOrCreate(
            ['correo' => $cuenta['correo']],
            [...$cuenta, 'email_verified_at' => now()],
        ), UsuariosSeeder::DEMOSTRACION);

        $generados = array_map(fn (string $apodo): Usuario => Usuario::query()->create([
            'nombre' => 'Jugador '.$apodo,
            'apodo' => Str::ascii($apodo),
            'correo' => Str::lower(Str::ascii($apodo)).'@demo.juegoszone.com',
            'clave' => self::CLAVE,
            'rol' => RolUsuario::Jugador,
        ]), self::APODOS);

        $todos = [...$demostracion, ...$generados];

        foreach ($todos as $jugador) {
            foreach (Juego::cases() as $juego) {
                $cuenta = new CuentaDeJuego;
                $cuenta->forceFill([
                    'usuario_id' => $jugador->getKey(),
                    'juego' => $juego,
                    'identificador' => $this->cuentaPara($jugador, $juego),
                ])->save();
            }
        }

        return $todos;
    }

    private function cuentaPara(Usuario $jugador, Juego $juego): string
    {
        return match ($juego) {
            Juego::Valorant, Juego::LeagueOfLegends => Str::limit($jugador->apodo, 16, '').'#LAN',
            Juego::Pubg => Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', $jugador->apodo), 12, '').'_CO',
            // Identificadores con el formato real, derivados del id para que no
            // se repitan entre jugadores.
            Juego::Dota2 => '7656119'.str_pad((string) (8_000_000_000 + $jugador->getKey()), 10, '0', STR_PAD_LEFT),
            Juego::FreeFire => (string) (300_000_000 + $jugador->getKey() * 7919),
        };
    }

    /**
     * En cada juego los equipos se arman con los cuarenta jugadores en orden:
     * el primero de cada grupo es el capitán. Rotar el orden por juego hace
     * que los jugadores de prueba capitaneen en juegos distintos.
     *
     * @param  list<Usuario>  $jugadores
     */
    private function crearEquipos(array $jugadores): void
    {
        $rotacion = ['valorant' => 0, 'lol' => 1, 'dota2' => 3, 'pubg' => 2, 'freefire' => 5];

        foreach (self::EQUIPOS as $valor => $equipos) {
            $juego = Juego::from($valor);
            $orden = [...array_slice($jugadores, $rotacion[$valor]), ...array_slice($jugadores, 0, $rotacion[$valor])];
            $tamano = $juego->titulares();

            foreach ($equipos as $indice => [$nombre, $sigla, $color]) {
                $plantilla = array_slice($orden, $indice * $tamano, $tamano);

                $equipo = $this->gestorDeEquipos->crear($plantilla[0], [
                    'juego' => $valor,
                    'nombre' => $nombre,
                    'abreviatura' => $sigla,
                    'color' => $color,
                ]);

                foreach (array_slice($plantilla, 1) as $miembro) {
                    $this->gestorDeEquipos->unirse($miembro, $equipo->codigo_invitacion);
                }

                $this->equipos[$valor][] = $equipo;
            }
        }
    }

    private function copaNeon(): void
    {
        $torneo = $this->torneo(Juego::Valorant, 'Copa Neón Valorant', FormatoTorneo::EliminacionDoble, [
            'cupo_maximo_equipos' => 8,
            'mejor_de' => 3,
            'mejor_de_final' => 5,
            'costo_inscripcion_centavos' => 4_000_000,
            'premios' => [150_000_000, 60_000_000, 30_000_000],
            'inicia_en' => now()->subDays(2)->setTime(18, 0),
        ]);

        $this->inscribir($torneo, $this->equipos['valorant']);
        $this->inicio->iniciar($torneo);

        // La primera ronda de las dos llaves y una semifinal de arriba.
        $this->jugar($torneo, 6);

        // El rival de Cóndores Andinos ya reportó: a Parcero le toca confirmar
        // o disputar desde su panel.
        $this->reportarPorElRival($torneo, $this->equipos['valorant'][0]);
    }

    private function relampagoValorant(): void
    {
        $torneo = $this->torneo(Juego::Valorant, 'Relámpago Valorant', FormatoTorneo::EliminacionSimple, [
            'estado' => EstadoTorneo::InscripcionesAbiertas,
            'cupo_maximo_equipos' => 16,
            'costo_inscripcion_centavos' => 0,
            'check_in_minutos' => 30,
            'premios' => [50_000_000],
            'inicia_en' => now()->addDays(3)->setTime(19, 0),
            'inscripciones_cierran_en' => now()->addDays(2)->setTime(23, 59),
        ]);

        // Cóndores Andinos queda fuera a propósito: es el equipo de Parcero, y
        // así puede probar el flujo de inscripción.
        $this->inscribir($torneo, array_slice($this->equipos['valorant'], 1, 3));
    }

    private function grietaDelPacifico(): void
    {
        $torneo = $this->torneo(Juego::LeagueOfLegends, 'Grieta del Pacífico', FormatoTorneo::GruposYEliminacion, [
            'cupo_maximo_equipos' => 8,
            'cantidad_de_grupos' => 2,
            'clasifican_por_grupo' => 2,
            'mejor_de' => 1,
            'mejor_de_final' => 3,
            'costo_inscripcion_centavos' => 3_000_000,
            'premios' => [80_000_000, 30_000_000],
            'inicia_en' => now()->subDays(5)->setTime(17, 0),
        ]);

        $this->inscribir($torneo, $this->equipos['lol']);
        $this->inicio->iniciar($torneo);

        // Doce partidos de grupos y una semifinal.
        $this->jugar($torneo, 13);
    }

    private function ancientsCup(): void
    {
        $torneo = $this->torneo(Juego::Dota2, 'Ancients Cup', FormatoTorneo::EliminacionSimple, [
            'cupo_maximo_equipos' => 8,
            'mejor_de' => 3,
            'mejor_de_final' => 3,
            'costo_inscripcion_centavos' => 2_000_000,
            'premios' => [60_000_000, 20_000_000],
            'inicia_en' => now()->subWeeks(3)->setTime(16, 0),
        ]);

        $this->inscribir($torneo, $this->equipos['dota2']);
        $this->inicio->iniciar($torneo);
        $this->jugar($torneo);
    }

    private function suizoDeRoshan(): void
    {
        $torneo = $this->torneo(Juego::Dota2, 'Suizo de Roshan', FormatoTorneo::Suizo, [
            'cupo_maximo_equipos' => 8,
            'rondas_suizo' => 3,
            'mejor_de' => 1,
            'costo_inscripcion_centavos' => 0,
            'inicia_en' => now()->subDay()->setTime(20, 0),
        ]);

        $this->inscribir($torneo, $this->equipos['dota2']);
        $this->inicio->iniciar($torneo);
        $this->jugar($torneo, 4);
    }

    private function erangelColombia(): void
    {
        $torneo = $this->torneo(Juego::Pubg, 'Erangel Colombia', FormatoTorneo::BattleRoyale, [
            'cupo_maximo_equipos' => 16,
            'partidas_battle_royale' => 4,
            'costo_inscripcion_centavos' => 2_500_000,
            'premios' => [100_000_000, 50_000_000, 25_000_000],
            'inicia_en' => now()->subHours(2),
        ]);

        $this->inscribir($torneo, $this->equipos['pubg']);
        $this->inicio->iniciar($torneo);

        $participantes = $torneo->participantes()->pluck('equipo_id')->all();

        foreach ($torneo->partidos()->orderBy('ronda')->limit(2)->get() as $partida) {
            $puestos = $participantes;
            shuffle($puestos);

            $this->resultados->registrarBattleRoyale($partida, $this->organizador, array_map(
                fn (int $equipoId, int $indice): array => [
                    'equipo_id' => $equipoId,
                    'posicion' => $indice + 1,
                    'bajas' => max(0, mt_rand(0, 9) - intdiv($indice, 3)),
                ],
                $puestos,
                array_keys($puestos),
            ));
        }
    }

    private function booyahFest(): void
    {
        $torneo = $this->torneo(Juego::FreeFire, 'Booyah Fest', FormatoTorneo::BattleRoyale, [
            'estado' => EstadoTorneo::InscripcionesAbiertas,
            'cupo_maximo_equipos' => 12,
            'partidas_battle_royale' => 3,
            'check_in_minutos' => 60,
            'costo_inscripcion_centavos' => 2_000_000,
            'premios' => [80_000_000, 40_000_000, 20_000_000],
            'inicia_en' => now()->addMinutes(40),
        ]);

        $escuadras = $this->equipos['freefire'];

        // Aprobadas, con y sin check-in; una esperando revisión y otra el pago.
        $this->inscribir($torneo, array_slice($escuadras, 0, 4), checkIn: true);
        $this->inscribir($torneo, array_slice($escuadras, 4, 2));
        $this->inscribir($torneo, [$escuadras[6]], EstadoInscripcion::PendienteRevision);
        $this->inscribir($torneo, [$escuadras[7]], EstadoInscripcion::PendientePago);
    }

    /**
     * @param  array<string, mixed>  $ajustes
     */
    private function torneo(Juego $juego, string $nombre, FormatoTorneo $formato, array $ajustes): Torneo
    {
        $torneo = new Torneo([
            'juego' => $juego,
            'nombre' => $nombre,
            'formato' => $formato,
            'reglas' => $this->reglasPara($juego),
            'check_in_minutos' => 0,
            'inscripciones_abren_en' => now()->subWeeks(2),
            ...array_diff_key($ajustes, ['estado' => true]),
        ]);

        $torneo->forceFill([
            'estado' => $ajustes['estado'] ?? EstadoTorneo::InscripcionesCerradas,
            'creado_por_id' => $this->organizador->getKey(),
        ])->save();

        return $torneo;
    }

    /**
     * @param  list<Equipo>  $equipos
     */
    private function inscribir(Torneo $torneo, array $equipos, EstadoInscripcion $estado = EstadoInscripcion::Aprobada, bool $checkIn = false): void
    {
        foreach ($equipos as $equipo) {
            $pagada = $estado !== EstadoInscripcion::PendientePago;

            $inscripcion = new Inscripcion;
            $inscripcion->forceFill([
                'torneo_id' => $torneo->getKey(),
                'equipo_id' => $equipo->getKey(),
                'estado' => $estado,
                'monto_centavos' => $torneo->costo_inscripcion_centavos,
                'moneda' => $torneo->moneda,
                'pagada_en' => $pagada ? now()->subDays(3) : null,
                'revisado_por_id' => $estado === EstadoInscripcion::Aprobada ? $this->organizador->getKey() : null,
                'revisado_en' => $estado === EstadoInscripcion::Aprobada ? now()->subDays(2) : null,
                'check_in_en' => $checkIn ? now()->subMinutes(5) : null,
            ])->save();
        }
    }

    /**
     * Resuelve partidos listos para jugarse, en orden, hasta `$cuantos` o
     * hasta que no quede ninguno.
     */
    private function jugar(Torneo $torneo, int $cuantos = PHP_INT_MAX): void
    {
        for ($jugados = 0; $jugados < $cuantos; $jugados++) {
            $partido = $torneo->partidos()
                ->where('estado', EstadoPartido::PorJugar->value)
                ->orderBy('fase_id')
                ->orderByRaw("CASE llave WHEN 'superior' THEN 0 WHEN 'inferior' THEN 1 ELSE 2 END")
                ->orderBy('ronda')
                ->orderBy('numero')
                ->first();

            if ($partido === null) {
                return;
            }

            $this->resultados->resolver($partido, $this->organizador, $this->marcador($partido, $torneo->juego));
        }
    }

    /**
     * @return array{victorias_a: int, victorias_b: int, detalle: list<array{mapa: string|null, ganador: string}>}
     */
    private function marcador(Partido $partido, Juego $juego): array
    {
        $necesarias = $partido->victoriasParaGanar();
        $ganaA = mt_rand(1, 100) <= 58;
        $delPerdedor = mt_rand(0, $necesarias - 1);

        [$victoriasA, $victoriasB] = $ganaA ? [$necesarias, $delPerdedor] : [$delPerdedor, $necesarias];

        // El perdedor gana sus mapas primero y el ganador cierra la serie.
        $orden = [
            ...array_fill(0, $ganaA ? $victoriasB : $victoriasA, $ganaA ? 'b' : 'a'),
            ...array_fill(0, $necesarias, $ganaA ? 'a' : 'b'),
        ];

        $mapas = self::MAPAS_VALORANT;
        shuffle($mapas);

        return [
            'victorias_a' => $victoriasA,
            'victorias_b' => $victoriasB,
            'detalle' => array_map(fn (string $ganador, int $indice): array => [
                'mapa' => $juego === Juego::Valorant ? $mapas[$indice] : null,
                'ganador' => $ganador,
            ], $orden, array_keys($orden)),
        ];
    }

    private function reportarPorElRival(Torneo $torneo, Equipo $equipo): void
    {
        /** @var Partido|null $partido */
        $partido = $torneo->partidos()
            ->delEquipo($equipo->getKey())
            ->where('estado', EstadoPartido::PorJugar->value)
            ->first();

        if ($partido === null) {
            return;
        }

        $rival = Equipo::query()->findOrFail($partido->equipo_a_id === $equipo->getKey() ? $partido->equipo_b_id : $partido->equipo_a_id);
        $rivalEsA = $partido->equipo_a_id === $rival->getKey();
        $necesarias = $partido->victoriasParaGanar();

        $this->resultados->reportar($partido, $rival, $rival->capitan, [
            'victorias_a' => $rivalEsA ? $necesarias : 1,
            'victorias_b' => $rivalEsA ? 1 : $necesarias,
            'comentario' => 'Buen partido. Les dejo la captura del final.',
        ]);
    }

    private function reglasPara(Juego $juego): string
    {
        $cuenta = $juego->nombreDeCuenta();

        return implode("\n", array_filter([
            "Cada jugador juega con el {$cuenta} vinculado en su perfil.",
            $juego->esBattleRoyale()
                ? 'Salas personalizadas. Puntos por puesto según la tabla oficial, y 1 punto por baja.'
                : 'El capitán ganador reporta el marcador con la captura de la pantalla final.',
            'Tolerancia de 10 minutos: pasado ese tiempo, el partido se da por perdido.',
            'Los reclamos se hacen desde la página del partido, con captura.',
        ]));
    }
}
