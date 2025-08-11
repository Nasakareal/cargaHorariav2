<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" href="{{ asset('UTM.png') }}" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenidos | Sistema de Carga Horaria</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { height: 100%; font-family: Arial, sans-serif; }

        header {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 100;
        }
        header a.login-button {
            background: #004f39;
            color: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
        }
        header a.login-button:hover { background: #003d2d; }

        .welcome-container {
            position: relative;
            background: url('{{ asset('images/utm_background.jpg') }}') no-repeat center center/cover;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            color: white;
        }
        .overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
        }
        .content {
            position: relative;
            z-index: 2;
            max-width: 500px;
            padding: 20px;
        }
        .utm-logo {
            width: 480px;
            max-width: 100%;
            height: auto;
            margin-bottom: 15px;
        }
        .title { font-size: 1.8rem; font-weight: bold; margin-bottom: 8px; }
        .subtitle { font-size: 1.1rem; margin-bottom: 25px; }

        .system-card {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 15px;
        }
        .system-card img {
            width: 80px;
            height: auto;
            margin-bottom: 10px;
        }
        .system-card a {
            display: inline-block;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: bold;
            border-radius: 6px;
            text-transform: uppercase;
            background-color: #004f39;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease-in-out;
        }
        .system-card a:hover {
            transform: scale(1.05);
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <header>
        <a href="{{ route('login') }}" class="login-button">Login</a>
    </header>

    <div class="welcome-container">
        <div class="overlay"></div>
        <div class="content">
            <img src="{{ asset('logo_2025.png') }}" alt="UTM" class="utm-logo">
            <h1 class="title">Sistema de Carga Horaria</h1>
            <p class="subtitle">Universidad Tecnológica de Morelia</p>

            <div class="system-card">
                <img src="{{ asset('UTM.png') }}" alt="Carga Horaria">
                <a href="{{ route('login') }}">Ingresar al Sistema</a>
            </div>
        </div>
    </div>

    <script>
        console.log("Página Welcome de Carga Horaria cargada");
    </script>
</body>
</html>
