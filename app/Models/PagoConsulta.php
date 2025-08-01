<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoConsulta extends Model
{
    /** @use HasFactory<\Database\Factories\PagoConsultaFactory> */
    use HasFactory;

    protected $table = 'pago_consultas';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'valor_real',
        'valor_transaccion',
        'estado',
        'ticket_id',
        'codigo_traza',
        'medio_pago',
        'tipo_doc_titular',
        'numero_doc_titular',
        'nombre_titular',
        'email_titular',
        'celular_titular',
        'descripcion_pago',
        'nombre_medio_pago',
        'tarjeta_oculta',
        'ultimos_cuatro',
        'fecha_banco',
        'moneda',
        'id_reserva',
        'hora_inicio',
        'hora_fin',
        'fecha_reserva',
        'codigo_reserva',
        'id_usuario_reserva',
        'tipo_doc_usuario_reserva',
        'doc_usuario_reserva',
        'email_usuario_reserva',
        'celular_usuario_reserva',
        'id_espacio',
        'nombre_espacio',
    ];

    protected $casts = [
        'valor_real' => 'decimal:2',
        'valor_transaccion' => 'decimal:2',
        'fecha_banco' => 'datetime',
        'hora_inicio' => 'datetime',
        'hora_fin' => 'datetime',
    ];
}
