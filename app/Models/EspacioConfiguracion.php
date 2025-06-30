<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EspacioConfiguracion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'espacios_configuracion';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_espacio',
        'fecha',
        'dia_semana',
        'minutos_uso',
        'dias_previos_apertura',
        'hora_apertura',
        'tiempo_cancelacion',
        'creado_por',
        'eliminado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'dia_semana' => 'integer',
        'minutos_uso' => 'integer',
        'dias_previos_apertura' => 'integer',
        'hora_apertura' => 'string',
        'tiempo_cancelacion' => 'integer',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'id_espacio', 'id');
    }

    public function franjas_horarias()
    {
        return $this->hasMany(FranjaHoraria::class, 'id_config', 'id');
    }

    public function getHoraAperturaAttribute($value)
    {
        if ($value && !empty($value)) {
            if (strpos($value, ':') !== false) {
                return substr($value, 0, 5); // HH:mm
            }
            return $value;
        }
        return $value;
    }

    public function setHoraAperturaAttribute($value)
    {
        if ($value && !empty($value)) {
            if (substr_count($value, ':') === 1) {
                $value .= ':00';
            }
            if (strlen($value) > 8) {
                $value = date('H:i:s', strtotime($value));
            }
        }
        $this->attributes['hora_apertura'] = $value;
    }
}
