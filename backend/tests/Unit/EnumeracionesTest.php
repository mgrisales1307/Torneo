<?php

namespace Tests\Unit;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Enumeraciones\RolUsuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Las reglas que viven en los enums: plantillas, formato de cuentas, puntos de
 * battle royale y el ciclo de vida de torneos e inscripciones.
 */
class EnumeracionesTest extends TestCase
{
    /**
     * @return array<string, array{Juego, int, int, int}>
     */
    public static function plantillas(): array
    {
        return [
            'valorant' => [Juego::Valorant, 5, 2, 7],
            'lol' => [Juego::LeagueOfLegends, 5, 2, 7],
            'dota2' => [Juego::Dota2, 5, 2, 7],
            'pubg' => [Juego::Pubg, 4, 1, 5],
            'free fire' => [Juego::FreeFire, 4, 1, 5],
        ];
    }

    #[DataProvider('plantillas')]
    public function test_cada_juego_tiene_su_plantilla(Juego $juego, int $titulares, int $suplentes, int $maximo): void
    {
        $this->assertSame($titulares, $juego->titulares());
        $this->assertSame($suplentes, $juego->suplentesMaximos());
        $this->assertSame($maximo, $juego->maximoDeMiembros());
    }

    public function test_hay_cinco_juegos_y_sus_valores_son_unicos(): void
    {
        $this->assertCount(5, Juego::cases());
        $this->assertSame(['valorant', 'lol', 'dota2', 'pubg', 'freefire'], Juego::valores());
        $this->assertSame(Juego::Pubg, Juego::from('pubg'));
        $this->assertNull(Juego::tryFrom('fortnite'));
    }

    public function test_cada_juego_describe_su_cuenta_con_un_nombre_y_un_ejemplo(): void
    {
        foreach (Juego::cases() as $juego) {
            $this->assertNotSame('', $juego->nombreDeCuenta(), $juego->value);
            $this->assertNotSame('', $juego->ejemploDeCuenta(), $juego->value);
        }

        $this->assertSame('Riot ID', Juego::Valorant->nombreDeCuenta());
        $this->assertSame('Riot ID', Juego::LeagueOfLegends->nombreDeCuenta());
        $this->assertSame('Steam ID', Juego::Dota2->nombreDeCuenta());
    }

    public function test_la_vinculacion_de_cuentas_ya_no_exige_un_formato(): void
    {
        // La vinculación es simulada: el formato dejó de validarse.
        $this->assertFalse(method_exists(Juego::class, 'formatoDeCuenta'));
    }

    public function test_los_puntos_de_battle_royale_siguen_la_tabla_oficial(): void
    {
        $this->assertSame(10, Juego::Pubg->puntosPorPosicion(1));
        $this->assertSame(6, Juego::Pubg->puntosPorPosicion(2));
        $this->assertSame(1, Juego::Pubg->puntosPorPosicion(8));
        $this->assertSame(0, Juego::Pubg->puntosPorPosicion(9));

        $this->assertSame(12, Juego::FreeFire->puntosPorPosicion(1));
        $this->assertSame(1, Juego::FreeFire->puntosPorPosicion(10));
        $this->assertSame(0, Juego::FreeFire->puntosPorPosicion(11));

        $this->assertSame(0, Juego::Valorant->puntosPorPosicion(1), 'Los duelos no puntúan por posición.');
        $this->assertSame(1, Juego::Pubg->puntosPorBaja());
    }

    public function test_solo_pubg_y_free_fire_son_battle_royale(): void
    {
        $this->assertTrue(Juego::Pubg->esBattleRoyale());
        $this->assertTrue(Juego::FreeFire->esBattleRoyale());
        $this->assertFalse(Juego::Valorant->esBattleRoyale());
        $this->assertFalse(Juego::LeagueOfLegends->esBattleRoyale());
        $this->assertFalse(Juego::Dota2->esBattleRoyale());

        $this->assertSame(16, Juego::Pubg->equiposPorPartida());
        $this->assertSame(12, Juego::FreeFire->equiposPorPartida());
        $this->assertSame(2, Juego::Valorant->equiposPorPartida());
    }

    public function test_cada_juego_solo_admite_los_formatos_de_su_tipo(): void
    {
        $this->assertSame([FormatoTorneo::BattleRoyale], Juego::Pubg->formatosPermitidos());
        $this->assertSame([FormatoTorneo::BattleRoyale], Juego::FreeFire->formatosPermitidos());

        $duelos = Juego::Valorant->formatosPermitidos();

        $this->assertCount(4, $duelos);
        $this->assertNotContains(FormatoTorneo::BattleRoyale, $duelos);
    }

    public function test_la_ficha_del_juego_trae_lo_que_consume_el_frontend(): void
    {
        $ficha = Juego::Valorant->comoArreglo();

        foreach (['valor', 'nombre', 'abreviatura', 'modalidad', 'es_battle_royale', 'titulares', 'suplentes_maximos', 'cuenta', 'formatos'] as $clave) {
            $this->assertArrayHasKey($clave, $ficha);
        }

        $this->assertSame('valorant', $ficha['valor']);
        $this->assertSame('Duelo 5v5', $ficha['modalidad']);
        $this->assertCount(4, $ficha['formatos']);
    }

    public function test_el_ciclo_de_vida_del_torneo(): void
    {
        $this->assertFalse(EstadoTorneo::Borrador->esVisibleAlPublico());
        $this->assertTrue(EstadoTorneo::InscripcionesAbiertas->esVisibleAlPublico());

        $this->assertTrue(EstadoTorneo::InscripcionesAbiertas->admiteInscripciones());
        $this->assertFalse(EstadoTorneo::InscripcionesCerradas->admiteInscripciones());
        $this->assertFalse(EstadoTorneo::EnCurso->admiteInscripciones());

        $this->assertTrue(EstadoTorneo::InscripcionesAbiertas->esperaInicio());
        $this->assertTrue(EstadoTorneo::InscripcionesCerradas->esperaInicio());
        $this->assertFalse(EstadoTorneo::EnCurso->esperaInicio());

        $this->assertTrue(EstadoTorneo::EnCurso->haEmpezado());
        $this->assertTrue(EstadoTorneo::Finalizado->haEmpezado());
        $this->assertFalse(EstadoTorneo::Cancelado->haEmpezado());

        $this->assertTrue(EstadoTorneo::Finalizado->estaCerrado());
        $this->assertTrue(EstadoTorneo::Cancelado->estaCerrado());
        $this->assertFalse(EstadoTorneo::EnCurso->estaCerrado());
    }

    public function test_todos_los_estados_de_torneo_tienen_etiqueta(): void
    {
        foreach (EstadoTorneo::cases() as $estado) {
            $this->assertNotSame('', $estado->etiqueta());
        }
    }

    public function test_el_ciclo_de_vida_de_la_inscripcion(): void
    {
        $this->assertFalse(EstadoInscripcion::PendientePago->estaPagada());
        $this->assertTrue(EstadoInscripcion::PendienteRevision->estaPagada());
        $this->assertTrue(EstadoInscripcion::Aprobada->estaPagada());
        $this->assertFalse(EstadoInscripcion::Rechazada->estaPagada());

        $this->assertTrue(EstadoInscripcion::Rechazada->estaCerrada());
        $this->assertTrue(EstadoInscripcion::Retirada->estaCerrada());
        $this->assertFalse(EstadoInscripcion::Aprobada->estaCerrada());
        $this->assertFalse(EstadoInscripcion::PendientePago->estaCerrada());
    }

    public function test_los_roles_del_staff(): void
    {
        $this->assertSame(['admin', 'empleado'], RolUsuario::valoresDeStaff());
        $this->assertSame(['admin', 'empleado', 'jugador'], RolUsuario::valores());
        $this->assertNotContains('jugador', RolUsuario::valoresDeStaff());
    }

    public function test_el_minimo_de_equipos_depende_del_formato(): void
    {
        $this->assertSame(2, FormatoTorneo::EliminacionSimple->minimoDeEquipos());
        $this->assertSame(2, FormatoTorneo::BattleRoyale->minimoDeEquipos());
        $this->assertSame(4, FormatoTorneo::EliminacionDoble->minimoDeEquipos());
        $this->assertSame(4, FormatoTorneo::GruposYEliminacion->minimoDeEquipos());
        $this->assertSame(4, FormatoTorneo::Suizo->minimoDeEquipos());
        $this->assertTrue(FormatoTorneo::BattleRoyale->esBattleRoyale());
        $this->assertFalse(FormatoTorneo::Suizo->esBattleRoyale());
    }
}
