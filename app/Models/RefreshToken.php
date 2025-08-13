<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'token_hash',
        'dispositivo',
        'ip',
        'creado_en',
        'expira_en',
        'revocado_en',
        'reemplazado_por_token_hash',
    ];

    protected $casts = [
        'creado_en' => 'datetime',
        'expira_en' => 'datetime',
        'revocado_en' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function isRevoked(): bool
    {
        return !is_null($this->revocado_en);
    }

    public function isExpired(): bool
    {
        return $this->expira_en instanceof Carbon
            ? now()->greaterThan($this->expira_en)
            : false;
    }

    public function scopeActivos($query)
    {
        return $query
            ->whereNull('revocado_en')
            ->where(function ($q) {
                $q->whereNull('expira_en')
                    ->orWhere('expira_en', '>=', now());
            });
    }

    public function scopeDeUsuario($query, int $idUsuario)
    {
        return $query->where('id_usuario', $idUsuario);
    }

    public function revocar(?string $reemplazadoPorTokenHash = null): bool
    {
        $this->revocado_en = now();
        if ($reemplazadoPorTokenHash) {
            $this->reemplazado_por_token_hash = $reemplazadoPorTokenHash;
        }
        return $this->save();
    }
}
