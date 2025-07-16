<?php

namespace App\Models;

use App\Traits\ManageTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    use HasFactory, SoftDeletes, ManageTimezone;
    protected $table = 'categorias';
    protected $primaryKey = 'id';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'nombre',
        'id_grupo',
        'creado_por',
        'reservas_estudiante',
        'reservas_administrativo',
        'reservas_egresado',
        'reservas_externo',
    ];

    protected $casts = [
        'reservas_estudiante' => 'integer',
        'reservas_administrativo' => 'integer',
        'reservas_egresado' => 'integer',
        'reservas_externo' => 'integer',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo', 'id');
    }

    public function espacios()
    {
        return $this->hasMany(Espacio::class, 'id_categoria', 'id');
    }
}
