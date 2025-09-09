<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReporteFallosReservaMensualidades extends Mailable
{
    use Queueable, SerializesModels;

    public array $reservas;
    public array $mensualidades;
    public string $fechaEjecucion;

    public function __construct(array $reservas, array $mensualidades)
    {
        $this->reservas = $reservas;
        $this->mensualidades = $mensualidades;
        $this->fechaEjecucion = now()->format('Y-m-d H:i:s');
    }

    public function build()
    {
        return $this->subject('Resumen fallos reporte (Reporte de reservas y mensualidades)')
            ->view('emails.reporte_fallos');
    }
}
