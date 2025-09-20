<?php

namespace App\Exports;

use App\Models\PagoConsulta;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class PagosExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
        return PagoConsulta::whereYear('fecha_banco', $this->anio)
            ->whereMonth('fecha_banco', $this->mes)
            ->orderBy('fecha_banco', 'desc')
            ->orderBy('hora_inicio', 'desc');
    }

    public function headings(): array
    {
        return [
            'Código Pago',
            'Valor Real',
            'Valor Transacción',
            'Estado',
            'Ticket ID',
            'Código Traza',
            'Medio Pago',
            'Nombre Medio Pago',
            'Tarjeta Oculta',
            'Últimos 4 Dígitos',
            'Fecha Banco',
            'Moneda',
            'Tipo Concepto',
            'ID Concepto',
            'Hora Inicio',
            'Hora Fin',
            'Fecha Reserva',
            'Código Reserva',
            'ID Usuario Reserva',
            'Tipo Doc Usuario Reserva',
            'Doc Usuario Reserva',
            'Email Usuario Reserva',
            'Celular Usuario Reserva',
            'ID Espacio',
            'Nombre Espacio',
            'Tipo Doc Titular',
            'Número Doc Titular',
            'Nombre Titular',
            'Email Titular',
            'Celular Titular',
            'Descripción Pago'
        ];
    }

    public function map($pago): array
    {
        return [
            $pago->codigo,
            $pago->valor_real,
            $pago->valor_transaccion,
            $pago->estado,
            $pago->ticket_id,
            $pago->codigo_traza,
            $pago->medio_pago,
            $pago->nombre_medio_pago,
            $pago->tarjeta_oculta,
            $pago->ultimos_cuatro,
            $pago->fecha_banco ? Carbon::parse($pago->fecha_banco)->format('d/m/Y H:i:s') : '',
            $pago->moneda,
            $pago->tipo_concepto,
            $pago->id_concepto,
            $pago->hora_inicio ? Carbon::parse($pago->hora_inicio)->format('H:i:s') : '',
            $pago->hora_fin ? Carbon::parse($pago->hora_fin)->format('H:i:s') : '',
            $pago->fecha_reserva ? Carbon::parse($pago->fecha_reserva)->format('d/m/Y') : '',
            $pago->codigo_reserva,
            $pago->id_usuario_reserva,
            $pago->tipo_doc_usuario_reserva,
            $pago->doc_usuario_reserva,
            $pago->email_usuario_reserva,
            $pago->celular_usuario_reserva,
            $pago->id_espacio,
            $pago->nombre_espacio,
            $pago->tipo_doc_titular,
            $pago->numero_doc_titular,
            $pago->nombre_titular,
            $pago->email_titular,
            $pago->celular_titular,
            $pago->descripcion_pago
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
