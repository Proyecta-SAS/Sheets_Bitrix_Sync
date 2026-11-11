<?php

declare(strict_types=1);

use App\Config\IntegrationConfig;

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$headers = array_values(array_unique(array_merge($config->detectedHeaders, array_keys($config->mapping))));
$availableSheets = (array) $app->settings->get('available_sheets', []);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Sheets Bitrix Sync</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sheets-bitrix-sync.css">
    <script>document.documentElement.dataset.theme=localStorage.getItem('sbs-theme')||'light';</script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sai-brand">
            <div class="sai-brand-mark" aria-hidden="true">SB</div>
            <div class="sai-brand-copy"><span class="sai-brand-name">Sheets Bitrix Sync</span><span class="sai-brand-product">Centro de integración</span></div>
        </div>
        <nav class="sidebar-nav" aria-label="Navegación principal">
            <span class="sidebar-label">Inicio</span>
            <a class="sidebar-link active" href="./">Resumen general</a>
            <a class="sidebar-link" href="#connections">Conexiones</a>
            <span class="sidebar-label">Administración</span>
            <a class="sidebar-link" href="#configuration">Configuración</a>
            <a class="sidebar-link" href="#records">Registros procesados</a>
            <a class="sidebar-link" href="#events">Eventos y errores</a>
            <span class="sidebar-label">Monitoreo</span>
            <a class="sidebar-link" href="portal">Portal de negociaciones</a>
        </nav>
        <div class="sidebar-foot">Google Sheets → Bitrix24<br>Versión <?= h(App\Application::VERSION) ?></div>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <div class="topbar-title"><strong>Dashboard general</strong></div>
            <div class="topbar-actions">
                <form method="post" action="sync"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn top-sync" type="submit">↻ Sincronizar</button></form>
                <button class="btn theme-toggle" type="button" aria-label="Cambiar tema" data-theme-toggle></button>
                <div class="user-chip"><span>A</span><div><strong>Administrador</strong><small>Acceso seguro</small></div></div>
                <form class="logout" method="post" action="logout"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn small" type="submit">Salir</button></form>
            </div>
        </header>

        <main class="page">
            <section class="page-heading">
                <div><p class="eyebrow">Vista general</p><h1>Panel de sincronización</h1><div class="muted">Google Sheets → Pipeline PRUEBA TECNOLOGIA</div></div>
                <div class="status <?= $config->active ? '' : 'off' ?>"><span class="dot"></span><?= $config->active ? 'Integración activa' : 'Integración inactiva' ?></div>
            </section>

            <?php foreach ($flashes as $item): ?><div class="flash <?= h($item['type']) ?>" role="status"><?= h($item['message']) ?></div><?php endforeach; ?>

            <div class="grid">
                <section class="metrics" aria-label="Resumen de sincronización">
                    <?php foreach (['CREADA' => ['Creadas', 'success'], 'PENDIENTE' => ['Pendientes', ''], 'PROCESANDO' => ['Procesando', ''], 'DUPLICADO' => ['Duplicadas', ''], 'ERROR' => ['Errores', 'alert']] as $key => [$label, $class]): ?>
                        <div class="metric <?= h($class) ?>"><strong><?= h($counts[$key] ?? 0) ?></strong><span><?= h($label) ?></span></div>
                    <?php endforeach; ?>
                </section>

                <section class="card half" id="connections">
                    <div class="card-head"><div><span class="card-kicker">Diagnóstico</span><h2>Conexiones</h2><div class="muted">Comprueba los accesos antes de activar la integración.</div></div></div>
                    <div class="actions">
                        <form method="post" action="test/google"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Probar Google Sheets</button></form>
                        <form method="post" action="test/bitrix"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Probar Bitrix24</button></form>
                        <form method="post" action="headers"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Leer encabezados</button></form>
                    </div>
                </section>

                <section class="card half">
                    <div class="card-head"><div><span class="card-kicker">Operación</span><h2>Ejecución</h2><div class="muted">Procesa hasta <?= h($config->batchSize) ?> filas pendientes por ciclo.</div></div></div>
                    <div class="actions">
                        <form method="post" action="sync"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn gradient" type="submit">Sincronizar ahora</button></form>
                        <form method="post" action="retry-all"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Reintentar errores</button></form>
                    </div>
                </section>

                <section class="card" id="configuration">
                    <div class="card-head"><div><span class="card-kicker">Parámetros</span><h2>Configuración de la integración</h2><div class="muted">Los secretos se administran exclusivamente en <span class="code">.env</span>.</div></div></div>
                    <form method="post" action="settings">
                        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                        <div class="form-grid">
                            <div class="field full"><label class="switch"><input type="checkbox" name="active" value="1" <?= $config->active ? 'checked' : '' ?>> Activar integración para cron y webhook</label></div>
                            <div class="field full"><label for="spreadsheet_id">ID del documento</label><input id="spreadsheet_id" type="text" name="spreadsheet_id" value="<?= h($config->spreadsheetId) ?>" required></div>
                            <div class="field"><label for="sheet_name">Hoja</label><input id="sheet_name" type="text" name="sheet_name" value="<?= h($config->sheetName) ?>" list="sheet-list" required><datalist id="sheet-list"><?php foreach ($availableSheets as $sheet): ?><option value="<?= h($sheet) ?>"><?php endforeach; ?></datalist></div>
                            <div class="field"><label for="header_row">Fila de encabezados</label><input id="header_row" type="number" name="header_row" min="1" value="<?= h($config->headerRow) ?>" required></div>
                            <div class="field"><label for="category_id">CATEGORY_ID</label><input id="category_id" type="text" name="category_id" value="<?= h($config->categoryId) ?>" required><small>Pipeline inicial: PRUEBA TECNOLOGIA (216)</small></div>
                            <div class="field"><label for="stage_id">STAGE_ID predeterminado</label><input id="stage_id" type="text" name="stage_id" value="<?= h($config->stageId) ?>" required></div>
                            <div class="field"><label for="assigned_by_id">Responsable (ID)</label><input id="assigned_by_id" type="text" name="assigned_by_id" value="<?= h($config->assignedById) ?>"></div>
                            <div class="field"><label for="batch_size">Filas por ejecución</label><input id="batch_size" type="number" name="batch_size" min="1" max="100" value="<?= h($config->batchSize) ?>"></div>
                        </div>

                        <div class="card-head" style="margin-top:30px"><div><span class="card-kicker">Correspondencia</span><h2>Mapeo de campos</h2><div class="muted">Deja vacíos los campos que no deseas enviar. Registra los códigos reales <span class="code">UF_CRM_...</span>.</div></div></div>
                        <div class="table-wrap"><table class="mapping"><thead><tr><th>Columna del Sheet</th><th class="arrow"></th><th>Campo de Bitrix24</th></tr></thead><tbody>
                            <?php foreach ($headers as $header): ?>
                                <tr><td><input type="hidden" name="mapping_column[]" value="<?= h($header) ?>"><strong><?= h($header) ?></strong></td><td class="arrow">→</td><td><input type="text" name="mapping_field[]" value="<?= h($config->mapping[$header] ?? '') ?>" list="bitrix-fields" placeholder="UF_CRM_..."></td></tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                        <datalist id="bitrix-fields"><?php foreach ($knownFields as $field): ?><option value="<?= h($field) ?>"><?php endforeach; ?></datalist>
                        <div class="actions" style="margin-top:18px"><button class="btn primary" type="submit">Guardar configuración</button></div>
                    </form>
                </section>

                <section class="card" id="records">
                    <div class="card-head"><div><span class="card-kicker">Actividad</span><h2>Registros procesados</h2><div class="muted">Últimos 100 intentos persistidos localmente.</div></div></div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Fila</th><th>Estado</th><th>ID Bitrix</th><th>Identificador</th><th>Intentos</th><th>Actualizado</th><th>Error / acción</th></tr></thead><tbody>
                        <?php if ($records === []): ?><tr><td colspan="7" class="empty">Todavía no hay registros.</td></tr><?php endif; ?>
                        <?php foreach ($records as $record): ?><tr>
                            <td><?= h($record['sheet_name']) ?> #<?= h($record['row_number']) ?></td><td><span class="badge <?= h($record['status']) ?>"><?= h($record['status']) ?></span></td><td><?= h($record['deal_id'] ?: '—') ?></td><td class="code"><?= h($record['unique_identifier']) ?></td><td><?= h($record['attempts']) ?></td><td><?= h($record['updated_at']) ?></td><td><?php if ($record['last_error']): ?><div class="log-error"><?= h($record['last_error']) ?></div><?php endif; ?><?php if ($record['status'] === 'ERROR'): ?><form method="post" action="retry" style="margin-top:6px"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= h($record['id']) ?>"><button class="btn small" type="submit">Reintentar</button></form><?php endif; ?></td>
                        </tr><?php endforeach; ?>
                    </tbody></table></div>
                </section>

                <section class="card" id="events">
                    <div class="card-head"><div><span class="card-kicker">Auditoría</span><h2>Eventos y errores</h2><div class="muted">Últimos 50 eventos, sin secretos.</div></div></div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha UTC</th><th>Nivel</th><th>Evento</th><th>Mensaje</th></tr></thead><tbody>
                        <?php if ($logs === []): ?><tr><td colspan="4" class="empty">Sin eventos registrados.</td></tr><?php endif; ?>
                        <?php foreach ($logs as $log): ?><tr><td><?= h($log['created_at']) ?></td><td><span class="badge <?= $log['level'] === 'error' ? 'ERROR' : '' ?>"><?= h(strtoupper($log['level'])) ?></span></td><td class="code"><?= h($log['event']) ?></td><td><?= h($log['message']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </section>

                <section class="card card-help">
                    <span class="card-kicker">Guía rápida</span><h2>Orden recomendado</h2>
                    <ol><li>Completa <span class="code">.env</span> y comparte el Sheet con el correo de la cuenta de servicio.</li><li>Guarda el ID y la hoja, prueba Google y lee los encabezados.</li><li>Prueba Bitrix, completa los códigos <span class="code">UF_CRM_...</span> reales y guarda.</li><li>Ejecuta una sincronización manual; cuando valides el resultado, activa la integración y el cron.</li></ol>
                </section>
            </div>
        </main>
    </div>
</div>
<script>
document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('sbs-theme', theme);
});
</script>
</body>
</html>
