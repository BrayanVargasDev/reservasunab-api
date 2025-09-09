<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Edificio extends Model
{
    protected $table = 'edificios';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'nombre',
        'codigo',
    ];

    protected $casts = [
        'nombre' => 'string',
        'codigo' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function espacios()
    {
        return $this->hasMany(Espacio::class, 'id_edificio');
    }
}
