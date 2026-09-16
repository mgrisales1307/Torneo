<?php

namespace Tests\Rasgos;

use Illuminate\Http\UploadedFile;

/**
 * Genera imágenes PNG reales para las pruebas de subida.
 *
 * Se arma el PNG a mano, byte a byte, en vez de usar `UploadedFile::fake()->image()`
 * porque ese método necesita la extensión GD, que la imagen de Docker del
 * proyecto no trae. Así las pruebas corren igual en cualquier PHP, y además el
 * archivo es un PNG de verdad: es lo que comprueban las reglas `image`,
 * `mimetypes` y `dimensions`.
 */
trait CreaImagenesDePrueba
{
    protected function imagenDePrueba(string $nombre = 'escudo.png', int $ancho = 400, int $alto = 400): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $this->contenidoPng($ancho, $alto));
    }

    protected function archivoQueNoEsImagen(string $nombre = 'trampa.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, '<?php echo "esto no es una imagen"; ?>');
    }

    private function contenidoPng(int $ancho, int $alto): string
    {
        $pixeles = '';

        for ($fila = 0; $fila < $alto; $fila++) {
            // Cada fila del PNG empieza con el byte de filtro (0 = ninguno).
            $pixeles .= chr(0).str_repeat(chr(18).chr(70).chr(58), $ancho);
        }

        $cabecera = pack('NN', $ancho, $alto)
            .chr(8)   // 8 bits por canal
            .chr(2)   // color verdadero (RGB)
            .chr(0).chr(0).chr(0);

        return "\x89PNG\r\n\x1a\n"
            .$this->bloquePng('IHDR', $cabecera)
            .$this->bloquePng('IDAT', gzcompress($pixeles, 9))
            .$this->bloquePng('IEND', '');
    }

    private function bloquePng(string $tipo, string $datos): string
    {
        return pack('N', strlen($datos)).$tipo.$datos.pack('N', crc32($tipo.$datos));
    }
}
