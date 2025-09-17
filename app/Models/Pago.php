<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Pago extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    protected $table = 'pagos';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'ulid';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'ticket_id',
        'valor',
        'estado',
        'url_ecollect',
    ];

    protected $casts = [
        'ticket_id' => 'integer',
        'valor' => 'decimal:2',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(PagosDetalles::class, 'id_pago', 'codigo');
    }

    public function reserva()
    {
        return $this->hasOneThrough(
            Reservas::class,
            PagosDetalles::class,
            'id_pago',
            'id',
            'codigo',
            'id_concepto'
        )
            // Incluir detalles de pago eliminados lógicamente en la relación through
            ->withTrashedParents()
            // Incluir reservas eliminadas lógicamente
            ->withTrashed()
            ->where('pagos_detalles.tipo_concepto', 'reserva');
    }

    public function mensualidad()
    {
        return $this->hasOneThrough(
            Mensualidades::class,
            PagosDetalles::class,
            'id_pago',
            'id',
            'codigo',
            'id_concepto'
        )
            // Incluir detalles de pago eliminados lógicamente en la relación through
            ->withTrashedParents()
            // Incluir mensualidades eliminadas lógicamente
            ->withTrashed()
            ->where('pagos_detalles.tipo_concepto', 'mensualidad');
    }

    public function elementos()
    {
        return $this->hasManyThrough(
            Elemento::class,
            PagosDetalles::class,
            'id_pago',
            'id',
            'codigo',
            'id_concepto'
        )
            // Incluir detalles de pago eliminados lógicamente en la relación through
            ->withTrashedParents()
            // Incluir elementos eliminados lógicamente si aplica
            ->withTrashed()
            ->where('pagos_detalles.tipo_concepto', 'elemento');
    }

    public function scopeSearch($query, string $input)
    {
        $tokens = explode(' ', trim($input));

        foreach ($tokens as $token) {
            $query->where(function ($q) use ($token) {
                $tokenLower = strtolower($token);
                // Fechas (dd/mm/yyyy o dd/mm/yy)
                try {
                    $fecha = Carbon::createFromFormat('d/m/Y', $token);
                    $q->orWhereDate('pagos.creado_en', $fecha);
                    $q->orWhereHas('reserva', fn($qr) => $qr->whereDate('reservas.fecha', $fecha));
                    $q->orWhereHas('mensualidad', fn($qm) => $qm->whereDate('mensualidades.creado_en', $fecha));
                } catch (\Exception $e) {
                    try {
                        $fecha = Carbon::createFromFormat('d/m/y', $token);
                        $q->orWhereDate('pagos.creado_en', $fecha);
                        $q->orWhereHas('reserva', fn($qr) => $qr->whereDate('reservas.fecha', $fecha));
                        $q->orWhereHas('mensualidad', fn($qm) => $qm->whereDate('mensualidades.creado_en', $fecha));
                    } catch (\Exception $e) {
                    }
                }

                // Estado de pago
                $estadoMap = [
                    'aprobado' => ['OK'],
                    'expirado' => ['EXPIRED'],
                    'pendiente' => ['PENDING'],
                    'rechazado' => ['NOT_AUTHORIZED'],
                    'error' => ['ERROR', 'FAILED']
                ];

                if (array_key_exists($tokenLower, $estadoMap)) {
                    $q->orWhereIn('estado', $estadoMap[$tokenLower]);
                } else {
                    $q->orWhereRaw('LOWER(estado) ILIKE ?', ['%' . $tokenLower . '%']);
                }

                // Nombre (usuario asociado a reserva o mensualidad)
                $q->orWhereHas('reserva.usuarioReserva.persona', function ($qu) use ($tokenLower) {
                    $qu->whereRaw('LOWER(primer_nombre) ILIKE ?', ['%' . $tokenLower . '%'])
                        ->orWhereRaw('LOWER(segundo_nombre) ILIKE ?', ['%' . $tokenLower . '%'])
                        ->orWhereRaw('LOWER(primer_apellido) ILIKE ?', ['%' . $tokenLower . '%'])
                        ->orWhereRaw('LOWER(segundo_apellido) ILIKE ?', ['%' . $tokenLower . '%']);
                });

                $q->orWhereHas('mensualidad.usuario.persona', function ($qu) use ($tokenLower) {
                    $qu->whereRaw('LOWER(primer_nombre) ILIKE ?', ['%' . $tokenLower . '%'])
                        ->orWhereRaw('LOWER(segundo_nombre) ILIKE ?', ['%' . $tokenLower . '%'])
                        ->orWhereRaw('LOWER(primer_apellido) ILIKE ?', ['%' . $tokenLower . '%'])
                        ->orWhereRaw('LOWER(segundo_apellido) ILIKE ?', ['%' . $tokenLower . '%']);
                });

                $q->orWhereHas('reserva', function ($qr) use ($token) {
                    if (method_exists($qr->getModel(), 'bootSoftDeletes')) {
                        $qr->withTrashed();
                    }
                    $qr->whereHas('espacio', function ($qe) use ($token) {
                        $qe->where('espacios.nombre', 'ILIKE', '%' . $token . '%')
                            ->orWhere('espacios.codigo', 'ILIKE', '%' . $token . '%');
                    });
                });

                $q->orWhereHas('mensualidad', function ($qm) use ($token) {
                    if (method_exists($qm->getModel(), 'bootSoftDeletes')) {
                        $qm->withTrashed();
                    }
                    $qm->whereHas('espacio', function ($qe) use ($token) {
                        $qe->where('espacios.nombre', 'ILIKE', '%' . $token . '%')
                            ->orWhere('espacios.codigo', 'ILIKE', '%' . $token . '%');
                    });
                });
            });
        }

        return $query;
    }
}
