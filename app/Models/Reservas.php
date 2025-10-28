<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ManageTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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
        'aprobado_por',
        'aprobado_en',
        'cancel_enviada',
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
        'cancel_enviada' => 'boolean',
        'fallos_reporte' => 'integer',
        'ultimo_error_reporte' => 'string',
        'precio_base' => 'decimal:2',
        'precio_espacio' => 'decimal:2',
        'precio_elementos' => 'decimal:2',
        'precio_total' => 'decimal:2',
        'aprobado_por' => 'integer',
        'aprobado_en' => 'datetime',
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
        )
            ->where('pagos_detalles.tipo_concepto', 'reserva')
            ->latest('pagos.creado_en')
            ->limit(1);
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
        // Verificar si el usuario es administrador y la reserva es para hoy
        $usuario = Auth::user();
        if ($usuario && $usuario->esAdministrador() && $this->fecha->isToday()) {
            return true;
        }

        if (!$this->configuracion) {
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

    public function aprobadoPor()
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por', 'id_usuario');
    }

    public function scopeSearch($query, string $input)
    {
        $tokens = preg_split('/\s+/', trim($input));

        foreach ($tokens as $token) {
            $query->where(function ($q) use ($token) {
                // Fecha
                try {
                    $fecha = Carbon::createFromFormat('d/m/Y', $token);
                    $q->orWhereDate('reservas.fecha', $fecha);
                } catch (\Exception $e) {
                    try {
                        $fecha = Carbon::createFromFormat('d/m/y', $token);
                        $q->orWhereDate('reservas.fecha', $fecha);
                    } catch (\Exception $e) {
                    }
                }

                // Hora (HH:mm)
                if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $token)) {
                    $q->orWhereTime('reservas.hora_inicio', $token);
                }

                // Código de reserva
                if (is_numeric($token)) {
                    $q->orWhere('reservas.codigo', 'ilike', "%{$token}%");
                }

                // Estado
                $estados = ['completada', 'creada', 'cancelada', 'pendiente', 'inicial', 'pagada'];
                if (in_array(strtolower($token), $estados)) {
                    $token = strtolower($token) == 'creada' ? 'inicial' : strtolower($token);
                    $token = strtolower($token) == 'pagada' ? 'completada' : strtolower($token);

                    $q->orWhere('reservas.estado', 'ilike', "%{$token}%");
                }

                // Usuario
                $q->orWhereHas('usuarioReserva', function ($qu) use ($token) {
                    $qu->whereRaw('LOWER(email) ILIKE ?', ['%' . strtolower($token) . '%'])
                        ->orWhereHas('persona', function ($qp) use ($token) {
                            $qp->whereRaw('LOWER(primer_nombre) ILIKE ?', ['%' . strtolower($token) . '%'])
                                ->orWhereRaw('LOWER(segundo_nombre) ILIKE ?', ['%' . strtolower($token) . '%'])
                                ->orWhereRaw('LOWER(primer_apellido) ILIKE ?', ['%' . strtolower($token) . '%'])
                                ->orWhereRaw('LOWER(segundo_apellido) ILIKE ?', ['%' . strtolower($token) . '%']);
                        });
                });

                // Espacio
                $q->orWhereHas('espacio', function ($qe) use ($token) {
                    $qe->where('nombre', 'ilike', "%{$token}%")
                        ->orWhere('codigo', 'ilike', "%{$token}%");
                });
            });
        }

        return $query;
    }
}
