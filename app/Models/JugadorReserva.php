<?php

namespace App\Models;

use App\Traits\ManageTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JugadorReserva extends Model
{
    use HasFactory, SoftDeletes, ManageTimezone;

    protected $table = 'reservas_jugadores';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_reserva',
        'id_usuario',
    'id_beneficiario',
    ];

    protected $casts = [
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    // Relaciones
    public function reserva()
    {
        return $this->belongsTo(Reservas::class, 'id_reserva', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class, 'id_beneficiario', 'id');
    }
}
