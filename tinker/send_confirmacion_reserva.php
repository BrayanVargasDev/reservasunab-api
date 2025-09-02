<?php

use App\Mail\ConfirmacionReservaEmail;
use App\Models\Reservas;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

// Script de prueba para enviar ConfirmacionReservaEmail desde Tinker
// Uso en Tinker:
//   php artisan tinker
//   require base_path('tinker/send_confirmacion_reserva.php');
// Opcional: definir $reservaId o $toEmail antes de require para personalizar
//   $reservaId = 123; $toEmail = 'otro@correo.com'; require base_path('tinker/send_confirmacion_reserva.php');

// Permite override desde el contexto de Tinker
$toEmail = $toEmail ?? 'bvargasdev@gmail.com';

try {
    // Buscar la reserva: por ID si se definió $reservaId; de lo contrario, la más reciente
    if (isset($reservaId)) {
        $reserva = Reservas::query()
            ->with(['espacio.sede', 'usuarioReserva.persona', 'jugadores.usuario.persona', 'detalles.elemento'])
            ->find($reservaId);
        if (!$reserva) {
            echo "No se encontró la reserva con ID {$reservaId}.\n";
            return;
        }
    } else {
        $reserva = Reservas::query()
            ->with(['espacio.sede', 'usuarioReserva.persona', 'jugadores.usuario.persona', 'detalles.elemento'])
            ->latest('id')
            ->first();
        if (!$reserva) {
            echo "No hay reservas en la base de datos. Crea una primero.\n";
            return;
        }
    }

    // Intentar tomar valores desde la reserva si existen, si no usar 0 por defecto
    $valorReal = (float) ($reserva->valor_total ?? $reserva->total ?? 0);
    $valorDescuento = (float) ($reserva->valor_descuento ?? $reserva->descuento ?? 0);

    // Enviar el correo
    Mail::to($toEmail)->send(new ConfirmacionReservaEmail($reserva, $valorReal, $valorDescuento));

    echo "Correo de confirmación enviado a {$toEmail} para la reserva ID {$reserva->id}.\n";
} catch (Throwable $e) {
    // Registrar y mostrar el error para diagnóstico rápido en Tinker
    Log::error('Error enviando ConfirmacionReservaEmail desde tinker', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    echo "Error: {$e->getMessage()}\n";
}
