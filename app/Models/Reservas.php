<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ManageTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        'observaciones',
        'reportado',
        'fallos_reporte',
        'ultimo_error_reporte',
        'precio_base',
        'precio_espacio',
        'precio_elementos',
        'precio_total',
    ];

    protected $casts = [
        'id_configuracion' => 'integer',
        'id_espacio' => 'integer',
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
        'reportado' => 'boolean',
        'fallos_reporte' => 'integer',
        'ultimo_error_reporte' => 'string',
        'precio_base' => 'decimal:2',
        'precio_espacio' => 'decimal:2',
        'precio_elementos' => 'decimal:2',
        'precio_total' => 'decimal:2',
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

    public function detalles()
    {
        return $this->hasMany(DetalleReserva::class, 'id_reserva');
    }

    public function pago()
    {
        return $this->hasOneThrough(
            Pago::class,
            PagosDetalles::class,
            'id_concepto',
            'codigo',
            'id',
            'id_pago'
        );
        // ->where('pagos_detalles.tipo_concepto', 'reserva');
    }

    public function jugadores()
    {
        return $this->hasMany(JugadorReserva::class, 'id_reserva');
    }

    public function movimientos()
    {
        return $this->hasMany(Movimientos::class, 'id_reserva');
    }

    public function scopePuedeCancelar($query)
    {
        return $query
            ->whereDoesntHave('movimientos')
            ->whereHas('configuracion', function ($q) {
                $q->whereRaw('
                    reservas.fecha > NOW()
                    AND TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(reservas.fecha, " ", reservas.hora_inicio)) >= espacios_configuracion.tiempo_cancelacion
                ');
            });
    }

    public function puedeSerCancelada()
    {
        if (!$this->configuracion) {
            return false;
        }

        if ($this->movimientos()->exists()) {
            return false;
        }

        if ($this->estado === 'cancelada') {
            return false;
        }

        $fechaHoraReserva = $this->fecha->format('Y-m-d') . ' ' . $this->hora_inicio->format('H:i:s');
        $momentoReserva = Carbon::parse($fechaHoraReserva);

        if ($momentoReserva->isPast()) {
            return false;
        }

        $tiempoCancelacion = $this->configuracion->tiempo_cancelacion ?? 0;

        $minutosHastaReserva = now()->diffInMinutes($momentoReserva, false);

        if ($minutosHastaReserva < 0) {
            return false;
        }

        return $minutosHastaReserva >= $tiempoCancelacion;
    }
}
