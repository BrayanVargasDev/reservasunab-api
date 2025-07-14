<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class UpdateTimezoneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timezone:check {--set-bogota : Configura la zona horaria a America/Bogota}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica y gestiona la configuración de zona horaria de la aplicación';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentTimezone = config('app.timezone');
        $this->info("🕐 Zona horaria actual de la aplicación: {$currentTimezone}");
        
        // Mostrar la hora actual en diferentes zonas horarias
        $utcTime = Carbon::now('UTC');
        $appTime = Carbon::now($currentTimezone);
        $bogotaTime = Carbon::now('America/Bogota');
        
        $this->line('');
        $this->info('⏰ Comparación de horarios:');
        $this->line("UTC: {$utcTime->format('Y-m-d H:i:s')}");
        $this->line("Aplicación ({$currentTimezone}): {$appTime->format('Y-m-d H:i:s')}");
        $this->line("Bogotá (America/Bogota): {$bogotaTime->format('Y-m-d H:i:s')}");
        
        // Verificar si la configuración es la correcta para Colombia
        if ($currentTimezone !== 'America/Bogota') {
            $this->warn('⚠️  La zona horaria actual no está configurada para Colombia.');
            
            if ($this->option('set-bogota')) {
                $this->setTimezoneToBogotan();
            } else {
                $this->info('💡 Para configurar la zona horaria de Bogotá, ejecuta:');
                $this->line('   php artisan timezone:check --set-bogota');
            }
        } else {
            $this->info('✅ La zona horaria está correctamente configurada para Colombia.');
        }
        
        // Mostrar información adicional
        $this->line('');
        $this->info('📋 Información adicional:');
        $this->line("- PHP timezone: " . date_default_timezone_get());
        $this->line("- Carbon locale: " . Carbon::getLocale());
        
        return Command::SUCCESS;
    }
    
    /**
     * Configura la zona horaria a America/Bogota
     */
    private function setTimezoneToBogotan()
    {
        $this->info('🔧 Configurando zona horaria a America/Bogota...');
        
        // Actualizar la configuración en runtime
        config(['app.timezone' => 'America/Bogota']);
        date_default_timezone_set('America/Bogota');
        
        $this->info('✅ Zona horaria actualizada a America/Bogota en esta sesión.');
        $this->warn('⚠️  Para hacer el cambio permanente, asegúrate de que:');
        $this->line('   1. El archivo .env contenga: APP_TIMEZONE=America/Bogota');
        $this->line('   2. El archivo config/app.php use env("APP_TIMEZONE", "America/Bogota")');
        $this->line('   3. Ejecuta: php artisan config:cache');
    }
}
