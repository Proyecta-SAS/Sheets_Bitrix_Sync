<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Sheets Bitrix Sync</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:linear-gradient(140deg,#eef5ff,#f8fafc 55%,#ecfdf5);color:#172033;font:15px/1.5 system-ui,-apple-system,Segoe UI,sans-serif;display:grid;min-height:100vh;place-items:center}.login{width:min(420px,calc(100% - 32px));background:#fff;border:1px solid #dce3ee;border-radius:20px;padding:32px;box-shadow:0 22px 60px rgba(25,48,80,.12)}.brand{display:flex;align-items:center;gap:12px;margin-bottom:28px}.logo{width:44px;height:44px;display:grid;place-items:center;background:#2563eb;color:#fff;border-radius:13px;font-weight:800}h1{font-size:22px;margin:0}.muted{color:#64748b;margin:2px 0 0}.field{margin:16px 0}label{display:block;font-weight:650;margin-bottom:7px}input{width:100%;padding:12px 13px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}input:focus{border-color:#2563eb;outline:3px solid #dbeafe}button{width:100%;margin-top:8px;padding:12px;border:0;border-radius:10px;background:#2563eb;color:#fff;font:inherit;font-weight:700;cursor:pointer}.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:9px;padding:10px 12px}
    </style>
</head>
<body>
<main class="login">
    <div class="brand"><div class="logo">SB</div><div><h1>Sheets → Bitrix24</h1><p class="muted">Panel de sincronización</p></div></div>
    <?php if ($error !== null): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(App\Http\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="field"><label for="user">Usuario</label><input id="user" name="user" autocomplete="username" required autofocus></div>
        <div class="field"><label for="password">Contraseña</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
        <button type="submit">Ingresar</button>
    </form>
</main>
</body>
</html>
