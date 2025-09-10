<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PagosDetalles extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pagos_detalles';
    protected $primaryKey = 'id';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_pago',
        'tipo_concepto',
        'cantidad',
        'id_concepto',
        'total',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'total' => 'decimal:2',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'id_pago', 'codigo');
    }

    public function reserva()
    {
        return $this->belongsTo(Reservas::class, 'id_concepto', 'id');
    }
}
