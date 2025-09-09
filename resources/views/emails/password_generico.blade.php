@php($titulo = $esCreacion ? 'Acceso a la plataforma' : 'Nueva contraseña generada')
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
        }

        .password {
            font-size: 20px;
            font-weight: bold;
            background: #f5f5f5;
            padding: 10px 14px;
            border-radius: 6px;
            display: inline-block;
            letter-spacing: 1px;
        }

        a.btn {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 16px;
        }

        small {
            color: #555;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2 style="margin-top:0;">{{ $titulo }}</h2>
        <p>Hola {{ $nombre }},</p>

        @if($esCreacion)
        <p>Se ha creado una cuenta para ti en la plataforma con este correo: <strong>{{ $email }}</strong>.</p>
        <p>Tu contraseña temporal es:</p>
        @else
        <p>Se ha generado una nueva contraseña para tu cuenta (correo: <strong>{{ $email }}</strong>). Esto ocurre porque no tenías una contraseña establecida.</p>
        <p>Tu nueva contraseña es:</p>
        @endif

        <p class="password">{{ $password }}</p>

        <p>Por seguridad te recomendamos iniciar sesión y cambiarla lo antes posible.</p>

        <p>Si no solicitaste esta acción o crees que es un error, por favor contacta al soporte.</p>

        <p>Saludos,<br />Equipo de Soporte</p>
        <hr />
        <small>Este es un mensaje automático, por favor no respondas a este correo.</small>
    </div>
</body>

</html>
