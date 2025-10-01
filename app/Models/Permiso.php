<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permiso extends Model
{
    use SoftDeletes;

    protected $table = 'permisos';
    protected $keyType = 'integer';

    public $timestamps = true;
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';
    public const DELETED_AT = 'eliminado_en';
    protected $primaryKey = 'id_permiso';

    protected $fillable = [
        'nombre',
        'descripcion',
        'codigo',
        'icono',
        'id_pantalla',
    ];

    public function pantalla(): BelongsTo
    {
        return $this->belongsTo(Pantalla::class, 'id_pantalla', 'id_pantalla');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'roles_permisos', 'id_permiso', 'id_rol');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'usuarios_permisos', 'id_permiso', 'id_usuario');
    }
}
