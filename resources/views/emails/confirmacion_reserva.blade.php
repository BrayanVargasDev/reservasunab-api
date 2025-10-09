<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Confirmación de reserva</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9fafb;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .header {
            background: #9e01e2;
            /* color morado de tu app */
            color: #fff;
            padding: 10px;
            text-align: center;
        }

        .content {
            padding: 20px;
        }

        .status {
            font-weight: bold;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 10px;
        }

        .status.success {
            background: #dcfce7;
            color: #166534;
        }

        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .details {
            margin-top: 20px;
            border-collapse: collapse;
            width: 100%;
        }

        .details td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .footer {
            text-align: center;
            padding: 15px;
            font-size: 12px;
            color: #6b7280;
            background: #f3f4f6;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>Confirmación de reserva</h2>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $usuario }}</strong>,</p>
            @php
            $mensajeEstado = '';
            switch($estado) {
            case 'pendienteap':
            $mensajeEstado = 'Tu reserva se encuentra pendiente de aprobación.';
            break;
            case 'inicial':
            $mensajeEstado = 'Tu reserva está pendiente de pago.';
            break;
            case 'aprobada':
            $mensajeEstado = 'Tu reserva ya ha sido aprobada.';
            break;
            case 'pagada':
            $mensajeEstado = 'Hemos recibido tu pago. La reserva está confirmada.';
            break;
            case 'confirmada':
            case 'completada':
            $mensajeEstado = 'Tu reserva ha sido confirmada.';
            break;
            default:
            $mensajeEstado = 'Estado actual de tu reserva: ' . ucfirst($estado);
            break;
            }
            @endphp
            <p>{{ $mensajeEstado }}</p>

            <table class="details">
                <tr>
                    <td><strong>Fecha:</strong></td>
                    <td>{{ $fecha }}</td>
                </tr>
                <tr>
                    <td><strong>Hora:</strong></td>
                    <td>{{ $hora_inicio }}@if(!empty($hora_fin)) - {{ $hora_fin }}@endif</td>
                </tr>
                <tr>
                    <td><strong>Valor:</strong></td>
                    <td>${{ number_format($valor_descuento, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td><strong>Espacio:</strong></td>
                    <td>{{ $espacio->nombre }}</td>
                </tr>
                <tr>
                    <td><strong>Código reserva:</strong></td>
                    <td>{{ $codigo }}</td>
                </tr>
            </table>

            @if(!empty($participantes))
            <h3 style="margin-top: 24px;">Participantes</h3>
            <table class="details" role="presentation" aria-label="Participantes de la reserva">
                <thead>
                    <tr>
                        <td><strong>Nombre</strong></td>
                        <td><strong>Documento</strong></td>
                        <td><strong>Email</strong></td>
                    </tr>
                </thead>
                <tbody>
                    @foreach($participantes as $p)
                    <tr>
                        <td>{{ $p['nombre'] ?? 'N/D' }}</td>
                        <td>{{ $p['documento'] ?? 'N/D' }}</td>
                        <td>{{ $p['email'] ?? 'N/D' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            @if(!empty($detalles_lista))
            <h3 style="margin-top: 24px;">Detalles de la reserva</h3>
            <table class="details" role="presentation" aria-label="Detalles de la reserva">
                <thead>
                    <tr>
                        <td><strong>Elemento</strong></td>
                        <td><strong>Cantidad</strong></td>
                        <td><strong>Precio unitario</strong></td>
                        <td><strong>Total</strong></td>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detalles_lista as $d)
                    <tr>
                        <td>{{ $d['nombre'] ?? 'Elemento' }}</td>
                        <td>{{ $d['cantidad'] ?? 0 }}</td>
                        <td>${{ number_format($d['precio_unitario'] ?? 0, 0, ',', '.') }}</td>
                        <td>${{ number_format(($d['total'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            <p style="margin-top: 20px;">
                Si tienes alguna duda, por favor contacta a nuestro equipo de soporte.
            </p>
        </div>
        <div class="footer">
            © {{ date('Y') }} UNAB - Todos los derechos reservados.
        </div>
    </div>
</body>

</html>
