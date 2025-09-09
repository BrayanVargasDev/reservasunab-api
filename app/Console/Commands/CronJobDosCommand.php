<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobsService;
use Illuminate\Support\Facades\Log;

class CronJobDosCommand extends Command
{
    protected $signature = 'cron:procesar-novedades';
    protected $description = 'Procesa novedades de espacios';

    public function __construct(private readonly CronJobsService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        Log::info('[CRON] Iniciando comando cron:procesar-novedades');
        $this->service->procesarNovedades();
        $this->info('cron:procesar-novedades ejecutado (ver logs).');
        return self::SUCCESS;
    }
}
