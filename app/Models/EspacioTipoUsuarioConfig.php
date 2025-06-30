<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EspacioTipoUsuarioConfig extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'espacio_tipo_usuario_configs';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_espacio',
        'tipo_usuario',
        'porcentaje_descuento',
        'retraso_reserva',
        'creado_por',
        'actualizado_por',
        'eliminado_por',
    ];

    protected $casts = [
        'id_espacio' => 'integer',
        'tipo_usuario' => 'string',
        'porcentaje_descuento' => 'float',
        'retraso_reserva' => 'integer',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'id_espacio', 'id');
    }
}
