<?php

namespace App\Models\Rasgos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Genera el slug de un modelo a partir de una columna de texto y garantiza
 * que sea único, incluso frente a registros con baja lógica.
 *
 * Al dar de baja un registro, su slug se libera con un sufijo: así el nombre
 * vuelve a estar disponible para uno nuevo sin chocar con el índice único.
 *
 * @phpstan-require-extends Model
 */
trait TieneSlug
{
    public static function bootTieneSlug(): void
    {
        static::creating(function (Model $modelo): void {
            if (blank($modelo->getAttribute('slug'))) {
                $modelo->setAttribute('slug', $modelo->generaSlugUnico(
                    (string) $modelo->getAttribute($modelo->columnaOrigenDelSlug())
                ));
            }
        });

        static::deleted(function (Model $modelo): void {
            if (! method_exists($modelo, 'trashed') || ! $modelo->trashed()) {
                return;
            }

            $modelo->newQueryWithoutScopes()
                ->whereKey($modelo->getKey())
                ->update(['slug' => Str::limit($modelo->getAttribute('slug'), 200, '').'-baja-'.$modelo->getKey()]);
        });
    }

    /**
     * Columna de la que se deriva el slug.
     */
    protected function columnaOrigenDelSlug(): string
    {
        return 'nombre';
    }

    public function generaSlugUnico(string $texto): string
    {
        $base = Str::slug($texto) ?: Str::lower(Str::random(8));
        $slug = $base;
        $sufijo = 2;

        while ($this->slugYaEstaEnUso($slug)) {
            $slug = $base.'-'.$sufijo;
            $sufijo++;
        }

        return $slug;
    }

    protected function slugYaEstaEnUso(string $slug): bool
    {
        return $this->newQueryWithoutScopes()
            ->where('slug', $slug)
            ->when($this->exists, fn ($consulta) => $consulta->whereKeyNot($this->getKey()))
            ->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
