<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleReserva extends Model
{
    use HasFactory;

    protected $table = 'reservas_detalles';
    protected $primaryKey = null; // PK compuesta (id_reserva, id_elemento)
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_reserva',
        'id_elemento',
        'cantidad',
        'valor_unitario',
    ];

    protected $casts = [
        'id_reserva' => 'integer',
        'id_elemento' => 'integer',
        'cantidad' => 'integer',
        'valor_unitario' => 'decimal:2',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reservas::class, 'id_reserva');
    }

    public function elemento()
    {
        return $this->belongsTo(Elemento::class, 'id_elemento');
    }
}
