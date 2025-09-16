<?php

namespace App\Models;

use App\Traits\ManageTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Espacio extends Model
{
    use HasFactory, SoftDeletes, ManageTimezone;

    protected $table = 'espacios';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'nombre',
        'descripcion',
        'agregar_jugadores',
        'minimo_jugadores',
        'maximo_jugadores',
        'permite_externos',
        'reservas_simultaneas',
        'aprobar_reserva',
        'tiempo_limite_reserva',
        'despues_hora',
        'pago_mensual',
        'valor_mensualidad',
        'id_edificio',
        'codigo',
        'id_sede',
        'id_categoria',
        'creado_por',
        'actualizado_por',
        'eliminado_por',
    ];

    protected $casts = [
        'agregar_jugadores' => 'boolean',
        'minimo_jugadores' => 'integer',
        'maximo_jugadores' => 'integer',
        'reservas_simultaneas' => 'integer',
        'permite_externos' => 'boolean',
        'aprobar_reserva' => 'boolean',
        'tiempo_limite_reserva' => 'integer',
        'despues_hora' => 'boolean',
        'id_edificio' => 'integer',
        'id_sede' => 'integer',
        'id_categoria' => 'integer',
        'creado_por' => 'integer',
        'actualizado_por' => 'integer',
        'eliminado_por' => 'integer',
        'codigo' => 'string',
        'pago_mensual' => 'boolean',
        'valor_mensualidad' => 'decimal:2',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'id_sede', 'id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
    }

    public function imagen()
    {
        return $this->hasOne(EspacioImagen::class, 'id_espacio', 'id');
    }

    public function novedades()
    {
        return $this->hasMany(EspacioNovedad::class, 'id_espacio', 'id');
    }

    public function tipo_usuario_config()
    {
        return $this->hasMany(EspacioTipoUsuarioConfig::class, 'id_espacio', 'id');
    }

    public function configuraciones()
    {
        return $this->hasMany(EspacioConfiguracion::class, 'id_espacio', 'id');
    }

    public function edificio()
    {
        return $this->belongsTo(Edificio::class, 'id_edificio', 'id');
    }

    public function elementos()
    {
        return $this->belongsToMany(Elemento::class, 'elementos_espacios', 'id_espacio', 'id_elemento');
    }

    public function scopeFiltros($query, $sede, $categoria, $grupo)
    {
        return $query->when($sede, fn($q) => $q->where('id_sede', $sede))
            ->when($categoria, fn($q) => $q->where('id_categoria', $categoria))
            ->when($grupo, fn($q) => $q->whereHas(
                'categoria.grupo',
                fn($q) => $q->whereKey($grupo)
            ));
    }
}
