<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use App\Models\Usuario;

class PasswordGenericoEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected Usuario $usuario,
        protected string $passwordPlano,
        protected bool $esCreacion = true,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: $this->esCreacion ? 'Tu acceso a la plataforma' : 'Se ha generado tu contraseña de acceso',
        );
    }

    public function content(): Content
    {
        $persona = $this->usuario->persona;
        $nombre = collect([
            $persona?->primer_nombre,
            $persona?->segundo_nombre,
            $persona?->primer_apellido,
            $persona?->segundo_apellido,
        ])->filter()->implode(' ');

        if (empty($nombre)) {
            $nombre = $this->usuario->email;
        }

        return new Content(
            view: 'emails.password_generico',
            with: [
                'nombre' => $nombre,
                'email' => $this->usuario->email,
                'password' => $this->passwordPlano,
                'esCreacion' => $this->esCreacion,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
