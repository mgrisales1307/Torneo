<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Habilita $this->authorize() en todos los controladores: la autorización
     * de cada acción se delega en la política del modelo.
     */
    use AuthorizesRequests;
}
