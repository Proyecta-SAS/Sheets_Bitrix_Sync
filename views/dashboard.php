<?php

declare(strict_types=1);

use App\Config\IntegrationConfig;

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function uiIcon(string $name): string
{
    $icons = [
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'home' => '<path d="m3 11 9-8 9 8M5 10v10h14V10M9 20v-6h6v6"/>',
        'chart' => '<path d="M4 19V9M10 19V5M16 19v-8M22 19H2"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'sliders' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"/>',
        'table' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 9v11"/>',
        'alert' => '<path d="M10.3 3.7 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0ZM12 9v4M12 17h.01"/>',
        'refresh' => '<path d="M21 12a9 9 0 1 1-2.64-6.36L21 8"/><path d="M21 3v5h-5"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'chevron' => '<path d="m8 10 4 4 4-4"/>',
        'user' => '<circle cx="12" cy="8" r="3"/><path d="M5 21a7 7 0 0 1 14 0"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M20.5 14.5A8 8 0 0 1 9.5 3.5 8.5 8.5 0 1 0 20.5 14.5Z"/>',
    ];

    return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
}

$headers = array_values(array_unique(array_merge($config->detectedHeaders, array_keys($config->mapping))));
$availableSheets = (array) $app->settings->get('available_sheets', []);
$adminUser = App\Support\Env::get('ADMIN_USER', 'admin');
$adminEmail = str_contains($adminUser, '@') ? $adminUser : $adminUser . '@avanzarsoluciones.com';
$totalRecords = array_sum(array_map('intval', $counts));
$errorRecords = (int) ($counts['ERROR'] ?? 0);
$assetVersion = App\Application::VERSION . '-' . (string) (@filemtime(__DIR__ . '/../public/assets/css/sheets-bitrix-sync.css') ?: 0);
$metricCards = [
    'CREADA' => ['Creadas', 'Registros enviados correctamente', 'success'],
    'PENDIENTE' => ['Pendientes', 'Filas esperando sincronización', ''],
    'PROCESANDO' => ['Procesando', 'Operaciones actualmente en curso', 'cyan'],
    'DUPLICADO' => ['Duplicadas', 'Registros detectados previamente', 'warning'],
    'ERROR' => ['Errores', 'Registros que requieren revisión', 'alert'],
];
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
    <link rel="stylesheet" href="assets/css/sheets-bitrix-sync.css?v=<?= rawurlencode($assetVersion) ?>">
    <script>document.documentElement.dataset.theme=localStorage.getItem('sbs-theme')||'light';</script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand-row">
            <div class="sai-brand"><img class="avanzar-logo" src="assets/img/avanzar-logo.svg?v=<?= rawurlencode(App\Application::VERSION) ?>" alt="Avanzar"></div>
            <button class="sidebar-collapse" type="button" aria-label="Contraer menú" aria-expanded="true" data-sidebar-toggle><?= uiIcon('menu') ?></button>
        </div>
        <div class="sidebar-scroll">
            <nav class="sidebar-nav" aria-label="Navegación principal">
                <span class="sidebar-label">Inicio</span>
                <a class="sidebar-link active" href="#home" data-view-link="home"><span class="nav-icon"><?= uiIcon('home') ?></span>Inicio</a>
                <a class="sidebar-link" href="#sync-report" data-view-link="sync-report"><span class="nav-icon"><?= uiIcon('chart') ?></span>Informe de sincronización</a>
                <a class="sidebar-link" href="#connections" data-view-link="connections"><span class="nav-icon"><?= uiIcon('link') ?></span>Conexiones</a>

                <span class="sidebar-label with-rule">Operación</span>
                <a class="sidebar-link" href="#configuration" data-view-link="configuration"><span class="nav-icon"><?= uiIcon('settings') ?></span>Configuración</a>
                <a class="sidebar-link" href="#field-mapping" data-view-link="field-mapping"><span class="nav-icon"><?= uiIcon('sliders') ?></span>Mapeo de campos</a>
                <a class="sidebar-link" href="#records" data-view-link="records"><span class="nav-icon"><?= uiIcon('table') ?></span>Registros procesados</a>
                <a class="sidebar-link" href="#events" data-view-link="events"><span class="nav-icon"><?= uiIcon('alert') ?></span>Eventos y errores</a>
                <a class="sidebar-link" href="#execution" data-view-link="execution"><span class="nav-icon"><?= uiIcon('refresh') ?></span>Ejecución manual</a>

                <span class="sidebar-label with-rule">Monitoreo</span>
                <a class="sidebar-link" href="portal"><span class="nav-icon"><?= uiIcon('target') ?></span>Portal de negociaciones</a>
                <a class="sidebar-link" href="#history" data-view-link="history"><span class="nav-icon"><?= uiIcon('clock') ?></span>Historial</a>
            </nav>
        </div>
        <a class="sidebar-settings" href="#configuration" data-view-link="configuration"><span class="nav-icon"><?= uiIcon('settings') ?></span>Configuración</a>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <div class="topbar-title"><strong>Dashboard general</strong></div>
            <div class="topbar-actions">
                <label class="top-search"><?= uiIcon('search') ?><input type="search" placeholder="Buscar registro..." data-record-search aria-label="Buscar registro"></label>
                <form method="post" action="sync"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn top-sync" type="submit"><?= uiIcon('refresh') ?>Sincronizar</button></form>
                <button class="btn theme-toggle" type="button" aria-label="Cambiar tema" data-theme-toggle><span class="theme-sun"><?= uiIcon('sun') ?></span><span class="theme-moon"><?= uiIcon('moon') ?></span></button>
                <div class="user-menu" data-user-menu>
                    <button class="user-chip" type="button" aria-expanded="false" aria-haspopup="menu" data-user-toggle><span>S</span><div><strong>Superadmin</strong><small>Super Administrador</small></div><b><?= uiIcon('chevron') ?></b></button>
                    <div class="user-dropdown" role="menu" data-user-dropdown hidden>
                        <div class="user-dropdown-head"><strong>Superadmin DEV</strong><small><?= h($adminEmail) ?></small></div>
                        <a href="#configuration" role="menuitem" data-view-link="configuration"><span><?= uiIcon('user') ?></span>Perfil</a>
                        <form method="post" action="logout"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button type="submit" role="menuitem"><span><?= uiIcon('logout') ?></span>Cerrar sesión</button></form>
                    </div>
                </div>
            </div>
        </header>

        <main class="page">
            <section class="page-heading dashboard-heading" data-view-panel="home">
                <div><h1>Dashboard general</h1><div class="muted">Vista global para revisar el estado de la integración, los registros procesados y los focos de gestión.</div></div>
                <div class="heading-total"><span>Total de registros</span><strong><?= h($totalRecords) ?></strong><b><?= h($errorRecords) ?> con error</b><small><?= $config->active ? 'Integración activa' : 'Integración inactiva' ?></small></div>
            </section>

            <?php foreach ($flashes as $item): ?><div class="flash <?= h($item['type']) ?>" role="status"><?= h($item['message']) ?></div><?php endforeach; ?>

            <div class="grid">
                <section class="status-tabs" aria-label="Filtros por estado" data-view-panel="home">
                    <button class="status-tab active" type="button" data-status-filter="ALL" aria-pressed="true">Todos los estados</button>
                    <button class="status-tab" type="button" data-status-filter="CREADA" aria-pressed="false">Creadas</button>
                    <button class="status-tab" type="button" data-status-filter="PENDIENTE" aria-pressed="false">Pendientes</button>
                    <button class="status-tab" type="button" data-status-filter="PROCESANDO" aria-pressed="false">Procesando</button>
                    <button class="status-tab" type="button" data-status-filter="DUPLICADO" aria-pressed="false">Duplicadas</button>
                    <button class="status-tab" type="button" data-status-filter="ERROR" aria-pressed="false">Errores</button>
                </section>

                <section class="dashboard-toolbar" data-view-panel="home">
                    <div class="toolbar-title"><?= uiIcon('clock') ?><b>Filtros de registros</b></div>
                    <label><span>Fila</span><input type="text" inputmode="numeric" placeholder="Número" data-filter-row></label>
                    <label><span>Hoja</span><input type="text" value="<?= h($config->sheetName) ?>" readonly></label>
                    <label><span>Desde</span><input type="text" placeholder="dd/mm/aaaa" data-filter-from></label>
                    <label><span>Hasta</span><input type="text" value="<?= h(date('d/m/Y')) ?>" data-filter-to></label>
                    <button class="btn gradient toolbar-apply" type="button"><?= uiIcon('search') ?>Aplicar</button>
                    <button class="btn toolbar-clear" type="button">Limpiar</button>
                    <form method="post" action="sync"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn toolbar-sync" type="submit"><?= uiIcon('refresh') ?>Sincronizar todo</button></form>
                </section>

                <div class="dashboard-notice" data-view-panel="home"><?= uiIcon('clock') ?> El tablero muestra <strong data-filter-count><?= h($totalRecords) ?> registros</strong><span data-filter-context>en todos los estados</span> de <?= h($config->sheetName) ?> hacia Bitrix24.</div>

                <section class="metrics" aria-label="Resumen de sincronización" data-view-panel="home">
                    <?php foreach ($metricCards as $key => [$label, $description, $class]): ?>
                        <div class="metric <?= h($class) ?>" data-metric-status="<?= h($key) ?>">
                            <button class="metric-menu" type="button" aria-label="Opciones de <?= h(strtolower($label)) ?>" aria-expanded="false" data-metric-menu-toggle>⋮</button>
                            <div class="metric-dropdown" data-metric-dropdown hidden>
                                <button type="button" data-download-status="<?= h($key) ?>">Descargar CSV</button>
                            </div>
                            <span class="metric-label"><?= h($label) ?></span><strong><?= h($counts[$key] ?? 0) ?></strong><p><?= h($description) ?></p><?php if ($key === 'CREADA'): ?><small><?= h($totalRecords) ?> registros totales</small><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>

                <section class="card overview-table" id="overview-table" data-view-panel="home sync-report">
                    <div class="overview-title"><h2 data-activity-title>Actividad reciente de sincronización</h2><span>⋮</span></div>
                    <div class="table-wrap"><table class="data-table dashboard-table"><thead><tr><th>Fila</th><th>Estado</th><th>Hoja</th><th>ID Bitrix</th><th>Identificador</th><th>Intentos</th><th>Actualizado</th></tr></thead><tbody>
                        <?php if ($records === []): ?><tr data-no-records><td colspan="7" class="empty">Todavía no hay registros procesados.</td></tr><?php endif; ?>
                        <?php foreach ($records as $record): ?><tr data-record-row data-status="<?= h($record['status']) ?>" data-row-number="<?= h($record['row_number']) ?>" data-sheet="<?= h($record['sheet_name']) ?>" data-updated="<?= h($record['updated_at']) ?>"><td><strong>#<?= h($record['row_number']) ?></strong></td><td><span class="state-dot <?= h(strtolower((string) $record['status'])) ?>"></span><span class="badge <?= h($record['status']) ?>"><?= h($record['status']) ?></span></td><td><?= h($record['sheet_name']) ?></td><td><?= h($record['deal_id'] ?: '—') ?></td><td class="code"><?= h($record['unique_identifier']) ?></td><td><?= h($record['attempts']) ?></td><td><?= h($record['updated_at']) ?></td></tr><?php endforeach; ?>
                        <?php if ($records !== []): ?><tr data-filter-empty hidden><td colspan="7" class="empty">No hay registros que coincidan con los filtros.</td></tr><?php endif; ?>
                    </tbody></table></div>
                </section>

                <section class="card half" id="connections" data-view-panel="connections">
                    <div class="card-head"><div><span class="card-kicker">Diagnóstico</span><h2>Conexiones</h2><div class="muted">Comprueba los accesos antes de activar la integración.</div></div></div>
                    <div class="actions">
                        <form method="post" action="test/google"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Probar Google Sheets</button></form>
                        <form method="post" action="test/bitrix"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Probar Bitrix24</button></form>
                        <form method="post" action="headers"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Leer encabezados</button></form>
                    </div>
                </section>

                <section class="card half" id="execution" data-view-panel="execution">
                    <div class="card-head"><div><span class="card-kicker">Operación</span><h2>Ejecución</h2><div class="muted">Procesa hasta <?= h($config->batchSize) ?> filas pendientes por ciclo.</div></div></div>
                    <div class="actions">
                        <form method="post" action="sync"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn gradient" type="submit">Sincronizar ahora</button></form>
                        <form method="post" action="retry-all"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Reintentar errores</button></form>
                    </div>
                </section>

                <section class="card" id="configuration" data-view-panel="configuration field-mapping">
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

                        <div class="card-head" id="field-mapping" style="margin-top:30px"><div><span class="card-kicker">Correspondencia</span><h2>Mapeo de campos</h2><div class="muted">Deja vacíos los campos que no deseas enviar. Registra los códigos reales <span class="code">UF_CRM_...</span>.</div></div></div>
                        <div class="table-wrap"><table class="mapping"><thead><tr><th>Columna del Sheet</th><th class="arrow"></th><th>Campo de Bitrix24</th></tr></thead><tbody>
                            <?php foreach ($headers as $header): ?>
                                <tr><td><input type="hidden" name="mapping_column[]" value="<?= h($header) ?>"><strong><?= h($header) ?></strong></td><td class="arrow">→</td><td><input type="text" name="mapping_field[]" value="<?= h($config->mapping[$header] ?? '') ?>" list="bitrix-fields" placeholder="UF_CRM_..."></td></tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                        <datalist id="bitrix-fields"><?php foreach ($knownFields as $field): ?><option value="<?= h($field) ?>"><?php endforeach; ?></datalist>
                        <div class="actions" style="margin-top:18px"><button class="btn primary" type="submit">Guardar configuración</button></div>
                    </form>
                </section>

                <section class="card" id="records" data-view-panel="records history">
                    <div class="card-head"><div><span class="card-kicker">Actividad</span><h2>Registros procesados</h2><div class="muted">Últimos 100 intentos persistidos localmente.</div></div></div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Fila</th><th>Estado</th><th>ID Bitrix</th><th>Identificador</th><th>Intentos</th><th>Actualizado</th><th>Error / acción</th></tr></thead><tbody>
                        <?php if ($records === []): ?><tr><td colspan="7" class="empty">Todavía no hay registros.</td></tr><?php endif; ?>
                        <?php foreach ($records as $record): ?><tr>
                            <td><?= h($record['sheet_name']) ?> #<?= h($record['row_number']) ?></td><td><span class="badge <?= h($record['status']) ?>"><?= h($record['status']) ?></span></td><td><?= h($record['deal_id'] ?: '—') ?></td><td class="code"><?= h($record['unique_identifier']) ?></td><td><?= h($record['attempts']) ?></td><td><?= h($record['updated_at']) ?></td><td><?php if ($record['last_error']): ?><div class="log-error"><?= h($record['last_error']) ?></div><?php endif; ?><?php if ($record['status'] === 'ERROR'): ?><form method="post" action="retry" style="margin-top:6px"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= h($record['id']) ?>"><button class="btn small" type="submit">Reintentar</button></form><?php endif; ?></td>
                        </tr><?php endforeach; ?>
                    </tbody></table></div>
                </section>

                <section class="card" id="events" data-view-panel="events">
                    <div class="card-head"><div><span class="card-kicker">Auditoría</span><h2>Eventos y errores</h2><div class="muted">Últimos 50 eventos, sin secretos.</div></div></div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha UTC</th><th>Nivel</th><th>Evento</th><th>Mensaje</th></tr></thead><tbody>
                        <?php if ($logs === []): ?><tr><td colspan="4" class="empty">Sin eventos registrados.</td></tr><?php endif; ?>
                        <?php foreach ($logs as $log): ?><tr><td><?= h($log['created_at']) ?></td><td><span class="badge <?= $log['level'] === 'error' ? 'ERROR' : '' ?>"><?= h(strtoupper($log['level'])) ?></span></td><td class="code"><?= h($log['event']) ?></td><td><?= h($log['message']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </section>

                <section class="card card-help" data-view-panel="home">
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
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const setSidebarCollapsed = (collapsed) => {
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    sidebarToggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    sidebarToggle?.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Contraer menú');
    localStorage.setItem('sbs-sidebar-collapsed', collapsed ? '1' : '0');
};
setSidebarCollapsed(localStorage.getItem('sbs-sidebar-collapsed') === '1');
sidebarToggle?.addEventListener('click', () => {
    setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
});

const viewLinks = [...document.querySelectorAll('[data-view-link]')];
const viewPanels = [...document.querySelectorAll('[data-view-panel]')];
const topbarTitle = document.querySelector('.topbar-title strong');
const viewTitles = {
    home: 'Dashboard general',
    'sync-report': 'Informe de sincronización',
    connections: 'Conexiones',
    configuration: 'Configuración',
    'field-mapping': 'Mapeo de campos',
    records: 'Registros procesados',
    events: 'Eventos y errores',
    execution: 'Ejecución manual',
    history: 'Historial',
};
const legacyViews = {
    'overview-table': 'sync-report',
};
const hasView = (view) => viewPanels.some((panel) => panel.dataset.viewPanel.split(' ').includes(view));
const setCurrentView = (requestedView, updateHistory = true) => {
    const normalizedView = legacyViews[requestedView] || requestedView;
    const view = hasView(normalizedView) ? normalizedView : 'home';

    document.body.dataset.currentView = view;
    viewPanels.forEach((panel) => {
        panel.hidden = !panel.dataset.viewPanel.split(' ').includes(view);
    });
    viewLinks.forEach((link) => {
        const active = link.dataset.viewLink === view;
        if (link.classList.contains('sidebar-link')) link.classList.toggle('active', active);
        link.toggleAttribute('aria-current', active);
    });
    if (topbarTitle) topbarTitle.textContent = viewTitles[view] || viewTitles.home;

    if (updateHistory) {
        const nextUrl = view === 'home' ? `${location.pathname}${location.search}` : `#${view}`;
        history.pushState({ view }, '', nextUrl);
    }
    window.scrollTo({ top: 0, behavior: 'auto' });
};

viewLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
        event.preventDefault();
        setCurrentView(link.dataset.viewLink);
    });
});

const viewFromLocation = () => location.hash.replace(/^#/, '') || 'home';
setCurrentView(viewFromLocation(), false);
window.addEventListener('popstate', () => setCurrentView(viewFromLocation(), false));

const recordRows = [...document.querySelectorAll('[data-record-row]')];
const filterEmptyRow = document.querySelector('[data-filter-empty]');
const statusFilterButtons = [...document.querySelectorAll('[data-status-filter]')];
const rowFilterInput = document.querySelector('[data-filter-row]');
const fromFilterInput = document.querySelector('[data-filter-from]');
const toFilterInput = document.querySelector('[data-filter-to]');
const recordSearchInput = document.querySelector('[data-record-search]');
const dashboardNoticeCount = document.querySelector('[data-filter-count]');
const dashboardFilterContext = document.querySelector('[data-filter-context]');
const activityTitle = document.querySelector('[data-activity-title]');
const noRecordsRow = document.querySelector('[data-no-records]');
const metricCardsElements = [...document.querySelectorAll('[data-metric-status]')];
const recordFilters = { status: 'ALL', row: '', from: '', to: '', search: '' };
const statusLabels = {
    ALL: 'todos los estados',
    CREADA: 'creadas',
    PENDIENTE: 'pendientes',
    PROCESANDO: 'procesando',
    DUPLICADO: 'duplicadas',
    ERROR: 'errores',
};

const parseDashboardDate = (value) => {
    const normalized = String(value || '').trim();
    if (!normalized) return null;
    const latinDate = normalized.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (latinDate) return new Date(Number(latinDate[3]), Number(latinDate[2]) - 1, Number(latinDate[1]));
    const isoDate = normalized.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (isoDate) return new Date(Number(isoDate[1]), Number(isoDate[2]) - 1, Number(isoDate[3]));
    return null;
};

const recordMatchesFilters = (row, forcedStatus = recordFilters.status) => {
    if (forcedStatus !== 'ALL' && row.dataset.status !== forcedStatus) return false;
    if (recordFilters.row && row.dataset.rowNumber !== recordFilters.row.replace(/^#/, '')) return false;
    if (recordFilters.search && !row.innerText.toLocaleLowerCase('es').includes(recordFilters.search)) return false;

    const updated = parseDashboardDate(row.dataset.updated);
    const from = parseDashboardDate(recordFilters.from);
    const to = parseDashboardDate(recordFilters.to);
    if (from && updated && updated < from) return false;
    if (to && updated && updated > to) return false;
    return true;
};

const applyRecordFilters = () => {
    let matchCount = 0;
    let displayedCount = 0;
    recordRows.forEach((row) => {
        const matches = recordMatchesFilters(row);
        row.hidden = !matches || displayedCount >= 8;
        if (matches) {
            matchCount += 1;
            displayedCount += 1;
        }
    });
    if (filterEmptyRow) filterEmptyRow.hidden = matchCount !== 0 || recordRows.length === 0;
    if (noRecordsRow) {
        noRecordsRow.hidden = recordRows.length !== 0;
        const cell = noRecordsRow.querySelector('td');
        if (cell) cell.textContent = recordFilters.status === 'ALL'
            ? 'Todavía no hay registros procesados en el entorno local.'
            : `No hay registros ${statusLabels[recordFilters.status]} en el entorno local.`;
    }
    if (dashboardNoticeCount) dashboardNoticeCount.textContent = `${matchCount} ${matchCount === 1 ? 'registro' : 'registros'}`;
    if (dashboardFilterContext) dashboardFilterContext.textContent = `en ${statusLabels[recordFilters.status]}`;
    if (activityTitle) activityTitle.textContent = recordFilters.status === 'ALL'
        ? 'Actividad reciente de sincronización'
        : `Actividad reciente · ${statusLabels[recordFilters.status]}`;
};

const selectStatusFilter = (status) => {
    recordFilters.status = status;
    statusFilterButtons.forEach((button) => {
        const active = button.dataset.statusFilter === status;
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    metricCardsElements.forEach((card) => {
        const selected = status === 'ALL' || card.dataset.metricStatus === status;
        card.classList.toggle('filter-selected', status !== 'ALL' && selected);
        card.classList.toggle('filter-muted', !selected);
    });
    applyRecordFilters();
};

statusFilterButtons.forEach((button) => {
    button.addEventListener('click', () => selectStatusFilter(button.dataset.statusFilter || 'ALL'));
});

document.querySelector('.toolbar-apply')?.addEventListener('click', () => {
    const fromValue = fromFilterInput?.value.trim() || '';
    const toValue = toFilterInput?.value.trim() || '';
    const rowValue = rowFilterInput?.value.trim().replace(/^#/, '') || '';
    const validFrom = !fromValue || parseDashboardDate(fromValue);
    const validTo = !toValue || parseDashboardDate(toValue);
    const validRow = !rowValue || /^\d+$/.test(rowValue);
    fromFilterInput?.setCustomValidity(validFrom ? '' : 'Usa el formato dd/mm/aaaa.');
    toFilterInput?.setCustomValidity(validTo ? '' : 'Usa el formato dd/mm/aaaa.');
    rowFilterInput?.setCustomValidity(validRow ? '' : 'Ingresa únicamente el número de la fila.');
    const invalidInput = [rowFilterInput, fromFilterInput, toFilterInput].find((input) => input && !input.checkValidity());
    if (invalidInput) {
        invalidInput.reportValidity();
        return;
    }
    recordFilters.row = rowValue;
    recordFilters.from = fromValue;
    recordFilters.to = toValue;
    applyRecordFilters();
});

document.querySelector('.toolbar-clear')?.addEventListener('click', () => {
    [rowFilterInput, fromFilterInput].forEach((input) => { if (input) input.value = ''; });
    if (toFilterInput) toFilterInput.value = '<?= h(date('d/m/Y')) ?>';
    if (recordSearchInput) recordSearchInput.value = '';
    [rowFilterInput, fromFilterInput, toFilterInput].forEach((input) => input?.setCustomValidity(''));
    Object.assign(recordFilters, { status: 'ALL', row: '', from: '', to: '<?= h(date('d/m/Y')) ?>', search: '' });
    selectStatusFilter('ALL');
});

recordSearchInput?.addEventListener('input', () => {
    recordFilters.search = recordSearchInput.value.trim().toLocaleLowerCase('es');
    applyRecordFilters();
});

const closeMetricMenus = () => {
    document.querySelectorAll('[data-metric-dropdown]').forEach((dropdown) => { dropdown.hidden = true; });
    document.querySelectorAll('[data-metric-menu-toggle]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
};

document.querySelectorAll('[data-metric-menu-toggle]').forEach((button) => {
    button.addEventListener('click', (event) => {
        event.stopPropagation();
        const dropdown = button.parentElement?.querySelector('[data-metric-dropdown]');
        const opening = Boolean(dropdown?.hidden);
        closeMetricMenus();
        if (dropdown && opening) {
            dropdown.hidden = false;
            button.setAttribute('aria-expanded', 'true');
        }
    });
});

const csvCell = (value) => {
    let safeValue = String(value ?? '').replace(/\s+/g, ' ').trim();
    if (/^[=+\-@]/.test(safeValue)) safeValue = `'${safeValue}`;
    return `"${safeValue.replaceAll('"', '""')}"`;
};

document.querySelectorAll('[data-download-status]').forEach((button) => {
    button.addEventListener('click', () => {
        const status = button.dataset.downloadStatus || 'ALL';
        const headers = [...document.querySelectorAll('.dashboard-table thead th')].map((cell) => cell.innerText.trim());
        const rows = recordRows
            .filter((row) => recordMatchesFilters(row, status))
            .map((row) => [...row.cells].map((cell) => cell.innerText.trim()));
        const csv = [headers, ...rows].map((row) => row.map(csvCell).join(',')).join('\r\n');
        const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `registros-${status.toLocaleLowerCase('es')}-<?= h(date('Y-m-d')) ?>.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        closeMetricMenus();
    });
});

recordFilters.to = toFilterInput?.value.trim() || '';
applyRecordFilters();

const userMenu = document.querySelector('[data-user-menu]');
const userToggle = document.querySelector('[data-user-toggle]');
const userDropdown = document.querySelector('[data-user-dropdown]');
const closeUserMenu = () => {
    if (!userDropdown || !userToggle) return;
    userDropdown.hidden = true;
    userToggle.setAttribute('aria-expanded', 'false');
};
userToggle?.addEventListener('click', () => {
    const opening = userDropdown.hidden;
    userDropdown.hidden = !opening;
    userToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
});
userDropdown?.querySelector('a')?.addEventListener('click', closeUserMenu);
document.addEventListener('click', (event) => {
    if (userMenu && !userMenu.contains(event.target)) closeUserMenu();
    if (!(event.target instanceof Element) || !event.target.closest('.metric')) closeMetricMenus();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeUserMenu();
        closeMetricMenus();
        userToggle?.focus();
    }
});
</script>
</body>
</html>
