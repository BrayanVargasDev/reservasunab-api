<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'email',
        'password_hash',
        'tipo_usuario',
        'rol',
        'ldap_uid',
        'activo',
        'id_rol',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'activo' => 'boolean',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
        'tipo_usuario' => 'string',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_usuario', 'id_usuario');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function permisosDirectos()
    {
        return $this->belongsToMany(Permiso::class, 'usuarios_permisos', 'id_usuario', 'id_permiso');
    }

    public function esAdministrador(): bool
    {
        return $this->rol && strtolower($this->rol->nombre) === 'administrador';
    }


    public function obtenerTodosLosPermisos()
    {
        if ($this->esAdministrador()) {
            return collect();
        }

        if ($this->relationLoaded('permisosDirectos') && $this->relationLoaded('rol.permisos')) {
            $permisosDirectos = $this->permisosDirectos;
            $permisosDelRol = $this->rol ? $this->rol->permisos : collect();
        } else {
            $this->load(['permisosDirectos', 'rol.permisos']);
            $permisosDirectos = $this->permisosDirectos;
            $permisosDelRol = $this->rol ? $this->rol->permisos : collect();
        }

        // Combinar permisos del rol y permisos directos, eliminando duplicados
        return $permisosDelRol->concat($permisosDirectos)
            ->unique('id_permiso')
            ->values();
    }

    public function tienePermiso(string $codigoPermiso): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        return $this->obtenerTodosLosPermisos()
            ->contains('codigo', $codigoPermiso);
    }

    public function tieneAlgunPermiso(array $codigosPermisos): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        $permisosUsuario = $this->obtenerTodosLosPermisos()
            ->pluck('codigo')
            ->toArray();

        return !empty(array_intersect($codigosPermisos, $permisosUsuario));
    }
}
