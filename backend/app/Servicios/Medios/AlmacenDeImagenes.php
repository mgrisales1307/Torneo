<?php

namespace App\Servicios\Medios;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use InvalidArgumentException;

/**
 * Único punto por el que entran y salen las imágenes del proyecto: escudos,
 * uniformes, fotos de jugadores y logos de torneo.
 *
 * Decisiones que se aplican aquí y no se repiten en cada controlador:
 *
 * - El nombre del archivo lo genera el servidor (hash aleatorio + extensión
 *   derivada del contenido). El nombre que manda el navegador no se usa nunca,
 *   así no hay forma de escribir fuera de la carpeta ni de subir `foto.php`.
 * - Al reemplazar una imagen se borra la anterior, para que el disco no crezca
 *   con archivos huérfanos.
 * - Las URL que se devuelven son relativas (`/storage/...`). El navegador solo
 *   habla con su propio origen y Vite reenvía la petición, igual que con /api.
 */
class AlmacenDeImagenes
{
    /**
     * Reglas de validación de una imagen, tomadas de config/medios.php.
     *
     * @return array<int, mixed>
     */
    public function reglasDeValidacion(string $tipo): array
    {
        $limites = $this->limites($tipo);

        return [
            'file',
            'image',
            'mimes:'.implode(',', config('medios.extensiones')),
            'mimetypes:'.implode(',', config('medios.tipos_mime')),
            'max:'.$limites['kb'],
            $this->dimensiones($limites),
        ];
    }

    /**
     * Guarda la imagen y devuelve su ruta relativa dentro del disco. Si se
     * indica una ruta anterior, se borra después de guardar la nueva.
     */
    public function guardar(UploadedFile $archivo, string $tipo, ?string $rutaAnterior = null): string
    {
        $ruta = $archivo->store($this->carpeta($tipo), $this->nombreDelDisco());

        if ($ruta === false) {
            throw new InvalidArgumentException('No fue posible guardar la imagen.');
        }

        if ($rutaAnterior !== null && $rutaAnterior !== $ruta) {
            $this->eliminar($rutaAnterior);
        }

        return $ruta;
    }

    public function eliminar(?string $ruta): void
    {
        if (blank($ruta)) {
            return;
        }

        $this->disco()->delete($ruta);
    }

    /**
     * @param  array<int, string|null>  $rutas
     */
    public function eliminarVarias(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            $this->eliminar($ruta);
        }
    }

    public function url(?string $ruta): ?string
    {
        if (blank($ruta)) {
            return null;
        }

        if ($this->nombreDelDisco() === 'public') {
            return '/storage/'.ltrim($ruta, '/');
        }

        return $this->disco()->url($ruta);
    }

    /**
     * @param  array{kb: int, minimo_px: int, maximo_px: int}  $limites
     */
    private function dimensiones(array $limites): Dimensions
    {
        return Rule::dimensions()
            ->minWidth($limites['minimo_px'])
            ->minHeight($limites['minimo_px'])
            ->maxWidth($limites['maximo_px'])
            ->maxHeight($limites['maximo_px']);
    }

    /**
     * @return array{kb: int, minimo_px: int, maximo_px: int}
     */
    private function limites(string $tipo): array
    {
        $limites = config("medios.limites.$tipo");

        if (! is_array($limites)) {
            throw new InvalidArgumentException("No hay límites configurados para el tipo de imagen «$tipo».");
        }

        return $limites;
    }

    private function carpeta(string $tipo): string
    {
        $carpeta = config("medios.carpetas.$tipo");

        if (! is_string($carpeta)) {
            throw new InvalidArgumentException("No hay carpeta configurada para el tipo de imagen «$tipo».");
        }

        return $carpeta;
    }

    private function nombreDelDisco(): string
    {
        return (string) config('medios.disco', 'public');
    }

    private function disco(): Filesystem
    {
        return Storage::disk($this->nombreDelDisco());
    }
}
