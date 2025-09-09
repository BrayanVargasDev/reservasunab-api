<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Reporte fallos Job 3</title>
</head>
<body>
    <h2>Reporte fallos Job 3</h2>
    <p>Fecha ejecución: {{ $fechaEjecucion }}</p>

    <h3>Reservas con 5 fallos</h3>
    @if(empty($reservas))
        <p>Sin reservas.</p>
    @else
        <table border="1" cellpadding="4" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Código</th>
                    <th>Fallos</th>
                    <th>Último error</th>
                </tr>
            </thead>
            <tbody>
            @foreach($reservas as $r)
                <tr>
                    <td>{{ $r['id'] }}</td>
                    <td>{{ $r['codigo'] }}</td>
                    <td>{{ $r['fallos_reporte'] }}</td>
                    <td>{{ $r['ultimo_error_reporte'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h3>Mensualidades con 5 fallos</h3>
    @if(empty($mensualidades))
        <p>Sin mensualidades.</p>
    @else
        <table border="1" cellpadding="4" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Espacio</th>
                    <th>Fallos</th>
                    <th>Último error</th>
                </tr>
            </thead>
            <tbody>
            @foreach($mensualidades as $m)
                <tr>
                    <td>{{ $m['id'] }}</td>
                    <td>{{ $m['id_usuario'] }}</td>
                    <td>{{ $m['id_espacio'] }}</td>
                    <td>{{ $m['fallos_reporte'] }}</td>
                    <td>{{ $m['ultimo_error_reporte'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
