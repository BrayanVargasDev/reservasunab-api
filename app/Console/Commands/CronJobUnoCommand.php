<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobsService;
use Illuminate\Support\Facades\Log;

class CronJobUnoCommand extends Command
{
    protected $signature = 'cron:reservas-sin-pago';
    protected $description = 'Cancela (soft delete) reservas sin pago OK después de 30 minutos';

    public function __construct(private readonly CronJobsService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
    Log::info('[CRON] Iniciando comando cron:reservas-sin-pago');
    $this->service->procesarReservasSinPago();
    $this->info('cron:reservas-sin-pago ejecutado (ver logs).');
        return self::SUCCESS;
    }
}
