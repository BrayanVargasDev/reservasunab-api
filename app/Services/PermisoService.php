<?php

namespace App\Services;

use App\Models\Permiso;
use App\Models\Usuario;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PermisoService
{
    public function getAll(int $perPage = 10, string $search = ''): LengthAwarePaginator
    {
        $todosLosPermisos = $this->getTodosLosPermisos();
        $usuarios = Usuario::with([
            'persona:id_persona,numero_documento,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,id_usuario',
            'persona.tipoDocumento:id_tipo,nombre,codigo',
            'rol:id_rol,nombre',
            'rol.permisos:id_permiso,nombre,descripcion,codigo,icono,id_pantalla',
            'permisosDirectos:id_permiso,nombre,descripcion,codigo,icono,id_pantalla'
        ])
            ->select(
                'usuarios.id_usuario',
                'usuarios.email',
                'usuarios.id_rol',
                'usuarios.tipos_usuario'
            )
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('usuarios.email', 'like', "%{$search}%")
                        ->orWhereHas('persona', function ($q) use ($search) {
                            $q->where('numero_documento', 'like', "%{$search}%")
                                ->orWhere('primer_nombre', 'like', "%{$search}%")
                                ->orWhere('segundo_nombre', 'like', "%{$search}%")
                                ->orWhere('primer_apellido', 'like', "%{$search}%")
                                ->orWhere('segundo_apellido', 'like', "%{$search}%");
                        })
                        ->orWhereHas('rol', function ($q) use ($search) {
                            $q->where('nombre', 'like', "%{$search}%");
                        });
                });
            })
            ->where('usuarios.id_usuario', '!=', Auth::id())
            ->orderBy('usuarios.id_usuario', 'asc')
            ->paginate($perPage);

        $usuarios->getCollection()->transform(function ($usuario) use ($todosLosPermisos) {
            $usuario->permisos_completos = $this->procesarPermisosUsuario($usuario, $todosLosPermisos);
            return $usuario;
        });

        return $usuarios;
    }

    public function getTodosLosPermisos()
    {
        return Permiso::with('pantalla:id_pantalla,nombre')
            ->select('id_permiso', 'nombre', 'descripcion', 'codigo', 'icono', 'id_pantalla')
            ->orderBy('nombre')
            ->get();
    }

    private function procesarPermisosUsuario(Usuario $usuario, $todosLosPermisos)
    {
        if ($usuario->esAdministrador()) {
            return $todosLosPermisos->map(function ($permiso) {
                $permisoClonado = clone $permiso;
                $permisoClonado->concedido = true;
                $permisoClonado->origen = 'administrador';
                unset($permisoClonado->pantalla);
                return $permisoClonado;
            });
        }

        $permisosUsuario = $this->obtenerIdsPermisosUsuario($usuario);

        return $todosLosPermisos->map(function ($permiso) use ($permisosUsuario) {
            $permisoConEstado = clone $permiso;
            $permisoConEstado->concedido = $permisosUsuario->has($permiso->id_permiso);
            $permisoConEstado->origen = $permisosUsuario->has($permiso->id_permiso)
                ? $permisosUsuario->get($permiso->id_permiso)
                : null;
            unset($permisoConEstado->pantalla);
            return $permisoConEstado;
        });
    }

    private function obtenerIdsPermisosUsuario(Usuario $usuario)
    {
        $permisosConOrigen = collect();

        if ($usuario->relationLoaded('permisosDirectos') && $usuario->permisosDirectos) {
            foreach ($usuario->permisosDirectos as $permiso) {
                $permisosConOrigen->put($permiso->id_permiso, 'directo');
            }
        }

        if ($usuario->rol && $usuario->relationLoaded('rol')) {
            if ($usuario->rol->relationLoaded('permisos') && $usuario->rol->permisos) {
                foreach ($usuario->rol->permisos as $permiso) {
                    if (!$permisosConOrigen->has($permiso->id_permiso)) {
                        $permisosConOrigen->put($permiso->id_permiso, 'rol: ' . $usuario->rol->nombre);
                    }
                }
            } else {
                $permisosDelRol = $usuario->rol->permisos()->get();
                foreach ($permisosDelRol as $permiso) {
                    if (!$permisosConOrigen->has($permiso->id_permiso)) {
                        $permisosConOrigen->put($permiso->id_permiso, 'rol: ' . $usuario->rol->nombre);
                    }
                }
            }
        }

        return $permisosConOrigen;
    }

    public function obtenerPermisosUnicos(Usuario $usuario)
    {
        if ($usuario->esAdministrador()) {
            return [
                'es_administrador' => true,
                'permisos' => collect(),
                'mensaje' => 'Usuario administrador - Acceso total'
            ];
        }

        // Usar el método del modelo que ya maneja la lógica correctamente
        $permisosUnicos = $usuario->obtenerTodosLosPermisos();

        return [
            'es_administrador' => false,
            'permisos' => $permisosUnicos,
        ];
    }

    public function getPermisosUsuario(int $idUsuario)
    {
        $usuario = Usuario::with([
            'persona:id_persona,numero_documento,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido',
            'rol:id_rol,nombre',
            'permisosDirectos:id_permiso,nombre,descripcion,codigo,icono,id_pantalla',
            'rol.permisos:id_permiso,nombre,descripcion,codigo,icono,id_pantalla'
        ])->findOrFail($idUsuario);

        return [
            'usuario' => $usuario,
            'permisos_procesados' => $this->obtenerPermisosUnicos($usuario)
        ];
    }

    public function getEstadisticasPermisos()
    {
        $usuarios = Usuario::with(['rol', 'permisosDirectos', 'rol.permisos'])->get();

        $estadisticas = [
            'total_usuarios' => $usuarios->count(),
            'administradores' => 0,
            'usuarios_con_permisos_directos' => 0,
            'usuarios_solo_permisos_rol' => 0,
            'usuarios_sin_permisos' => 0,
        ];

        foreach ($usuarios as $usuario) {
            if ($usuario->esAdministrador()) {
                $estadisticas['administradores']++;
            } else {
                $permisosDirectos = $usuario->permisosDirectos ? $usuario->permisosDirectos->count() : 0;
                $permisosRol = $usuario->rol ? $usuario->rol->permisos->count() : 0;

                if ($permisosDirectos > 0) {
                    $estadisticas['usuarios_con_permisos_directos']++;
                } elseif ($permisosRol > 0) {
                    $estadisticas['usuarios_solo_permisos_rol']++;
                } else {
                    $estadisticas['usuarios_sin_permisos']++;
                }
            }
        }

        return $estadisticas;
    }

    public function store(array $data): array
    {
        if ($this->isMultiple($data)) {
            $this->createMultiple($data);
            return [
                'message' => 'Permisos creados exitosamente',
                'count' => count($data),
            ];
        }

        $permiso = $this->create($data);
        return [
            'message' => 'Permiso creado exitosamente',
            'data' => $permiso,
        ];
    }

    protected function isMultiple(array $data): bool
    {
        return isset($data[0]) && is_array($data[0]);
    }

    public function create(array $data)
    {
        $permiso = Permiso::create($data);



        return $permiso;
    }

    public function createMultiple(array $permisos): array
    {
        $createdPermisos = [];

        DB::transaction(function () use ($permisos, &$createdPermisos) {
            foreach ($permisos as $permisoData) {
                $createdPermisos[] = Permiso::create([
                    'nombre' => $permisoData['nombre'],
                    'descripcion' => $permisoData['descripcion'] ?? null,
                    'codigo' => $permisoData['codigo'] ?? null,
                    'icono' => $permisoData['icono'] ?? null,
                    'id_pantalla' => $permisoData['idPantalla'] ?? null,
                ]);
            }
        });

        return $createdPermisos;
    }

    public function update(int $idPermiso, array $data)
    {
        $permiso = Permiso::findOrFail($idPermiso);
        $permiso->update($data);

        return $permiso;
    }

    public function delete(int $idPermiso)
    {
        $permiso = Permiso::findOrFail($idPermiso);
        $resultado = $permiso->delete();

        return $resultado;
    }

    public function getPermisosConEstadoParaUsuario(int $idUsuario): array
    {
        $usuario = Usuario::with([
            'permisosDirectos:id_permiso,nombre,descripcion,codigo,icono,id_pantalla',
            'rol:id_rol,nombre',
            'rol.permisos:id_permiso,nombre,descripcion,codigo,icono,id_pantalla'
        ])->findOrFail($idUsuario);

        $todosLosPermisos = $this->getTodosLosPermisos();

        $permisosConEstado = $this->procesarPermisosUsuario($usuario, $todosLosPermisos);

        return [
            'usuario' => [
                'id_usuario' => $usuario->id_usuario,
                'email' => $usuario->email,
                'tipo_usuario' => $usuario->tipos_usuario,
                'es_administrador' => $usuario->esAdministrador(),
            ],
            'rol' => $usuario->rol ? [
                'id_rol' => $usuario->rol->id_rol,
                'nombre' => $usuario->rol->nombre,
            ] : null,
            'permisos_completos' => $permisosConEstado,
            'estadisticas' => [
                'total_permisos' => $todosLosPermisos->count(),
                'permisos_concedidos' => $permisosConEstado->where('concedido', true)->count(),
                'permisos_directos' => $usuario->permisosDirectos ? $usuario->permisosDirectos->count() : 0,
                'permisos_por_rol' => $usuario->rol && $usuario->rol->permisos ? $usuario->rol->permisos->count() : 0,
            ]
        ];
    }

    public function asignarPermisosUsuario(int $idUsuario, array $permisos)
    {
        $usuario = Usuario::findOrFail($idUsuario);

        if (isset($permisos[0]) && is_array($permisos[0]) && isset($permisos[0]['concedido'])) {
            $permisosIds = $this->filtrarPermisosConcedidos($permisos);
        } else {
            $permisosIds = $permisos;
        }

        $usuario->permisosDirectos()->sync($permisosIds);

        return $usuario->load(['permisosDirectos', 'rol.permisos']);
    }


    private function filtrarPermisosConcedidos(array $permisos): array
    {
        return collect($permisos)
            ->filter(function ($permiso) {
                return isset($permiso['concedido']) && $permiso['concedido'] === true;
            })
            ->pluck('id_permiso')
            ->filter()
            ->values()
            ->toArray();
    }
}
