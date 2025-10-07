<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobsService;
use Illuminate\Support\Facades\Log;

class RepetirCancelacionesCommand extends Command
{
    protected $signature = 'cron:repetir-cancelaciones';
    protected $description = 'Envía cancelaciones pendientes de reservas canceladas al servicio UNAB';

    public function __construct(private readonly CronJobsService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        Log::info('[CRON] Iniciando comando cron:repetir-cancelaciones');
        $this->service->procesarCancelacionesPendientes();
        $this->info('cron:repetir-cancelaciones ejecutado (ver logs).');
        return self::SUCCESS;
    }
}
