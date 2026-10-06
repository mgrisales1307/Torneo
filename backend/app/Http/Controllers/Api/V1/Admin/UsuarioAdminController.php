<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enumeraciones\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Listado de usuarios para la organización.
 */
class UsuarioAdminController extends Controller
{
    public function index(Request $peticion): AnonymousResourceCollection
    {
        $usuarios = Usuario::query()
            ->withCount('equipos')
            ->when(
                $peticion->filled('rol') && RolUsuario::tryFrom((string) $peticion->string('rol')) !== null,
                fn ($consulta) => $consulta->where('rol', $peticion->string('rol')),
            )
            ->when($peticion->filled('buscar'), function ($consulta) use ($peticion): void {
                $termino = '%'.$peticion->string('buscar').'%';
                $consulta->where(fn ($filtro) => $filtro
                    ->where('apodo', 'like', $termino)
                    ->orWhere('nombre', 'like', $termino)
                    ->orWhere('correo', 'like', $termino));
            })
            ->orderBy('apodo')
            ->paginate(25)
            ->withQueryString();

        return UsuarioResource::collection($usuarios);
    }
}
