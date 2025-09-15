<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\CronJobUnoCommand::class,
        \App\Console\Commands\CronJobDosCommand::class,
        \App\Console\Commands\CronJobTresCommand::class,
        \App\Console\Commands\ConfirmarPagosCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('cron:reservas-sin-pago')->everyMinute()->runInBackground();
        $schedule->command('cron:procesar-novedades')->dailyAt('00:00')->runInBackground();
        $schedule->command('cron:reportar-reservas-mensualidades')->hourly()->runInBackground();
        $schedule->command('cron:confirmar-pagos')->everyMinute()->runInBackground();

        // Referencia oficial: https://laravel.com/docs/12.x/scheduling
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
