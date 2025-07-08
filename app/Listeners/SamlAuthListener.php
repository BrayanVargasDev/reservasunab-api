<?php

namespace App\Listeners;

use App\Events\SamlAuth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SamlAuthListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SamlAuth $event): void
    {
        // Aquí puedes agregar lógica adicional después de la autenticación SAML
        Log::info('SamlAuth event handled', [
            'user_id' => $event->user->id_usuario,
            'action' => $event->action,
            'email' => $event->user->email
        ]);

        // Ejemplos de lo que podrías hacer:

        // 1. Registrar última actividad
        $event->user->update(['last_login' => now()]);

        // 2. Enviar notificación de login
        // if ($event->action === 'login') {
        //     // Enviar email de confirmación de login
        // }

        // 3. Sincronizar datos adicionales con sistemas externos
        // if (!empty($event->samlData)) {
        //     $this->syncUserData($event->user, $event->samlData);
        // }

        // 4. Crear log de auditoría
        // AuditLog::create([
        //     'user_id' => $event->user->id_usuario,
        //     'action' => 'saml_' . $event->action,
        //     'ip_address' => request()->ip(),
        //     'user_agent' => request()->userAgent(),
        // ]);
    }

    /**
     * Sincronizar datos del usuario con información SAML
     */
    private function syncUserData($user, $samlData)
    {
        // Implementar sincronización de datos adicionales si es necesario
        // Por ejemplo, actualizar departamento, rol, etc.
    }
}
