<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reservas';
    protected $primaryKey = 'id';
    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_config',
        'id_espacio',
        'codigo',
        'valor',
        'id_usuario_reserva',
        'token_checkin',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'id_config' => 'integer',
        'id_espacio' => 'integer',
        'valor' => 'float',
        'id_usuario_reserva' => 'integer',
        'token_checkin' => 'string',
        'estado' => 'string',
        'observaciones' => 'string',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    protected $hidden = [
        'token_checkin',
    ];

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'id_espacio');
    }

    public function configuracion()
    {
        return $this->belongsTo(EspacioConfiguracion::class, 'id_config');
    }

    public function usuarioReserva()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_reserva');
    }
}
