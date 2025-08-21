<?php

namespace App\Mail;

use App\Models\Reservas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmacionReservaEmail extends Mailable
{
    use Queueable, SerializesModels;

    private float $valor_real;
    private float $valor_descuento;

    /**
     * Create a new message instance.
     */
    public function __construct(protected Reservas $reserva, float $valor_real, float $valor_descuento)
    {
        $this->valor_real = $valor_real;
        $this->valor_descuento = $valor_descuento;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            // Usar el remitente configurado en config/mail.php para evitar rechazos del proveedor SMTP
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Confirmación de reserva',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $usuarioModel = $this->reserva->usuarioReserva; // modelo relacionado (no el BelongsTo)
        $persona = $usuarioModel?->persona;
        $nombreUsuario = collect([
            $persona?->primer_nombre,
            $persona?->segundo_nombre,
            $persona?->primer_apellido,
            $persona?->segundo_apellido,
        ])->filter()->implode(' ');

        if (empty($nombreUsuario)) {
            $nombreUsuario = $usuarioModel?->email ?? 'Usuario';
        }

        return new Content(
            // Nombre correcto de la vista (carpeta resources/views/emails/confirmacion_reserva.blade.php)
            view: 'emails.confirmacion_reserva',
            with: [
                'usuario' => $nombreUsuario,
                'espacio' => $this->reserva->espacio, // modelo ya cargado
                'fecha' => $this->reserva->fecha->format('d/m/Y'),
                'hora_inicio' => $this->reserva->hora_inicio->format('g:i A'),
                'hora_fin' => $this->reserva->hora_fin?->format('g:i A'),
                'estado' => $this->reserva->estado,
                'codigo' => $this->reserva->codigo,
                'valor_real' => $this->valor_real,
                'valor_descuento' => $this->valor_descuento,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
