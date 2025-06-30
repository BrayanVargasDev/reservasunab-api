<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EspacioNovedad extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'espacios_novedades';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_espacio',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'descripcion',
        'tipo',
        'creado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio' => 'time',
        'hora_fin' => 'time',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'id_espacio', 'id');
    }
}
