<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración requerida</title>
    <style>
        body{margin:0;background:#f4f7fb;color:#172033;font:16px/1.55 system-ui,-apple-system,Segoe UI,sans-serif;display:grid;min-height:100vh;place-items:center}.card{width:min(620px,calc(100% - 40px));background:white;border:1px solid #dce3ee;border-radius:18px;padding:32px;box-shadow:0 18px 50px rgba(31,50,81,.09)}h1{margin:0 0 12px;font-size:26px}code{display:block;background:#111827;color:#e5e7eb;padding:14px;border-radius:9px;overflow:auto}p{color:#5c677d}
    </style>
</head>
<body>
<main class="card">
    <h1>Falta proteger la administración</h1>
    <p>Configure <strong>ADMIN_USER</strong> y <strong>ADMIN_PASSWORD_HASH</strong> en <code>.env</code>. Genere el hash desde la raíz del proyecto:</p>
    <code>php bin/hash-password.php</code>
    <p>La aplicación no habilita un usuario o contraseña predeterminados.</p>
</main>
</body>
</html>
