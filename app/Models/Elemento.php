<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Elemento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'elementos';
    protected $primaryKey = 'id';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'nombre',
        'valor_administrativo',
        'valor_egresado',
        'valor_estudiante',
        'valor_externo',
    ];

    protected $casts = [
        'id' => 'integer',
        'valor_administrativo' => 'decimal:2',
        'valor_egresado' => 'decimal:2',
        'valor_estudiante' => 'decimal:2',
        'valor_externo' => 'decimal:2',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];
}
