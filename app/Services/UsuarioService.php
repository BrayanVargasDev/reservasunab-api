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
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordGenericoEmail;

class UsuarioService
{
    // Constante para tipo de documento por defecto
    private const DEFAULT_TIPO_DOCUMENTO_ID = 1; // CC - Cédula de Ciudadanía

    // Constantes para campos de mapeo
    private const USUARIO_FIELDS_MAPPING = [
        'email' => 'email',
        'tipos_usuario' => 'tipos_usuario',
        'id_persona' => 'id_persona',
        'activo' => 'activo',
        'perfil_completado' => 'perfil_completado',
        'terminos_condiciones' => 'terminos_condiciones',
    ];

    private const PERSONA_FIELDS_MAPPING = [
        'direccion' => 'direccion',
        'telefono' => 'celular',
        'tipoDocumento' => 'tipo_documento_id',
        'documento' => 'numero_documento',
        'tipoPersona' => 'tipo_persona',
        'regimenTributario' => 'regimen_tributario_id',
        'ciudadExpedicion' => 'ciudad_expedicion_id',
        'ciudadResidencia' => 'ciudad_residencia_id',
        'digitoVerificacion' => 'digito_verificacion',
    ];

    private const PERSONA_DATA_KEYS = [
        'nombre',
        'apellido',
        'tipoDocumento',
        'documento',
        'fechaNacimiento',
        'direccion',
        'telefono',
        'tipoPersona',
        'digitoVerificacion',
        'regimenTributarioId',
        'ciudadExpedicionId',
        'ciudadResidenciaId'
    ];

    public function getAll(int $perPage = 10, string $search = ''): LengthAwarePaginator
    {
        $search = (string) $search;

        $query = Usuario::withTrashed()->with([
            'persona:id_persona,tipo_documento_id,numero_documento,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,celular,direccion,fecha_nacimiento,tipo_persona,regimen_tributario_id,ciudad_expedicion_id,ciudad_residencia_id,id_usuario',
            'persona.tipoDocumento:id_tipo,nombre,codigo',
            'persona.regimenTributario:codigo,nombre',
            'persona.ciudadExpedicion:id,nombre',
            'persona.ciudadResidencia:id,nombre',
            'rol:id_rol,nombre',
        ])
            ->select(
                'usuarios.id_usuario',
                'usuarios.email',
                'usuarios.tipos_usuario',
                'usuarios.ldap_uid',
                'usuarios.activo',
                'usuarios.id_rol',
                'usuarios.perfil_completado',
                'usuarios.terminos_condiciones',
                'usuarios.creado_en',
                'usuarios.actualizado_en',
                'usuarios.eliminado_en'
            )
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $searchLower = strtolower($search);

                    $q->where('usuarios.email', 'ILIKE', "%{$searchLower}%")
                        ->orWhereHas('persona', function ($personaQuery) use ($searchLower, $search) {
                            $personaQuery->where('personas.primer_nombre', 'ILIKE', "%{$searchLower}%")
                                ->orWhere('personas.segundo_nombre', 'ILIKE', "%{$searchLower}%")
                                ->orWhere('personas.primer_apellido', 'ILIKE', "%{$searchLower}%")
                                ->orWhere('personas.segundo_apellido', 'ILIKE', "%{$searchLower}%")
                                ->orWhere('personas.numero_documento', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('rol', function ($rolQuery) use ($searchLower) {
                            $rolQuery->where('roles.nombre', 'ILIKE', "%{$searchLower}%");
                        })
                        ->orWhere(DB::raw("array_to_string(usuarios.tipos_usuario, ',')"), 'ILIKE', "%{$searchLower}%");
                });
            })
            ->where('usuarios.id_usuario', '!=', Auth::id())
            ->orderBy('usuarios.id_usuario', 'asc');

        return $query->paginate($perPage);
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
            return Usuario::with([
                'persona',
                'persona.personaFacturacion',
            ])->findOrFail($id);
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

    public function create(array $data, bool $desdeDashboard = false, $esSSO = false): Usuario
    {
        try {
            DB::beginTransaction();

            $persona = $this->getOrCreatePersona($data);
            if (!empty($persona->id_usuario)) {
                throw new UsuarioException(
                    'La persona seleccionada ya está asociada a otro usuario',
                    'persona_asociada_otro_usuario',
                    409,
                );
            }

            $usuario = $this->createUsuarioRecord($data, $persona, $desdeDashboard, $esSSO);

            $persona->id_usuario = $usuario->id_usuario;
            $persona->save();
            DB::commit();

            if ($desdeDashboard && !empty($usuario->password_hash) && isset($usuario->_password_plano_generado)) {
                try {
                    Mail::to($usuario->email)->send(new PasswordGenericoEmail($usuario, $usuario->_password_plano_generado, true));
                } catch (\Throwable $mailEx) {
                    Log::error('No se pudo enviar correo de password genérico', [
                        'error' => $mailEx->getMessage(),
                        'user_id' => $usuario->id_usuario,
                    ]);
                }
            }
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
            // Manejar datos de facturación si vienen en la actualización de perfil
            $this->handlePersonaFacturacion($usuario, $data);

            $desdeDashboard = $data['__desde_dashboard'] ?? false; // bandera opcional en payload
            $forzarGeneracion = $data['generar_password'] ?? false;
            if (($desdeDashboard || $forzarGeneracion) && empty($usuario->password_hash)) {
                $passwordPlano = $this->generarPasswordGenerico($usuario->persona ?? new Persona());
                $usuario->password_hash = Hash::make($passwordPlano);
                $usuario->save();
                try {
                    Mail::to($usuario->email)->send(new PasswordGenericoEmail($usuario, $passwordPlano, false));
                } catch (\Throwable $mailEx) {
                    Log::error('No se pudo enviar correo de generación de password en update', [
                        'error' => $mailEx->getMessage(),
                        'user_id' => $usuario->id_usuario,
                    ]);
                }
            }

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

    private function handlePersonaFacturacion(Usuario $usuario, array $data): void
    {
        if (!isset($data['facturacion']) || !is_array($data['facturacion'])) {
            return;
        }

        $payload = $data['facturacion'];
        $this->mapEmailFacturacionToDireccion($payload);
        $personaTitular = $usuario->persona;
        if (!$personaTitular) {
            if ($this->hasPersonaData($data)) {
                $personaTitular = $this->createPersonaFromData($data, $usuario->id_usuario);
            } else {
                return;
            }
        }

        $personaFact = null;
        $idFact = $payload['id'] ?? $payload['id_persona'] ?? null;
        if ($idFact) {
            $personaFact = Persona::find($idFact);
        }

        if (!$personaFact) {
            $personaFact = Persona::where('persona_facturacion_id', $personaTitular->id_persona)
                ->where('es_persona_facturacion', true)
                ->orderByDesc('id_persona')
                ->first();
        }

        if ($personaFact) {
            $this->mapEmailFacturacionToDireccion($payload);
            $this->updatePersonaFields($personaFact, $payload);
            $personaFact->es_persona_facturacion = true;
            $personaFact->persona_facturacion_id = $personaTitular->id_persona;
            $personaFact->save();
        } else {
            $personaFact = $this->createPersonaFacturacionFromData($payload, $personaTitular->id_persona);
        }
    }

    private function createPersonaFacturacionFromData(array $data, int $personaPadreId): Persona
    {
        $personaData = [];

        $this->setNombreApellidoData($personaData, $data);
        $this->setFechaNacimientoData($personaData, $data);

        $this->mapEmailFacturacionToDireccion($data);

        foreach (self::PERSONA_FIELDS_MAPPING as $dataKey => $modelField) {
            if (isset($data[$dataKey])) {
                $personaData[$modelField] = $data[$dataKey];
            }
        }

        if (!isset($personaData['tipo_documento_id'])) {
            $personaData['tipo_documento_id'] = self::DEFAULT_TIPO_DOCUMENTO_ID;
        }
        if (!isset($personaData['tipo_persona'])) {
            $personaData['tipo_persona'] = 'natural';
        }

        $personaData['es_persona_facturacion'] = true;
        $personaData['persona_facturacion_id'] = $personaPadreId;

        return Persona::create($personaData);
    }

    private function mapEmailFacturacionToDireccion(array &$payload): void
    {

        $ya_esta_mapeado = count(explode(';', $payload['direccion'])) > 1;

        if (!$ya_esta_mapeado) {
            $payload['direccion'] = $payload['email'] . ';' . $payload['direccion'];
        }
    }

    private function updateUsuarioFields(Usuario $usuario, array $data): void
    {
        foreach (self::USUARIO_FIELDS_MAPPING as $dataKey => $modelField) {
            if (array_key_exists($dataKey, $data) && $dataKey !== 'tipos_usuario' && $dataKey !== 'perfil_completado') {
                $usuario->$modelField = $data[$dataKey];
            }
        }

        if (isset($data['tipos_usuario'])) {
            $usuario->tipos_usuario = is_array($data['tipos_usuario'])
                ? $data['tipos_usuario']
                : [$data['tipos_usuario']];
        } elseif (isset($data['tiposUsuario'])) {
            $usuario->tipos_usuario = is_array($data['tiposUsuario'])
                ? $data['tiposUsuario']
                : [$data['tiposUsuario']];
        }

        if (isset($data['password'])) {
            $usuario->password_hash = Hash::make($data['password']);
        }

        if (isset($data['rol']) || isset($data['id_rol'])) {
            $usuario->id_rol = $data['rol'] ?? $data['id_rol'] ?? null;
        }

        // Manejar terminos_condiciones
        if (isset($data['terminos_condiciones'])) {
            $usuario->terminos_condiciones = (bool) $data['terminos_condiciones'];
        }

        $usuario->save();

        $usuario->perfil_completado = $this->esPerfilCompleto($usuario);
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

        if ($persona->id_usuario) {
            $usuario = Usuario::find($persona->id_usuario);
            if ($usuario) {
                $usuario->perfil_completado = $this->esPerfilCompleto($usuario);
                $usuario->save();
            }
        }
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
        // Nueva lógica: palabra base + año nacimiento (si existe) + 4 caracteres aleatorios
        $nombre = preg_replace('/[^a-zA-Z]/', '', strtolower($persona->primer_nombre ?? 'user'));
        $apellido = preg_replace('/[^a-zA-Z]/', '', strtolower($persona->primer_apellido ?? ''));
        $base = substr($nombre, 0, 4) . substr($apellido, 0, 4);
        if (empty(trim($base))) {
            $base = 'usr';
        }
        $year = '';
        if (!empty($persona->fecha_nacimiento)) {
            try {
                $year = Carbon::parse($persona->fecha_nacimiento)->format('Y');
            } catch (\Throwable $t) {
                $year = '';
            }
        }
        $rand = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4);
        $password = strtolower($base) . ($year ? '.' . $year : '') . '@' . $rand;
        // Garantizar longitud mínima 10
        if (strlen($password) < 10) {
            $password .= substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 10 - strlen($password));
        }
        return $password;
    }

    private function createPersonaFromData(array $data, ?int $idUsuario): Persona
    {
        // Si el usuario ya tiene una persona asociada, actualizarla en lugar de crear una nueva
        if ($idUsuario !== null) {
            $existente = Persona::where('id_usuario', $idUsuario)->first();
            if ($existente) {
                $this->updatePersonaFields($existente, $data);
                return $existente;
            }
        }

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

        // Si no se proporciona tipo de persona, usar 'natural' como predeterminado
        if (!isset($personaData['tipo_persona'])) {
            $personaData['tipo_persona'] = 'natural';
        }

        if ($idUsuario !== null) {
            $personaData['id_usuario'] = $idUsuario;
        }

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

        // Crear persona sin id_usuario inicialmente
        return $this->createPersonaFromData($data, null);
    }

    private function createUsuarioRecord(array $data, Persona $persona, bool $desdeDashboard): Usuario
    {
        // Si viene desde dashboard generamos password genérico y lo marcamos para correo.
        // Si viene de SSO (no desde dashboard) permitimos password null y se enviará luego cuando complete perfil desde dashboard.
        $password = null;
        if ($desdeDashboard) {
            $password = $this->generarPasswordGenerico($persona);
        } elseif (!empty($data['password'])) {
            $password = $data['password'];
        }

        $tiposUsuario = $data['tiposUsuario'] ?? $data['tipos_usuario'] ?? ['egresado'];

        if (!is_array($tiposUsuario)) {
            $tiposUsuario = [$tiposUsuario];
        }

        $dataUsuario = [
            'email' => $data['email'],
            'password_hash' => $password ? Hash::make($password) : null,
            'tipos_usuario' => $tiposUsuario,
            'ldap_uid' => $data['ldap_uid'] ?? null,
            'activo' => $data['activo'] ?? true,
            'id_persona' => $persona->id_persona,
            'perfil_completado' => false,
            'terminos_condiciones' => $data['terminos_condiciones'] ?? false,
        ];
        if (isset($data['rol']) || isset($data['id_rol'])) {
            $dataUsuario['id_rol'] = $data['rol'] ?? $data['id_rol'];
        }

        $usuario = Usuario::create($dataUsuario);

        $usuario->perfil_completado = $this->esPerfilCompleto($usuario);
        $usuario->save();
        $usuario->asignarPermisoReservar();

        if ($password) {
            $usuario->_password_plano_generado = $password;
        }
        return $usuario;
    }

    private function handlePersonaUpdate(Usuario $usuario, array $data): void
    {
        if (!$usuario->persona && $this->hasPersonaData($data)) {
            $persona = $this->createPersonaFromData($data, $usuario->id_usuario);

            if (empty($usuario->id_persona)) {
                $usuario->id_persona = $persona->id_persona;
                $usuario->save();
            }
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

    public function verificarPerfilCompleto(int $usuarioId): bool
    {
        $usuario = $this->getById($usuarioId);
        return $this->esPerfilCompleto($usuario);
    }

    public function actualizarEstadoPerfil(int $usuarioId): Usuario
    {
        try {
            DB::beginTransaction();

            $usuario = $this->getById($usuarioId);
            $usuario->perfil_completado = $this->esPerfilCompleto($usuario);
            $usuario->save();

            DB::commit();

            return $usuario->load('persona');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al actualizar estado del perfil', $e, [
                'usuario_id' => $usuarioId,
            ]);

            throw new UsuarioException(
                'Error al actualizar el estado del perfil: ' . $e->getMessage(),
                'profile_update_failed',
                500,
                $e,
            );
        }
    }

    private function esPerfilCompleto(Usuario $usuario): bool
    {
        if (!$usuario->persona) {
            return false;
        }

        $persona = $usuario->persona;

        $esta_completo = !empty($persona->ciudad_residencia_id) &&
            !empty($persona->direccion) &&
            !empty($persona->ciudad_expedicion_id) &&
            !empty($persona->numero_documento) &&
            !empty($persona->tipo_documento_id) &&
            !empty($persona->tipo_persona) &&
            !empty($persona->regimen_tributario_id);

        try {
            DB::beginTransaction();

            $usuario->perfil_completado = $esta_completo;
            $usuario->save();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            $this->logError('Error al actualizar estado de perfil', $th, [
                'usuario_id' => $usuario->id_usuario,
                'perfil_completado' => $esta_completo,
            ]);
            return false;
        }

        return $esta_completo;
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

    public function buscarJugadores(string $termino, bool $incluirBeneficiarios = false)
    {
        $termino = trim(strtolower($termino));

        $usuarios = Usuario::with(['persona', 'persona.tipoDocumento'])
            ->leftJoin('personas', 'usuarios.id_usuario', '=', 'personas.id_usuario')
            ->select(
                'usuarios.id_usuario',
                'usuarios.id_usuario as id',
                'usuarios.email',
                'usuarios.tipos_usuario',
                'usuarios.ldap_uid',
                'usuarios.activo',
                'usuarios.id_rol',
                'usuarios.perfil_completado',
                'usuarios.terminos_condiciones',
                'usuarios.creado_en',
                'personas.primer_nombre',
                'personas.segundo_nombre',
                'personas.primer_apellido',
                'personas.segundo_apellido',
                'personas.numero_documento',
            )
            ->where(function ($query) use ($termino) {
                $query->where('usuarios.email', 'ILIKE', "%{$termino}%")
                    ->orWhere('usuarios.ldap_uid', 'ILIKE', "%{$termino}%")
                    ->orWhere(DB::raw("array_to_string(usuarios.tipos_usuario, ',')"), 'ILIKE', "%{$termino}%")
                    ->orWhere(function ($q) use ($termino) {
                        $q->where('personas.primer_nombre', 'ILIKE', "%{$termino}%")
                            ->orWhere('personas.segundo_nombre', 'ILIKE', "%{$termino}%")
                            ->orWhere('personas.primer_apellido', 'ILIKE', "%{$termino}%")
                            ->orWhere('personas.segundo_apellido', 'ILIKE', "%{$termino}%")
                            ->orWhere('personas.numero_documento', 'LIKE', "%{$termino}%");
                    });
            })
            ->where('usuarios.id_usuario', '!=', Auth::id())
            ->whereNull('usuarios.eliminado_en')
            ->get();

        if (!$incluirBeneficiarios) {
            return $usuarios;
        }

        try {
            $beneficiarios = \App\Models\Beneficiario::with('tipoDocumento')
                ->where('id_usuario', Auth::id())
                ->where(function ($q) use ($termino) {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ["%{$termino}%"])
                        ->orWhereRaw('LOWER(apellido) LIKE ?', ["%{$termino}%"])
                        ->orWhereRaw('LOWER(parentesco) LIKE ?', ["%{$termino}%"])
                        ->orWhere('documento', 'LIKE', "%{$termino}%");
                })
                ->get();

            $beneficiariosComoUsuarios = $beneficiarios->map(function ($b) {
                $fake = new \stdClass();
                $fake->id_usuario = -$b->id;
                $fake->avatar = null;
                $fake->email = null;
                $fake->tipos_usuario = ['externo'];
                $fake->ldap_uid = null;
                $fake->activo = true;
                $fake->id_rol = null;
                $fake->perfil_completado = true;
                $fake->terminos_condiciones = true;
                $fake->creado_en = $b->creado_en;
                // Alinear con UsuariosResource que espera 'ultimo_acceso'
                $fake->ultimo_acceso = null;

                $persona = new \stdClass();
                $persona->celular = null;
                $persona->direccion = null;
                $persona->fecha_nacimiento = null;
                $persona->regimen_tributario_id = null;
                $persona->ciudad_expedicion_id = null;
                $persona->ciudad_residencia_id = null;
                $persona->tipo_persona = null;
                $persona->primer_nombre = $b->nombre;
                $persona->segundo_nombre = '';
                $persona->primer_apellido = $b->apellido;
                $persona->segundo_apellido = '';
                $persona->numero_documento = $b->documento;
                $persona->tipo_documento_id = $b->tipo_documento_id;
                $persona->tipoDocumento = (object) [
                    'codigo' => optional($b->tipoDocumento)->codigo,
                ];
                $fake->persona = $persona;

                $fake->es_beneficiario = true;
                $fake->id_beneficiario = $b->id;
                return $fake;
            });

            return $usuarios->concat($beneficiariosComoUsuarios)->values();
        } catch (\Throwable $e) {
            // En caso de error, retornar solo usuarios
            return $usuarios;
        }
    }

    /**
     * Cambiar la contraseña de un usuario
     *
     * @param int $usuarioId ID del usuario
     * @param string $nuevaPassword Nueva contraseña
     * @return Usuario
     * @throws UsuarioException
     */
    public function cambiarPassword(int $usuarioId, string $nuevaPassword): Usuario
    {
        try {
            DB::beginTransaction();

            $usuario = $this->getById($usuarioId);

            $usuario->password_hash = Hash::make($nuevaPassword);
            $usuario->save();

            DB::commit();

            Log::info('Contraseña cambiada exitosamente', [
                'usuario_id' => $usuarioId,
                'email' => $usuario->email,
            ]);

            return $usuario->load('persona');
        } catch (UsuarioException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al cambiar contraseña', $e, [
                'usuario_id' => $usuarioId,
            ]);

            throw new UsuarioException(
                'Error al cambiar la contraseña: ' . $e->getMessage(),
                'password_change_failed',
                500,
                $e,
            );
        }
    }
}
