<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Ingresar · Sheets Bitrix Sync</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sheets-bitrix-sync.css">
    <script>document.documentElement.dataset.theme=localStorage.getItem('sbs-theme')||'light';</script>
</head>
<body>
<main class="auth-page">
    <section class="auth-visual" aria-label="Sheets Bitrix Sync">
        <div class="sai-brand">
            <div class="sai-brand-mark" aria-hidden="true">SB</div>
            <div class="sai-brand-copy"><span class="sai-brand-name">Sheets Bitrix Sync</span><span class="sai-brand-product">Integración inteligente</span></div>
        </div>
        <div class="auth-message">
            <p class="eyebrow">Integración centralizada</p>
            <h2>Tu cartera, conectada y bajo control.</h2>
            <p>Administra el flujo entre Google Sheets y Bitrix24 desde una experiencia clara, segura y confiable.</p>
        </div>
        <div class="auth-meta">Sincronización segura · Acceso administrativo</div>
    </section>
    <section class="auth-form-wrap">
        <div class="auth-card">
            <p class="eyebrow">Bienvenido de nuevo</p>
            <h1>Inicia sesión</h1>
            <p class="muted">Ingresa tus credenciales para acceder al centro de sincronización.</p>
            <?php if ($error !== null): ?><div class="error-box" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(App\Http\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field"><label for="user">Usuario</label><input id="user" name="user" autocomplete="username" placeholder="Tu usuario" required autofocus></div>
                <div class="field"><label for="password">Contraseña</label><input id="password" type="password" name="password" autocomplete="current-password" placeholder="Tu contraseña" required></div>
                <button class="btn gradient" type="submit">Ingresar al panel</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
