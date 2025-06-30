<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EspacioImagen extends Model
{
    use HasFactory;

    protected $table = 'espacios_imagenes';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';

    protected $fillable = [
        'id_espacio',
        'url',
        'titulo',
        'codigo', // ! El hash único del archivo para no repetirlo
        'ubicacion',
    ];

    protected $casts = [
        'id_espacio' => 'integer',
        'url' => 'string',
        'titulo' => 'string',
        'codigo' => 'string',
        'ubicacion' => 'string',
        'creado_en' => 'datetime',
    ];

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = null;

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'id_espacio', 'id');
    }
}
