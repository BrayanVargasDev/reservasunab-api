<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Reservas;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimientos::class, 'id_usuario', 'id_usuario');
    }
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    // Tipos de usuario válidos
    const TIPO_ESTUDIANTE = 'estudiante';
    const TIPO_ADMINISTRATIVO = 'administrativo';
    const TIPO_EGRESADO = 'egresado';
    const TIPO_EXTERNO = 'externo';

    const TIPOS_USUARIO_VALIDOS = [
        self::TIPO_ESTUDIANTE,
        self::TIPO_ADMINISTRATIVO,
        self::TIPO_EGRESADO,
        self::TIPO_EXTERNO,
    ];

    protected $fillable = [
        'email',
        'password_hash',
        'tipos_usuario',
        'rol',
        'ldap_uid',
        'activo',
        'id_rol',
        'perfil_completado',
        'terminos_condiciones',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected $casts = [
        'activo' => 'boolean',
        'perfil_completado' => 'boolean',
        'terminos_condiciones' => 'boolean',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
        // 'tipos_usuario' => 'array',
        'email_verified_at' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function getAuthIdentifierName()
    {
        return 'id_usuario';
    }

    public function getAuthIdentifier()
    {
        return $this->getAttribute($this->getAuthIdentifierName());
    }

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

    public function jugadoresReserva()
    {
        return $this->hasMany(JugadorReserva::class, 'id_usuario', 'id_usuario');
    }

    public function reservasComoJugador()
    {
        return $this->hasManyThrough(
            Reservas::class,
            JugadorReserva::class,
            'id_usuario',
            'id',
            'id_usuario',
            'id_reserva'
        );
    }

    public function tieneTipo(string $tipo)
    {
        return in_array($tipo, $this->tipos_usuario ?? []);
    }

    public function tieneAlgunTipo(array $tipos): bool
    {
        return !empty(array_intersect($tipos, $this->tipos_usuario ?? []));
    }

    public function agregarTipo(string $tipo): bool
    {
        if (!in_array($tipo, self::TIPOS_USUARIO_VALIDOS)) {
            throw new InvalidArgumentException("Tipo de usuario inválido: {$tipo}");
        }

        $tiposActuales = $this->tipos_usuario ?? [];

        if (!in_array($tipo, $tiposActuales)) {
            $tiposActuales[] = $tipo;
            $this->tipos_usuario = $tiposActuales;
            return true;
        }

        return false;
    }

    public function removerTipo(string $tipo): bool
    {
        $tiposActuales = $this->tipos_usuario ?? [];
        $key = array_search($tipo, $tiposActuales);

        if ($key !== false) {
            unset($tiposActuales[$key]);
            $this->tipos_usuario = array_values($tiposActuales);
            return true;
        }

        return false;
    }

    public function setTiposUsuario(array $tipos): void
    {
        $tiposInvalidos = array_diff($tipos, self::TIPOS_USUARIO_VALIDOS);

        if (!empty($tiposInvalidos)) {
            throw new InvalidArgumentException("Tipos de usuario inválidos: " . implode(', ', $tiposInvalidos));
        }

        $this->tipos_usuario = array_unique($tipos);
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

    public function asignarPermisoReservar(): bool
    {
        $permisoReservar = Permiso::where('codigo', 'reservar')->first();

        if ($permisoReservar) {
            // Verificar si ya tiene el permiso para evitar duplicados
            if (!$this->permisosDirectos()->where('id_permiso', $permisoReservar->id_permiso)->exists()) {
                $this->permisosDirectos()->attach($permisoReservar->id_permiso);
            }
            return true;
        }

        \Illuminate\Support\Facades\Log::warning('No se encontró el permiso con código "reservar"', [
            'usuario_id' => $this->id_usuario,
            'email' => $this->email
        ]);

        return false;
    }

    protected function tiposUsuario(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $this->fromPostgresArray($value),
            set: fn($value) => $this->toPostgresArray($value),
        );
    }

    private function fromPostgresArray($value): array
    {
        // Quita las llaves { } y explota por coma
        return str_getcsv(trim($value, '{}'));
    }

    private function toPostgresArray(array $array): string
    {
        return '{' . implode(',', $array) . '}';
    }

    public function authCodes(): HasMany
    {
        return $this->hasMany(AuthCode::class, 'id_usuario', 'id_usuario');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'id_usuario', 'id_usuario');
    }

    public function tieneMensualidadActiva(): bool
    {
        $hoy = Carbon::now()->startOfDay();

        return Mensualidades::where('id_usuario', $this->id_usuario)
            ->where('estado', 'activa')
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_fin', '>=', $hoy)
            ->exists();
    }
}
