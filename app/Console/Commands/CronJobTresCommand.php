<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobsService;
use Illuminate\Support\Facades\Log;

class CronJobTresCommand extends Command
{
    protected $signature = 'cron:reportar-reservas-mensualidades';
    protected $description = 'Job Tres (estructura base)';

    public function __construct(private readonly CronJobsService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        Log::info('[CRON] Iniciando comando cron:reportar-reservas-mensualidades');
        $this->service->procesarReporteReservasMensualidades();
        $this->info('cron:reportar-reservas-mensualidades ejecutado (ver logs).');
        return self::SUCCESS;
    }
}
