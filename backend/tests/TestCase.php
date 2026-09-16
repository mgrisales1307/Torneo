<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Marca la petición como venida del frontend declarado en
     * SANCTUM_STATEFUL_DOMAINS.
     *
     * Sin esta cabecera, Sanctum trata la petición como una llamada de máquina
     * a máquina: no abre sesión, y el login —que necesita sesión— no tendría
     * dónde apoyarse. El navegador la envía solo; en las pruebas hay que
     * ponerla a mano.
     */
    protected function desdeElFrontend(): static
    {
        return $this->withHeader('Origin', 'http://localhost:5173');
    }
}
