<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthCode extends Model
{
    protected $table = 'auth_codes';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'codigo',
        'refresh_token_hash',
        'expira_en',
        'consumido',
    ];

    protected $hidden = [
        'refresh_token_hash',
    ];

    protected $casts = [
        'expira_en' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
