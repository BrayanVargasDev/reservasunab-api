<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Beneficiario extends Model
{
    use HasFactory;

    protected $table = 'beneficiarios';
    protected $primaryKey = 'id';
    protected $keyType = 'integer';
    public $timestamps = false; // usamos columnas personalizadas

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'id_usuario',
        'nombre',
        'apellido',
        'tipo_documento_id',
        'documento',
        'parentesco',
        'creado_en',
        'actualizado_en',
    ];

    protected $casts = [
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id', 'id_tipo');
    }
}
