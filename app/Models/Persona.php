<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Persona extends Model {
    use HasFactory;

    protected $table = 'personas';
    protected $primaryKey = 'id_persona';
    protected $keyType = 'integer'; // Cambiado de string a integer para SERIAL

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'tipo_documento_id',
        'numero_documento',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'fecha_nacimiento',
        'direccion',
        'celular',
        'version',
        'id_usuario',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'datetime',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    public function usuario(): HasOne {
        return $this->hasOne(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function tipoDocumento(): BelongsTo {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }
}
