<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * `$this->authorize('verbo', $modelo)` de la Policy, y tambien
     * Gate::authorize(...) si hace falta invocarlo con un Gate explicito.
     */
    use AuthorizesRequests;
}
