<?php

namespace Database\Seeders;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoOrden;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\PosicionJugador;
use App\Enumeraciones\RolUsuario;
use App\Enumeraciones\TipoEventoPartido;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Orden;
use App\Models\Pago;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Servicios\Torneos\GeneradorDeCalendario;
use App\Servicios\Torneos\RegistroDeResultados;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deja la aplicación con algo que mirar desde el primer arranque: un torneo en
 * curso con ocho equipos, plantillas completas, calendario generado y las
 * primeras jornadas ya jugadas, más un segundo torneo con las inscripciones
 * abiertas para probar el flujo de pago.
 *
 * Solo se ejecuta si todavía no hay ningún torneo, así que reiniciar el
 * contenedor no duplica nada ni pisa lo que se haya creado a mano.
 */
class TorneoDeDemostracionSeeder extends Seeder
{
    private const EQUIPOS = [
        ['nombre' => 'Atlético Manrique', 'abreviatura' => 'ATM', 'ciudad' => 'Medellín', 'primario' => '#12463A', 'secundario' => '#EAB857'],
        ['nombre' => 'Deportivo Robledo', 'abreviatura' => 'DRO', 'ciudad' => 'Medellín', 'primario' => '#1C2B5E', 'secundario' => '#F2EFE6'],
        ['nombre' => 'Real Envigado', 'abreviatura' => 'REN', 'ciudad' => 'Envigado', 'primario' => '#7B1E2B', 'secundario' => '#F6D682'],
        ['nombre' => 'Unión Bello', 'abreviatura' => 'UBE', 'ciudad' => 'Bello', 'primario' => '#0E5C4A', 'secundario' => '#FFFFFF'],
        ['nombre' => 'Independiente Itagüí', 'abreviatura' => 'IIT', 'ciudad' => 'Itagüí', 'primario' => '#B4472A', 'secundario' => '#1B1B1B'],
        ['nombre' => 'Sporting Sabaneta', 'abreviatura' => 'SSA', 'ciudad' => 'Sabaneta', 'primario' => '#2E4A7D', 'secundario' => '#C8D6E5'],
        ['nombre' => 'Junior La Estrella', 'abreviatura' => 'JLE', 'ciudad' => 'La Estrella', 'primario' => '#D89B22', 'secundario' => '#101820'],
        ['nombre' => 'Racing Caldas', 'abreviatura' => 'RCA', 'ciudad' => 'Caldas', 'primario' => '#4A2C6B', 'secundario' => '#EDE3F5'],
    ];

    public function __construct(
        private readonly GeneradorDeCalendario $calendario,
        private readonly RegistroDeResultados $resultados,
    ) {}

    public function run(): void
    {
        if (Torneo::query()->exists()) {
            return;
        }

        $organizador = Usuario::query()->where('rol', RolUsuario::Administrador->value)->firstOrFail();
        $duenos = Usuario::query()->where('rol', RolUsuario::Dueno->value)->orderBy('id')->get();

        if ($duenos->isEmpty()) {
            $duenos = Usuario::factory()->count(3)->dueno()->create();
        }

        $equipos = $this->crearEquipos($duenos);

        $copa = $this->crearTorneoEnCurso($organizador);
        $this->inscribirEquipos($copa, $equipos);
        $this->calendario->generar($copa);
        $this->jugarPrimerasJornadas($copa, $organizador);

        $this->crearTorneoConInscripcionesAbiertas($organizador, $equipos);
    }

    /**
     * @param  Collection<int, Usuario>  $duenos
     * @return Collection<int, Equipo>
     */
    private function crearEquipos(Collection $duenos): Collection
    {
        return collect(self::EQUIPOS)->map(function (array $datos, int $indice) use ($duenos): Equipo {
            $equipo = new Equipo([
                'nombre' => $datos['nombre'],
                'abreviatura' => $datos['abreviatura'],
                'ciudad' => $datos['ciudad'],
                'estadio' => 'Cancha '.$datos['ciudad'],
                'descripcion' => 'Equipo participante de la liga aficionada de '.$datos['ciudad'].'.',
                'color_primario' => $datos['primario'],
                'color_secundario' => $datos['secundario'],
            ]);

            $equipo->dueno_id = $duenos[$indice % $duenos->count()]->getKey();
            $equipo->save();

            $this->crearPlantilla($equipo);

            return $equipo;
        });
    }

    private function crearPlantilla(Equipo $equipo): void
    {
        $posiciones = array_merge(
            [PosicionJugador::Portero, PosicionJugador::Portero],
            array_fill(0, 5, PosicionJugador::Defensa),
            array_fill(0, 5, PosicionJugador::Mediocampista),
            array_fill(0, 4, PosicionJugador::Delantero),
        );

        foreach ($posiciones as $indice => $posicion) {
            Jugador::factory()
                ->delEquipo($equipo)
                ->conDorsal($indice + 1)
                ->when($indice === 5, fn ($factory) => $factory->capitan())
                ->state(['posicion' => $posicion])
                ->create();
        }
    }

    private function crearTorneoEnCurso(Usuario $organizador): Torneo
    {
        $torneo = new Torneo([
            'nombre' => 'Copa Juegos Zone '.now()->year,
            'temporada' => (string) now()->year,
            'categoria' => 'Libre',
            'descripcion' => 'El torneo principal de la temporada: ocho equipos, todos contra todos, '
                .'y la tabla actualizándose fecha a fecha.',
            'formato' => FormatoTorneo::LigaIda,
            'estado' => EstadoTorneo::EnCurso,
            'ciudad' => 'Medellín',
            'sede' => 'Unidad Deportiva Atanasio Girardot',
            'cupo_maximo_equipos' => 8,
            'minimo_jugadores_por_equipo' => 7,
            'maximo_jugadores_por_equipo' => 20,
            'costo_inscripcion_centavos' => 15_000_000,
            'moneda' => 'COP',
            'inscripciones_abren_en' => now()->subMonths(2),
            'inscripciones_cierran_en' => now()->subMonth(),
            'inicia_en' => now()->subWeeks(4)->setTime(15, 0),
        ]);

        $torneo->creado_por_id = $organizador->getKey();
        $torneo->save();

        return $torneo;
    }

    /**
     * @param  Collection<int, Equipo>  $equipos
     */
    private function inscribirEquipos(Torneo $torneo, Collection $equipos): void
    {
        foreach ($equipos as $equipo) {
            $inscripcion = new Inscripcion;
            $inscripcion->forceFill([
                'torneo_id' => $torneo->getKey(),
                'equipo_id' => $equipo->getKey(),
                'estado' => EstadoInscripcion::Aprobada,
                'monto_centavos' => $torneo->costo_inscripcion_centavos,
                'moneda' => $torneo->moneda,
                'pagada_en' => now()->subMonth(),
                'revisado_en' => now()->subMonth(),
                'revisado_por_id' => $torneo->creado_por_id,
            ])->save();

            $this->registrarPagoDeDemostracion($inscripcion, $equipo);
        }
    }

    private function registrarPagoDeDemostracion(Inscripcion $inscripcion, Equipo $equipo): void
    {
        $orden = new Orden;
        $orden->forceFill([
            'referencia' => 'JZ-'.Str::upper(Str::random(14)),
            'inscripcion_id' => $inscripcion->getKey(),
            'usuario_id' => $equipo->dueno_id,
            'monto_centavos' => $inscripcion->monto_centavos,
            'moneda' => $inscripcion->moneda,
            'estado' => EstadoOrden::Pagada,
            'pasarela' => 'simulada',
            'expira_en' => now()->subMonth()->addHour(),
            'pagada_en' => now()->subMonth(),
        ])->save();

        Pago::factory()->create([
            'orden_id' => $orden->getKey(),
            'monto_centavos' => $orden->monto_centavos,
            'moneda' => $orden->moneda,
            'procesado_en' => now()->subMonth(),
        ]);
    }

    /**
     * Carga el resultado de las primeras jornadas pasando por el mismo servicio
     * que usa la organización, para que el marcador y los goleadores queden
     * necesariamente cuadrados.
     */
    private function jugarPrimerasJornadas(Torneo $torneo, Usuario $organizador): void
    {
        $partidos = $torneo->partidos()
            ->where('jornada', '<=', 4)
            ->with(['equipoLocal.jugadores', 'equipoVisitante.jugadores'])
            ->orderBy('jornada')
            ->get();

        foreach ($partidos as $partido) {
            $eventos = array_merge(
                $this->golesDe($partido->equipoLocal, random_int(0, 4)),
                $this->golesDe($partido->equipoVisitante, random_int(0, 3)),
            );

            $golesLocal = count(array_filter(
                $eventos,
                fn (array $evento): bool => $evento['equipo_id'] === $partido->equipo_local_id,
            ));

            $this->resultados->registrar($partido, $organizador, [
                'goles_local' => $golesLocal,
                'goles_visitante' => count($eventos) - $golesLocal,
                'observaciones' => null,
                'eventos' => $eventos,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function golesDe(Equipo $equipo, int $cantidad): array
    {
        if ($cantidad === 0) {
            return [];
        }

        $goleadores = $equipo->jugadores
            ->whereNotIn('posicion', [PosicionJugador::Portero])
            ->shuffle()
            ->take($cantidad);

        return $goleadores->map(fn (Jugador $jugador): array => [
            'equipo_id' => $equipo->getKey(),
            'jugador_id' => $jugador->getKey(),
            'tipo' => TipoEventoPartido::Gol->value,
            'minuto' => random_int(2, 90),
            'detalle' => null,
        ])->values()->all();
    }

    /**
     * Segundo torneo, con las inscripciones abiertas: sirve para probar de
     * punta a punta el alta de un equipo y el pago de su inscripción.
     *
     * @param  Collection<int, Equipo>  $equipos
     */
    private function crearTorneoConInscripcionesAbiertas(Usuario $organizador, Collection $equipos): void
    {
        $torneo = new Torneo([
            'nombre' => 'Torneo Apertura '.now()->addYear()->year,
            'temporada' => (string) now()->addYear()->year,
            'categoria' => 'Sub-20',
            'descripcion' => 'Inscripciones abiertas. Los equipos se inscriben, pagan y la organización revisa.',
            'formato' => FormatoTorneo::LigaIdaVuelta,
            'estado' => EstadoTorneo::InscripcionesAbiertas,
            'ciudad' => 'Medellín',
            'sede' => 'Cancha La Floresta',
            'cupo_maximo_equipos' => 10,
            'minimo_jugadores_por_equipo' => 7,
            'maximo_jugadores_por_equipo' => 18,
            'costo_inscripcion_centavos' => 12_000_000,
            'moneda' => 'COP',
            'inscripciones_abren_en' => now()->subWeek(),
            'inscripciones_cierran_en' => now()->addMonth(),
            'inicia_en' => now()->addMonths(2)->setTime(15, 0),
        ]);

        $torneo->creado_por_id = $organizador->getKey();
        $torneo->save();

        // Un equipo ya pagó y espera revisión; otro apenas se inscribió.
        $pendienteDeRevision = new Inscripcion;
        $pendienteDeRevision->forceFill([
            'torneo_id' => $torneo->getKey(),
            'equipo_id' => $equipos[0]->getKey(),
            'estado' => EstadoInscripcion::PendienteRevision,
            'monto_centavos' => $torneo->costo_inscripcion_centavos,
            'moneda' => $torneo->moneda,
            'pagada_en' => now()->subDays(2),
        ])->save();

        $this->registrarPagoDeDemostracion($pendienteDeRevision, $equipos[0]);

        $pendienteDePago = new Inscripcion;
        $pendienteDePago->forceFill([
            'torneo_id' => $torneo->getKey(),
            'equipo_id' => $equipos[1]->getKey(),
            'estado' => EstadoInscripcion::PendientePago,
            'monto_centavos' => $torneo->costo_inscripcion_centavos,
            'moneda' => $torneo->moneda,
        ])->save();
    }
}
