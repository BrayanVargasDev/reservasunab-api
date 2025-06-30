<?php

namespace App\Services;

use App\Models\Categoria;

class CategoriaService
{
    public function getAll()
    {
        return Categoria::orderBy('nombre', 'asc')->get();
    }
}
