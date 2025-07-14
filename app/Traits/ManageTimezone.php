<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait ManageTimezone
{
    /**
     * Convierte una fecha a la zona horaria de la aplicación
     */
    public function toAppTimezone($date, $format = null)
    {
        if (!$date) {
            return null;
        }

        $carbonDate = Carbon::parse($date)->setTimezone(config('app.timezone'));

        return $format ? $carbonDate->format($format) : $carbonDate;
    }

    /**
     * Obtiene la fecha actual en la zona horaria de la aplicación
     */
    public function getCurrentDateInAppTimezone($format = null)
    {
        $now = Carbon::now(config('app.timezone'));

        return $format ? $now->format($format) : $now;
    }

    /**
     * Convierte una fecha de la zona horaria de la aplicación a UTC
     */
    public function toUtcFromAppTimezone($date)
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date, config('app.timezone'))->utc();
    }

    /**
     * Formatea una fecha en español para Colombia
     */
    public function formatForColombia($date, $format = 'd/m/Y H:i')
    {
        if (!$date) {
            return null;
        }

        Carbon::setLocale('es');
        $carbonDate = Carbon::parse($date)->setTimezone(config('app.timezone'));

        return $carbonDate->format($format);
    }

    /**
     * Obtiene el inicio del día en la zona horaria de la aplicación
     */
    public function getStartOfDayInAppTimezone($date = null)
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::now();

        return $targetDate->setTimezone(config('app.timezone'))->startOfDay();
    }

    /**
     * Obtiene el final del día en la zona horaria de la aplicación
     */
    public function getEndOfDayInAppTimezone($date = null)
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::now();

        return $targetDate->setTimezone(config('app.timezone'))->endOfDay();
    }

    /**
     * Crea una fecha desde un string directamente en la zona horaria de la aplicación
     */
    public function createDateInAppTimezone($value, $format)
    {
        try {
            return Carbon::createFromFormat($format, $value, config('app.timezone'));
        } catch (\Exception $e) {
            Log::warning('Error al crear fecha en zona horaria de aplicación', [
                'value' => $value,
                'format' => $format,
                'timezone' => config('app.timezone'),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
