<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pantalla extends Model
{
    use HasFactory;

    protected $table = 'pantallas';
    protected $keyType = 'integer';

    protected $casts = [
        'visible' => 'boolean',
        'orden' => 'integer',
        'tipo' => 'string',
        'padre_id' => 'integer',
    ];
    public $timestamps = true;
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';
    public const DELETED_AT = 'eliminado_en';
    protected $primaryKey = 'id_pantalla';

    protected $fillable = [
        'nombre',
        'descripcion',
        'codigo',
        'icono',
        'ruta',
        'tipo',
        'orden',
        'visible',
    ];

    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class, 'id_pantalla', $this->primaryKey);
    }
}
