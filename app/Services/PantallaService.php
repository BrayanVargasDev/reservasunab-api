<?php

namespace App\Services;

use App\Models\Pantalla;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PantallaService
{
    private const CACHE_KEY_PREFIX = 'pantallas_';
    private const CACHE_TTL = 3600;

    public function getAll()
    {
        // $cacheKey = self::CACHE_KEY_PREFIX . 'all_' . 'permisos';
        // $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
        return Pantalla::select(
            'pantallas.nombre',
            'pantallas.icono',
            'pantallas.id_pantalla',
            'pantallas.visible',
            'pantallas.orden',
            'pantallas.ruta'
        )->with(['permisos:id_permiso,codigo,icono,descripcion,id_pantalla'])->get();
        // });

        // return $data;
    }

    public function store(array $data)
    {
        return Pantalla::create($data);
    }
}
