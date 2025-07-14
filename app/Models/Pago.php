<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'id_reserva',
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

    public function reserva()
    {
        return $this->belongsTo(Reservas::class, 'id_reserva');
    }
}
