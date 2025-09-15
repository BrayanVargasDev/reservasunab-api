<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobsService;
use Illuminate\Support\Facades\Log;

class ConfirmarPagosCommand extends Command
{
    protected $signature = 'cron:confirmar-pagos';
    protected $description = 'Confirma, valida y actualiza el estado de pagos pendientes con el proveedor';

    public function __construct(private readonly CronJobsService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        Log::info('[CRON] Iniciando comando cron:confirmar-pagos');
        $this->service->procesarPagosPendientes();
        $this->info('cron:confirmar-pagos ejecutado (ver logs).');
        return self::SUCCESS;
    }
}
