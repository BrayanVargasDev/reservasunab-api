<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'creado_por',
        'actualizado_por',
        'eliminado_por'
    ];

    protected $casts = [
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function espacios()
    {
        return $this->hasMany(Espacio::class, 'id_sede', 'id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
    }

    public function actualizadoPor()
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por', 'id_usuario');
    }

    public function eliminadoPor()
    {
        return $this->belongsTo(Usuario::class, 'eliminado_por', 'id_usuario');
    }
}
