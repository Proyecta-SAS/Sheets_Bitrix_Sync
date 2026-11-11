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
    <script>document.documentElement.dataset.theme='light';</script>
</head>
<body class="login-body">
<main class="auth-page">
    <section class="auth-visual" aria-label="Sheets Bitrix Sync">
        <div class="auth-visual-content">
            <div class="auth-kicker">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2m-9 0h8a2 2 0 0 1 2 2v7H6v-7a2 2 0 0 1 2-2Z"/></svg>
                Acceso seguro <span></span>
            </div>
            <h1>Sincronización en<br>un solo entorno.</h1>
            <p class="auth-intro">Plataforma interna para consultar y administrar la integración entre Google Sheets y Bitrix24 con información centralizada.</p>

            <div class="auth-features">
                <article class="auth-feature">
                    <div class="auth-feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.7 2.9 8.2 7 10 4.1-1.8 7-5.3 7-10V6l-7-3Zm-3 8 2 2 4-4"/></svg></div>
                    <div><h2>Autenticación corporativa</h2><p>Sesiones protegidas para administrar tus sincronizaciones.</p></div>
                </article>
                <article class="auth-feature">
                    <div class="auth-feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8-1h4m-4 4h4m-8 6v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/></svg></div>
                    <div><h2>Conexiones centralizadas</h2><p>Google Sheets y Bitrix24 operan desde un mismo panel.</p></div>
                </article>
                <article class="auth-feature">
                    <div class="auth-feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h16M8 15l3-4 3 2 5-7"/></svg></div>
                    <div><h2>Información trazable</h2><p>Registros, estados y errores organizados en un solo lugar.</p></div>
                </article>
            </div>

            <div class="auth-meta">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2m-9 0h8a2 2 0 0 1 2 2v7H6v-7a2 2 0 0 1 2-2Z"/></svg>
                Conexión segura · Uso interno de Sheets Bitrix Sync
            </div>
        </div>
        <div class="auth-watermark" aria-hidden="true"><span>S</span><span>B</span></div>
    </section>
    <section class="auth-form-wrap">
        <div class="auth-card">
            <div class="auth-card-brand"><span>SB</span> Sheets Bitrix Sync</div>
            <h1>Bienvenido de vuelta</h1>
            <p class="muted">Ingresa con tu cuenta administrativa para continuar.</p>
            <?php if ($error !== null): ?><div class="error-box" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(App\Http\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field"><label for="user">Usuario corporativo</label><div class="auth-input"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4V5Zm1 1 7 6 7-6"/></svg><input id="user" name="user" autocomplete="username" placeholder="Tu usuario" required autofocus></div></div>
                <div class="field"><label for="password">Contraseña</label><div class="auth-input"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2m-9 0h8a2 2 0 0 1 2 2v7H6v-7a2 2 0 0 1 2-2Z"/></svg><input id="password" type="password" name="password" autocomplete="current-password" placeholder="Tu contraseña" required><button class="password-toggle" type="button" aria-label="Mostrar contraseña" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg></button></div></div>
                <button class="btn auth-submit" type="submit">Ingresar al panel <span>→</span></button>
            </form>
            <p class="auth-note">Acceso exclusivo para usuarios autorizados.</p>
        </div>
    </section>
</main>
<footer class="auth-footer"><span>© <?= date('Y') ?> Sheets Bitrix Sync</span><i></i><span>Desarrollado para <strong>Proyecta</strong></span></footer>
<script>
document.querySelector('[data-password-toggle]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    const password = document.getElementById('password');
    const visible = password.type === 'text';
    password.type = visible ? 'password' : 'text';
    button.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
});
</script>
</body>
</html>
