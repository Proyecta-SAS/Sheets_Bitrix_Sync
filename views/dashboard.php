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
    <style>
        :root{--bg:#f4f7fb;--card:#fff;--line:#dce3ee;--text:#172033;--muted:#64748b;--blue:#2563eb;--blue2:#1d4ed8;--green:#15803d;--red:#b91c1c;--amber:#a16207}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}button,input,select{font:inherit}.topbar{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.93);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}.topbar-inner{max-width:1240px;margin:auto;padding:13px 22px;display:flex;align-items:center;justify-content:space-between}.brand{display:flex;align-items:center;gap:11px;font-weight:800;font-size:17px}.logo{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;background:var(--blue);color:#fff;font-size:12px}.container{max-width:1240px;margin:auto;padding:24px 22px 50px}.hero{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:20px}h1{font-size:27px;margin:0 0 4px}.muted{color:var(--muted)}.status{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border-radius:99px;background:#ecfdf5;color:#166534;font-weight:700}.status.off{background:#f1f5f9;color:#64748b}.dot{width:8px;height:8px;background:currentColor;border-radius:50%}.grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:18px}.card{grid-column:span 12;background:var(--card);border:1px solid var(--line);border-radius:15px;padding:20px;box-shadow:0 7px 20px rgba(31,50,81,.035)}.half{grid-column:span 6}.third{grid-column:span 4}.card h2{font-size:17px;margin:0 0 4px}.card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.metric{padding:15px;border:1px solid var(--line);border-radius:12px}.metric strong{font-size:25px;display:block}.metric span{color:var(--muted)}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field.full{grid-column:1/-1}.field label{display:block;font-weight:700;margin-bottom:6px}.field small{display:block;color:var(--muted);margin-top:5px}input[type=text],input[type=number],input[type=password],select{width:100%;padding:10px 11px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:var(--text)}input:focus,select:focus{border-color:var(--blue);outline:3px solid #dbeafe}.switch{display:flex;align-items:center;gap:9px;font-weight:700}.mapping{width:100%;border-collapse:collapse}.mapping th,.mapping td{text-align:left;padding:9px;border-bottom:1px solid #e8edf4}.mapping th{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}.arrow{width:34px;color:var(--muted);text-align:center}.actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:18px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:9px;padding:9px 13px;font-weight:700;cursor:pointer;text-decoration:none}.btn:hover{background:#f8fafc}.btn.primary{border-color:var(--blue);background:var(--blue);color:#fff}.btn.primary:hover{background:var(--blue2)}.btn.danger{color:var(--red)}.btn.small{padding:6px 9px;font-size:12px}.flash{padding:11px 14px;border-radius:10px;margin-bottom:12px;border:1px solid}.flash.success{background:#f0fdf4;border-color:#bbf7d0;color:#166534}.flash.error{background:#fef2f2;border-color:#fecaca;color:#991b1b}.table-wrap{overflow:auto}.data-table{width:100%;border-collapse:collapse;min-width:780px}.data-table th,.data-table td{text-align:left;padding:10px 9px;border-bottom:1px solid #e8edf4;vertical-align:top}.data-table th{color:var(--muted);font-size:12px}.badge{display:inline-block;padding:3px 7px;border-radius:99px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:800}.badge.CREADA{background:#dcfce7;color:#166534}.badge.ERROR{background:#fee2e2;color:#991b1b}.badge.PROCESANDO{background:#fef3c7;color:#92400e}.log-error{color:var(--red)}.empty{padding:28px;text-align:center;color:var(--muted)}.logout{margin:0}.code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px}.help{background:#eff6ff;border-color:#bfdbfe}.help ol{margin-bottom:0;padding-left:20px}@media(max-width:850px){.half,.third{grid-column:span 12}.metrics{grid-template-columns:repeat(2,1fr)}.form-grid{grid-template-columns:1fr}.hero{flex-direction:column}.container{padding:18px 14px}.topbar-inner{padding:11px 14px}}@media(max-width:480px){.metrics{grid-template-columns:1fr 1fr}.card{padding:16px}.mapping .arrow{display:none}.mapping input{min-width:145px}.actions .btn{flex:1}}
    </style>
</head>
<body>
<header class="topbar"><div class="topbar-inner"><div class="brand"><div class="logo">SB</div>Sheets → Bitrix24 <span class="muted code">V<?= h(App\Application::VERSION) ?></span></div><div class="actions" style="margin-top:0"><a class="btn small" href="portal">Portal</a><form class="logout" method="post" action="logout"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn small" type="submit">Salir</button></form></div></div></header>
<main class="container">
    <section class="hero"><div><h1>Panel de sincronización</h1><div class="muted">Google Sheets → Pipeline PRUEBA TECNOLOGIA</div></div><div class="status <?= $config->active ? '' : 'off' ?>"><span class="dot"></span><?= $config->active ? 'Integración activa' : 'Integración inactiva' ?></div></section>

    <?php foreach ($flashes as $item): ?><div class="flash <?= h($item['type']) ?>"><?= h($item['message']) ?></div><?php endforeach; ?>

    <div class="grid">
        <section class="card">
            <div class="metrics">
                <?php foreach (['CREADA' => 'Creadas', 'PENDIENTE' => 'Pendientes', 'PROCESANDO' => 'Procesando', 'DUPLICADO' => 'Duplicadas', 'ERROR' => 'Errores'] as $key => $label): ?>
                    <div class="metric"><strong><?= h($counts[$key] ?? 0) ?></strong><span><?= h($label) ?></span></div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card half">
            <div class="card-head"><div><h2>Conexiones</h2><div class="muted">Pruebe accesos antes de activar.</div></div></div>
            <div class="actions">
                <form method="post" action="test/google"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Probar Google Sheets</button></form>
                <form method="post" action="test/bitrix"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Probar Bitrix24</button></form>
                <form method="post" action="headers"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Leer encabezados</button></form>
            </div>
        </section>

        <section class="card half">
            <div class="card-head"><div><h2>Ejecución</h2><div class="muted">Procesa hasta <?= h($config->batchSize) ?> filas pendientes.</div></div></div>
            <div class="actions">
                <form method="post" action="sync"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn primary" type="submit">Sincronizar ahora</button></form>
                <form method="post" action="retry-all"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn" type="submit">Reintentar errores</button></form>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>Configuración</h2><div class="muted">Los secretos se administran exclusivamente en <span class="code">.env</span>.</div></div></div>
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

                <div class="card-head" style="margin-top:26px"><div><h2>Mapeo de campos</h2><div class="muted">Deje vacíos los campos que no desea enviar. Registre los códigos reales <span class="code">UF_CRM_...</span>.</div></div></div>
                <div class="table-wrap"><table class="mapping"><thead><tr><th>Columna del Sheet</th><th class="arrow"></th><th>Campo de Bitrix24</th></tr></thead><tbody>
                    <?php foreach ($headers as $index => $header): ?>
                        <tr><td><input type="hidden" name="mapping_column[]" value="<?= h($header) ?>"><strong><?= h($header) ?></strong></td><td class="arrow">→</td><td><input type="text" name="mapping_field[]" value="<?= h($config->mapping[$header] ?? '') ?>" list="bitrix-fields" placeholder="UF_CRM_..."></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <datalist id="bitrix-fields"><?php foreach ($knownFields as $field): ?><option value="<?= h($field) ?>"><?php endforeach; ?></datalist>
                <div class="actions"><button class="btn primary" type="submit">Guardar configuración</button></div>
            </form>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>Registros procesados</h2><div class="muted">Últimos 100 intentos persistidos localmente.</div></div></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Fila</th><th>Estado</th><th>ID Bitrix</th><th>Identificador</th><th>Intentos</th><th>Actualizado</th><th>Error / acción</th></tr></thead><tbody>
                <?php if ($records === []): ?><tr><td colspan="7" class="empty">Todavía no hay registros.</td></tr><?php endif; ?>
                <?php foreach ($records as $record): ?><tr>
                    <td><?= h($record['sheet_name']) ?> #<?= h($record['row_number']) ?></td><td><span class="badge <?= h($record['status']) ?>"><?= h($record['status']) ?></span></td><td><?= h($record['deal_id'] ?: '—') ?></td><td class="code"><?= h($record['unique_identifier']) ?></td><td><?= h($record['attempts']) ?></td><td><?= h($record['updated_at']) ?></td><td><?php if ($record['last_error']): ?><div class="log-error"><?= h($record['last_error']) ?></div><?php endif; ?><?php if ($record['status'] === 'ERROR'): ?><form method="post" action="retry" style="margin-top:6px"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= h($record['id']) ?>"><button class="btn small" type="submit">Reintentar</button></form><?php endif; ?></td>
                </tr><?php endforeach; ?>
            </tbody></table></div>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>Eventos y errores</h2><div class="muted">Últimos 50 eventos, sin secretos.</div></div></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha UTC</th><th>Nivel</th><th>Evento</th><th>Mensaje</th></tr></thead><tbody>
                <?php if ($logs === []): ?><tr><td colspan="4" class="empty">Sin eventos registrados.</td></tr><?php endif; ?>
                <?php foreach ($logs as $log): ?><tr><td><?= h($log['created_at']) ?></td><td><span class="badge <?= $log['level'] === 'error' ? 'ERROR' : '' ?>"><?= h(strtoupper($log['level'])) ?></span></td><td class="code"><?= h($log['event']) ?></td><td><?= h($log['message']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>

        <section class="card help">
            <h2>Orden recomendado</h2>
            <ol><li>Complete <span class="code">.env</span> y comparta el Sheet con el correo de la cuenta de servicio.</li><li>Guarde el ID y la hoja, pruebe Google y lea los encabezados.</li><li>Pruebe Bitrix, complete los códigos <span class="code">UF_CRM_...</span> reales y guarde.</li><li>Ejecute una sincronización manual; cuando valide el resultado, active la integración y el cron.</li></ol>
        </section>
    </div>
</main>
</body>
</html>
