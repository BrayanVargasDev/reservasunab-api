<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use HasFactory;

    protected $table = 'departamentos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
        'id_pais',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'id_pais' => 'integer',
    ];

    /**
     * Relación con país
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'id_pais');
    }

    /**
     * Relación con ciudades del departamento
     */
    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class, 'id_departamento');
    }
}
