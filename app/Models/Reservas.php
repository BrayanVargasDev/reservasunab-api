<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ManageTimezone;
use Carbon\Carbon;

class Reservas extends Model
{
    use HasFactory, SoftDeletes, ManageTimezone;

    protected $table = 'reservas';
    protected $primaryKey = 'id';
    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_configuracion',
        'id_espacio',
        'id_usuario',
        'fecha',
        'estado',
        'hora_inicio',
        'hora_fin',
        'check_in',
        'codigo',
        'valor',
        'observaciones',
    ];

    protected $casts = [
        'id_configuracion' => 'integer',
        'id_espacio' => 'integer',
        'valor' => 'float',
        'id_usuario' => 'integer',
        'fecha' => 'datetime',
        'hora_inicio' => 'datetime',
        'hora_fin' => 'datetime',
        'check_in' => 'boolean',
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
        return $this->belongsTo(EspacioConfiguracion::class, 'id_configuracion', 'id');
    }

    public function usuarioReserva()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function pago()
    {
        return $this->hasOne(Pago::class, 'id_reserva');
    }

    public function jugadores()
    {
        return $this->hasMany(JugadorReserva::class, 'id_reserva');
    }

    /**
     * Scope para obtener reservas que pueden ser canceladas
     */
    public function scopePuedeCancelar($query)
    {
        return $query->whereHas('configuracion', function ($q) {
            $q->whereRaw('
                reservas.fecha > NOW()
                AND TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(reservas.fecha, " ", reservas.hora_inicio)) >= espacios_configuracion.tiempo_cancelacion
            ');
        });
    }

    /**
     * Verifica si la reserva puede ser cancelada
     */
    public function puedeSerCancelada()
    {
        if ($this->fecha->isPast()) {
            return false;
        }

        if (!$this->configuracion) {
            return false;
        }

        $fechaHoraReserva = $this->fecha->format('Y-m-d') . ' ' . $this->hora_inicio->format('H:i:s');
        $momentoReserva = Carbon::parse($fechaHoraReserva);

        $tiempoCancelacion = $this->configuracion->tiempo_cancelacion ?? 0;

        $minutosHastaReserva = now()->diffInMinutes($momentoReserva, false);

        if ($minutosHastaReserva < 0) {
            return false;
        }

        return $minutosHastaReserva >= $tiempoCancelacion;
    }
}
