<?php

namespace Tests\Feature;

use Tests\TestCase;

class DespliegueTest extends TestCase
{
    public function test_las_rutas_de_la_spa_se_sirven_desde_el_archivo_compilado(): void
    {
        $directorio = sys_get_temp_dir().'/nexzone-spa-'.bin2hex(random_bytes(8));
        mkdir($directorio);
        file_put_contents($directorio.'/index.html', '<html><body>NexZone SPA</body></html>');
        $this->app->usePublicPath($directorio);

        try {
            foreach (['/', '/torneos', '/panel/cartera', '/organizacion/torneos'] as $ruta) {
                $respuesta = $this->get($ruta);
                $respuesta->assertOk();
                $this->assertSame($directorio.'/index.html', $respuesta->baseResponse->getFile()->getPathname());
            }

            $this->getJson('/api/v1/no-existe')->assertNotFound();
            $this->get('/storage/no-existe.png')->assertForbidden();
            $this->get('/assets/no-existe.js')->assertNotFound();
        } finally {
            unlink($directorio.'/index.html');
            rmdir($directorio);
        }
    }

    public function test_sin_compilar_la_spa_no_se_inventan_rutas(): void
    {
        $this->get('/panel/cartera')->assertNotFound();
    }
}
