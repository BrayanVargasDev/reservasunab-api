<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegimenTributario extends Model
{
    use HasFactory;

    protected $table = 'regimenes_tributarios';
    protected $primaryKey = 'codigo';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Relación con personas
     */
    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'regimen_tributario_id');
    }
}
