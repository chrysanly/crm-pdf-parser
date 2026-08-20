<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Every mutating controller method calls a Policy (RULES §5.1).
    use AuthorizesRequests;
}
