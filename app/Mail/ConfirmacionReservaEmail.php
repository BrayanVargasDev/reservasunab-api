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
use Illuminate\Support\Facades\Log;

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
        $this->reserva->load([
            'espacio.sede',
            'usuarioReserva.persona',
            'jugadores.usuario.persona',
            'detalles.elemento',
        ]);

        $usuarioModel = $this->reserva->usuarioReserva;
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

        $participantes = [];
        if ($this->reserva->jugadores && $this->reserva->jugadores->count() > 0) {
            $participantes = $this->reserva->jugadores->map(function ($jugador) {
                $u = $jugador->usuario;
                $p = $u?->persona;
                $nombre = collect([
                    $p?->primer_nombre,
                    $p?->segundo_nombre,
                    $p?->primer_apellido,
                    $p?->segundo_apellido,
                ])->filter()->implode(' ');

                return [
                    'nombre' => $nombre ?: ($u?->email ?? 'Usuario'),
                    'documento' => $p?->numero_documento,
                    'email' => $u?->email,
                ];
            })->values()->all();
        }

        $tiposUsuario = $usuarioModel?->tipos_usuario ?? [];
        $prioridadTipos = ['estudiante', 'administrativo', 'egresado', 'externo'];
        $tipoPrecio = collect($prioridadTipos)
            ->first(fn($t) => in_array($t, $tiposUsuario)) ?? 'externo';

        $detalles_lista = [];
        if ($this->reserva->detalles && $this->reserva->detalles->count() > 0) {
            $detalles_lista = $this->reserva->detalles->map(function ($d) use ($tipoPrecio) {
                $elem = $d->elemento;
                $nombre = $elem?->nombre ?? 'Elemento';
                $cantidad = (int) ($d->cantidad ?? 0);

                $precioUnitario = 0.0;
                if ($elem) {
                    switch ($tipoPrecio) {
                        case 'estudiante':
                            $precioUnitario = (float) ($elem->valor_estudiante ?? 0);
                            break;
                        case 'administrativo':
                            $precioUnitario = (float) ($elem->valor_administrativo ?? 0);
                            break;
                        case 'egresado':
                            $precioUnitario = (float) ($elem->valor_egresado ?? 0);
                            break;
                        case 'externo':
                        default:
                            $precioUnitario = (float) ($elem->valor_externo ?? 0);
                            break;
                    }
                }

                $total = $precioUnitario * $cantidad;

                return [
                    'id_elemento' => $d->id_elemento,
                    'nombre' => $nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'total' => $total,
                ];
            })->values()->all();
        }

        return new Content(
            view: 'emails.confirmacion_reserva',
            with: [
                'usuario' => $nombreUsuario,
                'espacio' => $this->reserva->espacio,
                'fecha' => $this->reserva->fecha->format('d/m/Y'),
                'hora_inicio' => $this->reserva->hora_inicio->format('g:i A'),
                'hora_fin' => $this->reserva->hora_fin?->format('g:i A'),
                'estado' => $this->reserva->estado,
                'codigo' => $this->reserva->codigo,
                'valor_real' => $this->valor_real,
                'valor_descuento' => $this->valor_descuento,
                'participantes' => $participantes,
                'detalles_lista' => $detalles_lista,
                'tipo_precio' => $tipoPrecio,
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
