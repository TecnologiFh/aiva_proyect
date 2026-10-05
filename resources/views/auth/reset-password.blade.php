<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIVA Commerce — Nueva Contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-dark: #0f172a; --card-bg: rgba(30, 41, 59, 0.85); --accent: #6366f1; --text-main: #f8fafc; --text-muted: #94a3b8; --border-color: rgba(255, 255, 255, 0.1); }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; color: var(--text-main); }
        .card { background: var(--card-bg); backdrop-filter: blur(16px); border: 1px solid var(--border-color); border-radius: 16px; width: 100%; max-width: 420px; padding: 40px; }
        .brand { text-align: center; margin-bottom: 24px; }
        .brand h1 { font-size: 24px; font-weight: 700; color: #818cf8; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; margin-bottom: 8px; color: var(--text-muted); }
        .form-group input { width: 100%; padding: 12px 16px; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color); border-radius: 8px; color: white; outline: none; }
        .btn-submit { width: 100%; padding: 12px; background: var(--accent); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <h1>Nueva Contraseña</h1>
        </div>

        <form action="{{ url('/reset-password') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" value="{{ $email ?? old('email') }}" required readonly>
            </div>

            <div class="form-group">
                <label for="password">Nueva Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirmar Contraseña</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-submit">Guardar Contraseña</button>
        </form>
    </div>
</body>
</html>
