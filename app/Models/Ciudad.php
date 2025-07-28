<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ciudad extends Model
{
    use HasFactory;

    protected $table = 'ciudades';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
        'id_departamento',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'id_departamento' => 'integer',
    ];

    /**
     * Relación con departamento
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'id_departamento');
    }

    /**
     * Relación con personas que tienen esta ciudad como lugar de expedición
     */
    public function personasExpedicion(): HasMany
    {
        return $this->hasMany(Persona::class, 'ciudad_expedicion_id');
    }

    /**
     * Relación con personas que tienen esta ciudad como lugar de residencia
     */
    public function personasResidencia(): HasMany
    {
        return $this->hasMany(Persona::class, 'ciudad_residencia_id');
    }
}
