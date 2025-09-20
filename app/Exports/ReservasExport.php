<?php

namespace App\Exports;

use App\Models\Reservas;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class ReservasExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    private $mes;
    private $anio;

    public function __construct($mes, $anio)
    {
        $this->mes = $mes;
        $this->anio = $anio;
    }

    public function query()
    {
        return Reservas::with([
            'usuarioReserva.persona',
            'espacio',
            'detalles.elemento'
        ])
        ->whereYear('fecha', $this->anio)
        ->whereMonth('fecha', $this->mes)
        ->orderBy('fecha')
        ->orderBy('hora_inicio');
    }

    public function headings(): array
    {
        return [
            'Código Reserva',
            'Fecha',
            'Hora Inicio',
            'Hora Fin',
            'Estado',
            'Usuario - Nombres',
            'Usuario - Apellidos',
            'Usuario - Email',
            'Usuario - Documento',
            'Usuario - Celular',
            'Espacio - Nombre',
            'Espacio - Código',
            'Precio Base',
            'Precio Espacio',
            'Precio Elementos',
            'Precio Total',
            'Elementos - Cantidad Total',
            'Elementos - Valor Total',
            'Elementos - Nombres'
        ];
    }

    public function map($reserva): array
    {
        // Información del usuario
        $usuario = $reserva->usuarioReserva;
        $persona = $usuario ? $usuario->persona : null;

        $nombres = $persona ? trim(($persona->primer_nombre ?? '') . ' ' . ($persona->segundo_nombre ?? '')) : '';
        $apellidos = $persona ? trim(($persona->primer_apellido ?? '') . ' ' . ($persona->segundo_apellido ?? '')) : '';
        $email = $usuario ? $usuario->email : '';
        $documento = $persona ? $persona->numero_documento : '';
        $celular = $persona ? $persona->celular : '';

        // Información del espacio
        $espacioNombre = $reserva->espacio ? $reserva->espacio->nombre : '';
        $espacioCodigo = $reserva->espacio ? $reserva->espacio->codigo : '';

        // Información de elementos
        $elementosCantidad = 0;
        $elementosValor = 0;
        $elementosNombres = [];

        if ($reserva->detalles) {
            foreach ($reserva->detalles as $detalle) {
                $elementosCantidad += $detalle->cantidad;
                $elementosValor += $detalle->cantidad * $detalle->valor_unitario;
                if ($detalle->elemento) {
                    $elementosNombres[] = $detalle->elemento->nombre;
                }
            }
        }

        $elementosNombresStr = implode(', ', $elementosNombres);

        return [
            $reserva->codigo,
            $reserva->fecha ? Carbon::parse($reserva->fecha)->format('d/m/Y') : '',
            $reserva->hora_inicio ? Carbon::parse($reserva->hora_inicio)->format('H:i') : '',
            $reserva->hora_fin ? Carbon::parse($reserva->hora_fin)->format('H:i') : '',
            $reserva->estado,
            $nombres,
            $apellidos,
            $email,
            $documento,
            $celular,
            $espacioNombre,
            $espacioCodigo,
            $reserva->precio_base,
            $reserva->precio_espacio,
            $reserva->precio_elementos,
            $reserva->precio_total,
            $elementosCantidad,
            $elementosValor,
            $elementosNombresStr
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F81BD']
                ]
            ]
        ];
    }
}
