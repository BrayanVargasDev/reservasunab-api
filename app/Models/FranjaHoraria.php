<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FranjaHoraria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'franjas_horarias';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_config',
        'hora_inicio',
        'hora_fin',
        'valor',
        'activa',
    ];

    protected $casts = [
        'id_config' => 'integer',
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'valor' => 'float',
        'activa' => 'boolean',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function config()
    {
        return $this->belongsTo(EspacioConfiguracion::class, 'id_config', 'id');
    }
}
