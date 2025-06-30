<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';
    protected $primaryKey = 'id_tipo';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function personas()
    {
        return $this->hasMany(Persona::class, 'tipo_documento_id');
    }
}
