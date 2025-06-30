<?php

namespace App\Services;

use App\Models\Sede;

class SedeService
{
    public function getAll()
    {
        return Sede::orderBy('nombre', 'asc')->get();
    }
}
