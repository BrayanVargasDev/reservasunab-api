<?php

namespace App\Services;

use App\Exceptions\UsuarioException;
use App\Models\Persona;
use App\Models\Usuario;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UsuarioService
{
    // Constante para tipo de documento por defecto
    private const DEFAULT_TIPO_DOCUMENTO_ID = 1; // CC - Cédula de Ciudadanía

    // Constantes para campos de mapeo
    private const USUARIO_FIELDS_MAPPING = [
        'email' => 'email',
        'tipo_usuario' => 'tipo_usuario',
        'id_persona' => 'id_persona',
        'activo' => 'activo',
    ];

    private const PERSONA_FIELDS_MAPPING = [
        'direccion' => 'direccion',
        'telefono' => 'celular',
        'tipoDocumento' => 'tipo_documento_id',
        'documento' => 'numero_documento',
    ];

    private const PERSONA_DATA_KEYS = [
        'nombre',
        'apellido',
        'tipoDocumento',
        'documento',
        'fechaNacimiento',
        'direccion',
        'telefono'
    ];

    public function getAll(int $perPage = 10, string $search = ''): LengthAwarePaginator
    {
        $search = (string) $search;

        return Usuario::withTrashed()->with([
            'persona:id_persona,tipo_documento_id,numero_documento,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,celular,direccion,fecha_nacimiento,id_usuario',
            'persona.tipoDocumento:id_tipo,nombre,codigo',
            'rol:id_rol,nombre',
        ])
            ->select(
                'usuarios.id_usuario',
                'usuarios.email',
                'usuarios.tipo_usuario',
                'usuarios.ldap_uid',
                'usuarios.activo',
                'usuarios.id_rol',
                'usuarios.creado_en',
                'usuarios.actualizado_en',
                'usuarios.eliminado_en'
            )
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(usuarios.email) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(usuarios.tipo_usuario::text) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereHas('persona', function ($q) use ($search) {
                            $q->whereRaw('LOWER(personas.primer_nombre) LIKE ?', ['%' . strtolower($search) . '%'])
                                ->orWhereRaw('LOWER(personas.segundo_nombre) LIKE ?', ['%' . strtolower($search) . '%'])
                                ->orWhereRaw('LOWER(personas.primer_apellido) LIKE ?', ['%' . strtolower($search) . '%'])
                                ->orWhereRaw('LOWER(personas.segundo_apellido) LIKE ?', ['%' . strtolower($search) . '%'])
                                ->orWhereRaw('LOWER(personas.numero_documento) LIKE ?', ['%' . $search . '%']);
                        })
                        ->orWhereHas('rol', function ($q) use ($search) {
                            $q->whereRaw('LOWER(roles.nombre) LIKE ?', ['%' . strtolower($search) . '%']);
                        });
                });
            })
            ->where('usuarios.id_usuario', '!=', Auth::id())
            ->orderBy('usuarios.id_usuario', 'asc')
            ->paginate($perPage);
    }

    public function getTrashed(int $perPage = 10): LengthAwarePaginator
    {
        return Usuario::onlyTrashed()
            ->with('persona')
            ->orderBy('id_usuario', 'desc')
            ->paginate($perPage);
    }

    public function getById(int|string $id)
    {
        if (!is_numeric($id) || intval($id) != $id) {
            throw new UsuarioException(
                'El ID del usuario debe ser un número entero',
                'invalid_id_format',
                400,
            );
        }

        try {
            return Usuario::with('persona')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new UsuarioException(
                "Usuario no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        }
    }

    public function getByEmail(string $email): ?Usuario
    {
        return Usuario::with('persona')->firstWhere('email', $email);
    }

    public function create(array $data, bool $desdeDashboard = false): Usuario
    {
        try {
            DB::beginTransaction();

            $persona = $this->getOrCreatePersona($data);
            $usuario = $this->createUsuarioRecord($data, $persona, $desdeDashboard);

            $persona->id_usuario = $usuario->id_usuario;
            $persona->save();
            DB::commit();
            return $usuario->load('persona');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al crear usuario', $e);

            throw new UsuarioException(
                'Error al crear el usuario: ' . $e->getMessage(),
                'creation_failed',
                500,
                $e,
            );
        }
    }

    public function update(int | string $param, array $data): Usuario
    {
        try {
            $usuario = null;

            if (is_numeric($param) && intval($param) == $param) {
                $usuario = $this->getById($param);
            } else {
                $usuario = $this->getByEmail($param);
            }

            if (!$usuario) {
                throw new UsuarioException(
                    "Usuario no encontrado con ID o email: {$param}",
                    'not_found',
                    404,
                );
            }

            DB::beginTransaction();

            $this->updateUsuarioFields($usuario, $data);
            $this->handlePersonaUpdate($usuario, $data);

            DB::commit();
            return $usuario->load('persona');
        } catch (UsuarioException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al actualizar usuario', $e, [
                'param' => $param,
                'data' => $data,
            ]);

            throw new UsuarioException(
                'Error al actualizar el usuario: ' . $e->getMessage(),
                'update_failed',
                500,
                $e,
            );
        }
    }

    private function updateUsuarioFields(Usuario $usuario, array $data): void
    {
        foreach (self::USUARIO_FIELDS_MAPPING as $dataKey => $modelField) {
            if (array_key_exists($dataKey, $data)) {
                $usuario->$modelField = $data[$dataKey];
            }
        }

        if (isset($data['password'])) {
            $usuario->password_hash = Hash::make($data['password']);
        }

        if (isset($data['rol']) || isset($data['id_rol'])) {
            $usuario->id_rol = $data['rol'] ?? $data['id_rol'] ?? null;
        }

        $usuario->save();
    }

    private function updatePersonaFields(?Persona $persona, array $data): void
    {
        if (!$persona) {
            return;
        }

        $this->setNombreApellido($persona, $data);
        $this->setFechaNacimiento($persona, $data);

        foreach (self::PERSONA_FIELDS_MAPPING as $dataKey => $modelField) {
            if (isset($data[$dataKey])) {
                $persona->$modelField = $data[$dataKey];
            }
        }

        $persona->save();
    }

    public function delete(int $id): ?bool
    {
        try {
            $usuario = $this->getById($id);

            $usuario->activo = false;
            $usuario->save();

            return $usuario->delete();
        } catch (UsuarioException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error al eliminar usuario', $e, ['id' => $id]);

            throw new UsuarioException(
                'Error al eliminar el usuario: ' . $e->getMessage(),
                'deletion_failed',
                500,
                $e,
            );
        }
    }

    public function restore(int $id): Usuario
    {
        try {
            $usuario = Usuario::withTrashed()->find($id);

            if (!$usuario) {
                throw new ModelNotFoundException(
                    "Usuario no encontrado con ID: {$id}",
                );
            }

            if (!$usuario->trashed()) {
                throw new UsuarioException(
                    "El usuario con ID: {$id} no está eliminado",
                    'not_deleted',
                    400,
                );
            }

            $usuario->restore();
            $usuario->activo = true;
            $usuario->save();
            $usuario->load('persona');
            return $usuario;
        } catch (ModelNotFoundException $e) {
            throw new UsuarioException(
                "Usuario no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        } catch (UsuarioException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error al restaurar usuario', $e, ['id' => $id]);

            throw new UsuarioException(
                'Error al restaurar el usuario: ' . $e->getMessage(),
                'restore_failed',
                500,
                $e,
            );
        }
    }

    private function generarPasswordGenerico(Persona $persona): string
    {
        $nombre = $persona->primer_nombre ?? '';
        $apellido = $persona->primer_apellido ?? '';
        $fechaNacimiento = $persona->fecha_nacimiento ? date('Y', strtotime($persona->fecha_nacimiento)) : '';

        Log::info('Generando contraseña genérica', [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'fechaNacimiento' => $fechaNacimiento,
        ]);

        return strtolower("{$nombre}.{$apellido}.{$fechaNacimiento}");
    }

    private function createPersonaFromData(array $data, int $idUsuario): Persona
    {
        $personaData = [];

        $this->setNombreApellidoData($personaData, $data);
        $this->setFechaNacimientoData($personaData, $data);

        foreach (self::PERSONA_FIELDS_MAPPING as $dataKey => $modelField) {
            if (isset($data[$dataKey])) {
                $personaData[$modelField] = $data[$dataKey];
            }
        }

        // Si no se proporciona tipo de documento, usar Cédula de Ciudadanía como predeterminado
        if (!isset($personaData['tipo_documento_id'])) {
            $personaData['tipo_documento_id'] = self::DEFAULT_TIPO_DOCUMENTO_ID;
        }

        // Asignar el ID del usuario a la persona
        $personaData['id_usuario'] = $idUsuario;

        return Persona::create($personaData);
    }

    private function getOrCreatePersona(array $data): Persona
    {
        if (!empty($data['id_persona'])) {
            $persona = Persona::find($data['id_persona']);
            if ($persona) {
                return $persona;
            }
        }

        return $this->createPersonaFromData($data, $data['id_usuario'] ?? 0);
    }

    private function createUsuarioRecord(array $data, Persona $persona, bool $desdeDashboard): Usuario
    {
        $password = $desdeDashboard
            ? $this->generarPasswordGenerico($persona)
            : $data['password'];

        if (empty($password)) {
            throw new UsuarioException(
                'La contraseña no puede estar vacía',
                'empty_password',
                400,
            );
        }

        $dataUsuario = [
            'email' => $data['email'],
            'password_hash' => Hash::make($password),
            'tipo_usuario' => $data['tipoUsuario'] ?? 'externo',
            'ldap_uid' => $data['ldap_uid'] ?? null,
            'activo' => $data['activo'] ?? true,
            'id_persona' => $persona->id_persona,
        ];

        if (isset($data['rol'])) {
            $dataUsuario['rol'] = $data['rol'];
        }

        $usuario = Usuario::create($dataUsuario);

        // Asignar el permiso de reservar a todos los usuarios nuevos
        $usuario->asignarPermisoReservar();

        return $usuario;
    }

    private function handlePersonaUpdate(Usuario $usuario, array $data): void
    {
        if (!$usuario->persona && $this->hasPersonaData($data)) {
            $persona = $this->createPersonaFromData($data, $usuario->id_usuario);
            // No necesitamos hacer nada más porque la persona ya está vinculada al usuario
        } elseif ($usuario->persona) {
            $this->updatePersonaFields($usuario->persona, $data);
        }
    }

    private function hasPersonaData(array $data): bool
    {
        foreach (self::PERSONA_DATA_KEYS as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                return true;
            }
        }

        return false;
    }

    private function setNombreApellido(Persona $persona, array $data): void
    {
        if (isset($data['nombre'])) {
            $nombres = explode(' ', $data['nombre']);
            $persona->primer_nombre = $nombres[0];
            $persona->segundo_nombre = $nombres[1] ?? null;
        }

        if (isset($data['apellido'])) {
            $apellidos = explode(' ', $data['apellido']);
            $persona->primer_apellido = $apellidos[0];
            $persona->segundo_apellido = $apellidos[1] ?? null;
        }
    }

    private function setFechaNacimiento(Persona $persona, array $data): void
    {
        if (isset($data['fechaNacimiento'])) {
            $persona->fecha_nacimiento = Carbon::createFromFormat('Y-m-d', $data['fechaNacimiento'])->format('Y-m-d');
        }
    }

    private function setNombreApellidoData(array &$personaData, array $data): void
    {
        if (isset($data['nombre'])) {
            $nombres = explode(' ', $data['nombre'], 2);
            $personaData['primer_nombre'] = $nombres[0];
            $personaData['segundo_nombre'] = $nombres[1] ?? null;
        }

        if (isset($data['apellido'])) {
            $apellidos = explode(' ', $data['apellido'], 2);
            $personaData['primer_apellido'] = $apellidos[0];
            $personaData['segundo_apellido'] = $apellidos[1] ?? null;
        }
    }

    private function setFechaNacimientoData(array &$personaData, array $data): void
    {
        if (isset($data['fechaNacimiento'])) {
            $personaData['fecha_nacimiento'] = Carbon::createFromFormat('Y-m-d', $data['fechaNacimiento'])->format('Y-m-d');
        }
    }

    private function logError(string $message, \Exception $exception, array $context = []): void
    {
        Log::error($message, array_merge($context, [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]));
    }

    public function actualizarPermisos(int | string $param, array $permisos): Usuario
    {
        try {
            $usuario = null;

            if (is_numeric($param) && intval($param) == $param) {
                $usuario = $this->getById($param);
            } else {
                $usuario = $this->getByEmail($param);
            }

            if (!$usuario) {
                throw new UsuarioException(
                    "Usuario no encontrado con ID o email: {$param}",
                    'not_found',
                    404,
                );
            }

            DB::beginTransaction();

            // Si los permisos vienen como objetos con propiedad concedido, filtrarlos
            if (isset($permisos[0]) && is_array($permisos[0]) && isset($permisos[0]['concedido'])) {
                $permisosIds = $this->filtrarPermisosConcedidos($permisos);
            } else {
                // Si vienen como array simple de IDs, usarlos directamente
                $permisosIds = $permisos;
            }

            $usuario->permisosDirectos()->sync($permisosIds);

            DB::commit();

            return $usuario->load(['permisosDirectos', 'rol.permisos', 'persona']);
        } catch (UsuarioException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al actualizar permisos del usuario', $e, [
                'param' => $param,
                'permisos_count' => count($permisos),
            ]);

            throw new UsuarioException(
                'Error al actualizar los permisos del usuario: ' . $e->getMessage(),
                'update_permissions_failed',
                500,
                $e,
            );
        }
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

    public function buscarJugadores(string $termino)
    {
        $termino = trim(strtolower($termino));

        return Usuario::with('persona')
            ->select([
                'usuarios.id_usuario as id',
                'usuarios.email',
                'usuarios.tipo_usuario',
                'usuarios.ldap_uid',
                'usuarios.activo',
                'usuarios.id_rol',
                'usuarios.creado_en',
                'usuarios.actualizado_en',
                'usuarios.eliminado_en'
            ])
            ->where(function ($query) use ($termino) {
                $query->whereRaw('LOWER(email) LIKE ?', ["%{$termino}%"])
                    // ->orWhereRaw('ldap_uid LIKE ?', ["%{$termino}%"])
                    ->orWhereRaw('LOWER(tipo_usuario::text) LIKE ?', ["%{$termino}%"])
                    ->orWhereHas('persona', function ($q) use ($termino) {
                        $q->whereRaw('LOWER(primer_nombre) LIKE ?', ["%{$termino}%"])
                            ->orWhereRaw('LOWER(segundo_nombre) LIKE ?', ["%{$termino}%"])
                            ->orWhereRaw('LOWER(primer_apellido) LIKE ?', ["%{$termino}%"])
                            ->orWhereRaw('LOWER(segundo_apellido) LIKE ?', ["%{$termino}%"])
                            ->orWhereRaw('LOWER(numero_documento) LIKE ?', ["%{$termino}%"]);
                    });
            })
            ->where('id_usuario', '!=', Auth::id())
            ->where('eliminado_en', null)
            ->get();
    }
}
