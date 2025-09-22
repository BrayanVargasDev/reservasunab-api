<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TokenService
{
    public function generarAccessToken(Usuario $user, $ttlMinutes = 15)
    {
        $expiresAt = $ttlMinutes ? now()->addMinutes($ttlMinutes) : null;

        try {
            $newToken = $user->createToken('access', ['*'], $expiresAt);
        } catch (\ArgumentCountError $e) {
            $newToken = $user->createToken('access', ['*']);
        }

        $model = $newToken->accessToken ?? null;
        $finalExpiresAt = $model->expires_at ?? $expiresAt;

        return [
            'token' => $newToken->accessToken,
            'expires_at' => $finalExpiresAt instanceof Carbon ? $finalExpiresAt : ($finalExpiresAt ? Carbon::parse($finalExpiresAt) : null),
        ];
    }

    public function crearRefreshTokenParaUsuario(Usuario $user, $ip = null, $device = null, $days = 30)
    {
        $raw = Str::random(64) . '.' . bin2hex(random_bytes(16)); // token opaco
        $hash = hash('sha256', $raw);

        $expiresAt = now()->addDays($days);

        $rt = RefreshToken::create([
            'id_usuario' => $user->id_usuario,
            'token_hash' => $hash,
            'dispositivo' => $device,
            'ip' => $ip,
            'creado_en' => now(),
            'expira_en' => $expiresAt,
        ]);

        return ['raw' => $raw, 'model' => $rt];
    }

    public function validarRefreshTokenHash($raw): ?RefreshToken
    {
        return RefreshToken::where('token_hash', $raw)->first();
    }

    /**
     * Valida un refresh token raw contra BD y opcionalmente verifica ip/dispositivo.
     * No rota ni crea nuevos tokens; solo valida estado.
     */
    public function validarRefreshToken(string $raw, ?string $ip = null, ?string $device = null): ?RefreshToken
    {
        $model = $this->validarRefreshTokenHash($raw);
        if (!$model) return null;

        // Estado
        if ($model->isRevoked() || $model->isExpired()) return null;

        // Coincidencia de contexto flexible: acepta registros con ip/dispositivo invertidos
        if ($ip !== null || $device !== null) {
            $mIp = $model->ip;
            $mDev = $model->dispositivo;
            $match = false;
            if ($device !== null && $ip !== null) {
                $match = ($mDev === $device && $mIp === $ip) || ($mDev === $ip && $mIp === $device);
            } elseif ($device !== null) {
                $match = ($mDev === $device || $mIp === $device);
            } elseif ($ip !== null) {
                $match = ($mIp === $ip || $mDev === $ip);
            }
            if (!$match) return null;
        }

        return $model;
    }

    /**
     * Emite un access token a partir de un refresh token válido.
     * Si el refresh token sigue vigente, no se recrea; se puede rotar opcionalmente cercano a expiración.
     *
     * @return array{access_token:string, token_expires_at:Carbon\CarbonInterface|null, refresh_token:string, rotated_refresh:bool}
     */
    public function emitirDesdeRefresh(string $raw, ?string $ip = null, ?string $device = null, ?int $accessTtlMinutes = null, bool $rotarRefreshSiCasiExpira = false, int $umbralDias = 3): array
    {
        $rt = $this->validarRefreshToken($raw, $ip, $device);
        if (!$rt) {
            throw new \RuntimeException('invalid_refresh_token');
        }

        $user = $rt->usuario; // relación en el modelo

        // ¿Debemos rotar el refresh token?
        $rotated = false;
        if ($rotarRefreshSiCasiExpira && $rt->expira_en instanceof Carbon) {
            if (now()->diffInDays($rt->expira_en, false) <= $umbralDias) {
                $nuevo = $this->crearRefreshTokenParaUsuario($user, $ip, $device, 30);
                // Marcar anterior como revocado y enlazar con el nuevo
                $rt->revocar(hash('sha256', $nuevo['raw']));
                $raw = $nuevo['raw'];
                $rt = $nuevo['model'];
                $rotated = true;
            }
        }

        // Emitir access token
        $access = $this->generarAccessToken($user, $accessTtlMinutes ?? (int) (config('sanctum.expiration') ?? 60));

        return [
            'access_token' => $access['token'],
            'token_expires_at' => $access['expires_at'] ?? null,
            'refresh_token' => $raw, // devolvemos el mismo raw si no se rotó
            'rotated_refresh' => $rotated,
        ];
    }
}
