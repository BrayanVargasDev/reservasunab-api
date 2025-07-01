<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class CleanExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum:clean-expired-tokens
                            {--days=30 : Delete tokens older than specified days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired and old Sanctum personal access tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');

        $expiredTokens = PersonalAccessToken::where('expires_at', '<', now())->count();
        PersonalAccessToken::where('expires_at', '<', now())->delete();

        $oldTokens = PersonalAccessToken::whereNull('expires_at')
            ->where('created_at', '<', now()->subDays($days))
            ->count();

        PersonalAccessToken::whereNull('expires_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $totalCleaned = $expiredTokens + $oldTokens;

        if ($totalCleaned > 0) {
            $this->info("Limpiados {$totalCleaned} tokens: {$expiredTokens} expirados y {$oldTokens} antiguos (más de {$days} días).");

            Log::info('Tokens de Sanctum limpiados', [
                'tokens_expirados' => $expiredTokens,
                'tokens_antiguos' => $oldTokens,
                'total_limpiados' => $totalCleaned,
                'dias_limite' => $days
            ]);
        } else {
            $this->info('No se encontraron tokens para limpiar.');
        }

        return 0;
    }
}
