<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración requerida · Sheets Bitrix Sync</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sheets-bitrix-sync.css?v=<?= rawurlencode(App\Application::VERSION) ?>">
</head>
<body>
<main class="setup-page">
    <section class="setup-card">
        <div class="sai-brand">
            <div class="sai-brand-mark" aria-hidden="true">SB</div>
            <div class="sai-brand-copy"><span class="sai-brand-name">Sheets Bitrix Sync</span><span class="sai-brand-product">Configuración inicial</span></div>
        </div>
        <p class="eyebrow">Acción requerida</p>
        <h1>Protege la administración</h1>
        <p class="muted">Configura <strong>ADMIN_USER</strong> y <strong>ADMIN_PASSWORD_HASH</strong> en el archivo <span class="code">.env</span>. Genera el hash desde la raíz del proyecto:</p>
        <code>php bin/hash-password.php</code>
        <p class="muted">Por seguridad, Sheets Bitrix Sync no habilita credenciales predeterminadas.</p>
    </section>
</main>
</body>
</html>
