<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\Permiso;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RolService
{
    private const CACHE_KEY_PREFIX = 'roles_';
    private const CACHE_TTL = 3600;

    private $permisoService;

    public function __construct(PermisoService $permisoService)
    {
        $this->permisoService = $permisoService;
    }

    public function getAll()
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'all';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return Rol::orderBy('id_rol')->get();
        });
    }

    public function getAllWithPermisos(int $perPage = 10)
    {
        $todosLosPermisos = $this->permisoService->getTodosLosPermisos();

        $roles = Rol::with([
            'permisos:id_permiso,nombre,descripcion,codigo,icono,id_pantalla'
        ])
            ->where('nombre', '!=', 'Administrador')
            ->select('id_rol', 'nombre', 'descripcion', 'creado_en', 'actualizado_en')
            ->orderBy('nombre', 'asc')
            ->paginate($perPage);

        $roles->getCollection()->transform(function ($rol) use ($todosLosPermisos) {
            $rol->permisos_completos = $this->procesarPermisosRol($rol, $todosLosPermisos);
            return $rol;
        });
        return $roles;
    }

    private function procesarPermisosRol(Rol $rol, $todosLosPermisos)
    {
        $permisosDelRol = $rol->permisos->pluck('id_permiso')->flip();

        return $todosLosPermisos->map(function ($permiso) use ($permisosDelRol) {
            $permisoConEstado = clone $permiso;
            $permisoConEstado->concedido = $permisosDelRol->has($permiso->id_permiso);
            unset($permisoConEstado->pantalla);
            return $permisoConEstado;
        });
    }

    public function getPermisosRol(int $idRol)
    {
        $rol = Rol::with([
            'permisos:id_permiso,nombre,descripcion,codigo,icono,id_pantalla'
        ])->findOrFail($idRol);

        $todosLosPermisos = $this->permisoService->getTodosLosPermisos();

        return [
            'rol' => $rol,
            'permisos_procesados' => $this->procesarPermisosRol($rol, $todosLosPermisos)
        ];
    }

    public function asignarPermisos(int $idRol, array $permisos)
    {
        $rol = Rol::findOrFail($idRol);

        // Si los permisos vienen como objetos con propiedad concedido, filtrarlos
        if (isset($permisos[0]) && is_array($permisos[0]) && isset($permisos[0]['concedido'])) {
            $permisosIds = $this->filtrarPermisosConcedidos($permisos);
        } else {
            // Si vienen como array simple de IDs, usarlos directamente
            $permisosIds = $permisos;
        }

        $rol->permisos()->sync($permisosIds);

        $this->clearRolesCache();
        $this->clearPermisosCache();

        return $rol->load('permisos');
    }

    public function getEstadisticasRoles()
    {
        $roles = Rol::with('permisos')
            ->where('nombre', '!=', 'Administrador')
            ->get();

        $estadisticas = [
            'total_roles' => $roles->count(),
            'roles_con_permisos' => 0,
            'roles_sin_permisos' => 0,
            'promedio_permisos_por_rol' => 0,
        ];

        $totalPermisos = 0;

        foreach ($roles as $rol) {
            $cantidadPermisos = $rol->permisos->count();

            if ($cantidadPermisos > 0) {
                $estadisticas['roles_con_permisos']++;
                $totalPermisos += $cantidadPermisos;
            } else {
                $estadisticas['roles_sin_permisos']++;
            }
        }

        $estadisticas['promedio_permisos_por_rol'] = $estadisticas['total_roles'] > 0
            ? round($totalPermisos / $estadisticas['total_roles'], 2)
            : 0;

        return $estadisticas;
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $permisos = $data['permisos'] ?? [];
            unset($data['permisos']);

            $rol = Rol::create($data);

            if (!empty($permisos)) {
                $permisosConcedidos = $this->filtrarPermisosConcedidos($permisos);

                if (!empty($permisosConcedidos)) {
                    $rol->permisos()->sync($permisosConcedidos);
                }
            }

            $this->clearRolesCache();
            $this->clearPermisosCache();

            return $rol->load('permisos');
        });
    }

    public function update(int $idRol, array $data)
    {
        return DB::transaction(function () use ($idRol, $data) {
            $rol = Rol::findOrFail($idRol);

            $permisos = $data['permisos'] ?? null;
            unset($data['permisos']); // Remover permisos del array para no pasarlos al update

            $rol->update($data);

            if ($permisos !== null) {
                $permisosConcedidos = $this->filtrarPermisosConcedidos($permisos);

                // Sincronizar permisos (esto reemplazará todos los permisos existentes)
                $rol->permisos()->sync($permisosConcedidos);
            }

            $this->clearRolesCache();
            $this->clearPermisosCache();

            return $rol->load('permisos');
        });
    }

    public function delete(int $idRol)
    {
        $rol = Rol::findOrFail($idRol);

        if ($rol->usuarios()->exists()) {
            throw new \Exception('No se puede eliminar el rol porque tiene usuarios asignados');
        }

        $resultado = $rol->delete();

        $this->clearRolesCache();

        return $resultado;
    }

    public function limpiarCache()
    {
        $this->clearRolesCache();
        $this->clearPermisosCache();

        Log::info('Caché de roles y permisos limpiado completamente');
    }

    private function clearRolesCache(): void
    {
        $paginasPorLimpiar = range(1, 10);
        $tamanosPagina = [10, 20, 50];

        foreach ($tamanosPagina as $perPage) {
            foreach ($paginasPorLimpiar as $page) {
                Cache::forget(self::CACHE_KEY_PREFIX . "all_{$perPage}");
                Cache::forget(self::CACHE_KEY_PREFIX . "with_permisos_{$perPage}");
            }
        }

        try {
            $keys = Cache::getRedis()->keys(config('cache.prefix') . self::CACHE_KEY_PREFIX . '*');

            if (!empty($keys)) {
                $keysToForget = array_map(function ($key) {
                    return str_replace(config('cache.prefix'), '', $key);
                }, $keys);

                foreach ($keysToForget as $key) {
                    Cache::forget($key);
                }
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo limpiar caché de roles usando Redis: ' . $e->getMessage());
        }
    }

    private function clearPermisosCache(): void
    {
        Cache::forget('todos_los_permisos_roles');
        Cache::forget('todos_los_permisos');
    }

    /**
     * Filtra y extrae los IDs de permisos que están marcados como concedidos
     *
     * @param array $permisos Array de permisos con estructura [{id_permiso: int, concedido: bool}, ...]
     * @return array Array de IDs de permisos concedidos
     */
    private function filtrarPermisosConcedidos(array $permisos): array
    {
        return collect($permisos)
            ->filter(function ($permiso) {
                return isset($permiso['concedido']) && $permiso['concedido'] === true;
            })
            ->pluck('id_permiso')
            ->filter() // Remover valores null o vacíos
            ->values()
            ->toArray();
    }
}
