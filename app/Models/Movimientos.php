<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Movimientos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'movimientos';
    protected $primaryKey = 'id';
    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'id_usuario',
        'id_reserva',
        'id_movimiento_principal',
        'fecha',
        'valor',
        'tipo',
        'creado_por',
    ];

    // Tipos permitidos
    public const TIPO_INGRESO = 'ingreso';
    public const TIPO_EGRESO = 'egreso';
    public const TIPO_AJUSTE = 'ajuste';

    public const TIPOS_VALIDOS = [
        self::TIPO_INGRESO,
        self::TIPO_EGRESO,
        self::TIPO_AJUSTE,
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'id_reserva' => 'integer',
        'id_movimiento_principal' => 'integer',
        'valor' => 'decimal:2',
        'fecha' => 'datetime',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function reserva()
    {
        return $this->belongsTo(Reservas::class, 'id_reserva');
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
    }

    public function movimientoPrincipal()
    {
        return $this->belongsTo(Movimientos::class, 'id_movimiento_principal');
    }

    public function movimientosRelacionados()
    {
        return $this->hasMany(Movimientos::class, 'id_movimiento_principal');
    }

    // Mutator para validar tipo
    public function setTipoAttribute($value)
    {
        $value = strtolower($value);
        if (!in_array($value, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de movimiento inválido: {$value}");
        }
        $this->attributes['tipo'] = $value;
    }

    // Helpers
    public function esIngreso(): bool
    {
        return $this->tipo === self::TIPO_INGRESO;
    }
    public function esEgreso(): bool
    {
        return $this->tipo === self::TIPO_EGRESO;
    }
    public function esAjuste(): bool
    {
        return $this->tipo === self::TIPO_AJUSTE;
    }
}
