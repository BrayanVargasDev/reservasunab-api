<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grupo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'grupos';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'nombre',
        'creado_por',
        'actualizado_por',
        'eliminado_por',
    ];

    protected $casts = [
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

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

    public function categorias()
    {
        return $this->hasMany(Categoria::class, 'id_grupo', 'id');
    }
}
