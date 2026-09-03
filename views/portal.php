<?php

declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flowState(array $record): array
{
    $status = strtoupper((string) ($record['status'] ?? ''));
    $hasDeal = trim((string) ($record['deal_id'] ?? '')) !== '';
    $sheetSynced = trim((string) ($record['sheet_synced_at'] ?? '')) !== '';

    if ($status === 'ERROR') {
        return ['label' => 'Revisar', 'class' => 'error', 'summary' => (string) ($record['last_error'] ?? 'Error pendiente de revision.')];
    }
    if ($status === 'DUPLICADO') {
        return ['label' => 'Duplicado', 'class' => 'warning', 'summary' => (string) ($record['last_error'] ?? 'Correo ya registrado en Bitrix.')];
    }
    if ($sheetSynced) {
        return ['label' => 'Completa', 'class' => 'done', 'summary' => 'Negociacion creada y Sheet actualizado.'];
    }
    if ($hasDeal) {
        return ['label' => 'Cierre pendiente', 'class' => 'warning', 'summary' => 'Bitrix ya devolvio ID; falta confirmar el Sheet.'];
    }
    if ($status === 'PROCESANDO') {
        return ['label' => 'Procesando', 'class' => 'active', 'summary' => 'La fila esta tomada por un proceso de sincronizacion.'];
    }
    if ($status === 'PENDIENTE') {
        return ['label' => 'En cola', 'class' => 'queued', 'summary' => 'Lista para la siguiente ejecucion.'];
    }

    return ['label' => $status !== '' ? $status : 'Sin estado', 'class' => 'queued', 'summary' => 'Registro detectado localmente.'];
}

function stepClass(array $record, string $step): string
{
    $status = strtoupper((string) ($record['status'] ?? ''));
    $hasDeal = trim((string) ($record['deal_id'] ?? '')) !== '';
    $sheetSynced = trim((string) ($record['sheet_synced_at'] ?? '')) !== '';

    if ($step === 'sheet') {
        return 'done';
    }
    if ($step === 'process') {
        if ($status === 'ERROR') {
            return 'error';
        }

        return in_array($status, ['PROCESANDO', 'PENDIENTE'], true) ? 'active' : 'done';
    }
    if ($step === 'bitrix') {
        return $hasDeal ? 'done' : (in_array($status, ['ERROR', 'DUPLICADO'], true) ? 'blocked' : 'pending');
    }
    if ($step === 'close') {
        if ($sheetSynced) {
            return 'done';
        }

        return $hasDeal ? 'active' : (in_array($status, ['ERROR', 'DUPLICADO'], true) ? 'blocked' : 'pending');
    }

    return 'pending';
}

function renderRecord(array $record): void
{
    $state = flowState($record);
    $steps = [
        'sheet' => 'Sheet',
        'process' => 'Proceso',
        'bitrix' => 'Bitrix',
        'close' => 'Cierre',
    ];
    ?>
    <article class="record">
        <div class="record-main">
            <div>
                <div class="row-title"><?= h($record['sheet_name'] ?? '') ?> #<?= h($record['row_number'] ?? '') ?></div>
                <div class="row-sub code"><?= h($record['unique_identifier'] ?? '') ?></div>
            </div>
            <div class="record-side">
                <span class="pill <?= h($state['class']) ?>"><?= h($state['label']) ?></span>
                <span class="muted"><?= h($record['updated_at'] ?? '') ?></span>
            </div>
        </div>
        <div class="steps">
            <?php foreach ($steps as $key => $label): ?>
                <div class="step <?= h(stepClass($record, $key)) ?>"><span></span><?= h($label) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="record-meta">
            <span>ID Bitrix: <strong><?= h(($record['deal_id'] ?? '') !== '' ? $record['deal_id'] : 'pendiente') ?></strong></span>
            <span>Intentos: <strong><?= h($record['attempts'] ?? 0) ?></strong></span>
            <span><?= h($state['summary']) ?></span>
        </div>
    </article>
    <?php
}

$activeFlow = ($flow['pending'] ?? 0) + ($flow['processing'] ?? 0);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Portal de negociaciones</title>
    <style>
        :root{--bg:#f6f8fb;--surface:#fff;--line:#d9e2ee;--text:#172033;--muted:#64748b;--blue:#2563eb;--green:#16803d;--red:#b42318;--amber:#a16207;--cyan:#0e7490}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.45 system-ui,-apple-system,Segoe UI,sans-serif}button{font:inherit}.topbar{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.94);border-bottom:1px solid var(--line);backdrop-filter:blur(12px)}.topbar-inner{max-width:1180px;margin:auto;padding:13px 18px;display:flex;align-items:center;justify-content:space-between;gap:14px}.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:16px}.logo{width:34px;height:34px;border-radius:8px;background:var(--blue);color:#fff;display:grid;place-items:center;font-size:12px}.nav{display:flex;align-items:center;gap:8px}.container{max-width:1180px;margin:auto;padding:22px 18px 44px}.hero{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}h1{font-size:26px;margin:0 0 4px}.muted{color:var(--muted)}.code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:8px;padding:8px 11px;font-weight:700;text-decoration:none;cursor:pointer}.btn:hover{background:#f8fafc}.btn.small{font-size:12px;padding:6px 9px}.logout{margin:0}.status{display:inline-flex;align-items:center;gap:7px;border-radius:99px;padding:7px 10px;font-weight:800;background:#ecfdf5;color:#166534}.status.off{background:#f1f5f9;color:#64748b}.dot{width:8px;height:8px;border-radius:50%;background:currentColor}.metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px}.metric{background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:14px}.metric strong{display:block;font-size:25px;line-height:1.1}.metric span{color:var(--muted)}.panel{background:var(--surface);border:1px solid var(--line);border-radius:8px;overflow:hidden}.panel-head{padding:16px 18px;border-bottom:1px solid var(--line);display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.panel-head h2{font-size:17px;margin:0 0 3px}.records{display:grid}.record{padding:16px 18px;border-bottom:1px solid #e8edf4}.record:last-child{border-bottom:0}.record-main{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:13px}.row-title{font-size:16px;font-weight:800}.row-sub{margin-top:2px;color:var(--muted);overflow-wrap:anywhere}.record-side{display:flex;align-items:flex-end;gap:8px;flex-direction:column;text-align:right}.pill{display:inline-flex;border-radius:99px;padding:4px 8px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.03em}.pill.done{background:#dcfce7;color:#166534}.pill.active{background:#dbeafe;color:#1d4ed8}.pill.warning{background:#fef3c7;color:#92400e}.pill.error{background:#fee2e2;color:#991b1b}.pill.queued{background:#e0f2fe;color:#075985}.steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-bottom:11px}.step{position:relative;min-height:38px;border:1px solid var(--line);border-radius:8px;padding:9px 9px 9px 29px;background:#f8fafc;color:#64748b;font-weight:800}.step span{position:absolute;left:10px;top:14px;width:9px;height:9px;border-radius:50%;background:#94a3b8}.step.done{border-color:#bbf7d0;background:#f0fdf4;color:#166534}.step.done span{background:var(--green)}.step.active{border-color:#bfdbfe;background:#eff6ff;color:#1d4ed8}.step.active span{background:var(--blue);box-shadow:0 0 0 4px #dbeafe}.step.error{border-color:#fecaca;background:#fef2f2;color:#991b1b}.step.error span{background:var(--red)}.step.blocked{opacity:.55}.record-meta{display:flex;flex-wrap:wrap;gap:8px 18px;color:var(--muted)}.empty{padding:36px 18px;text-align:center;color:var(--muted)}@media(max-width:850px){.hero,.record-main{flex-direction:column}.record-side{align-items:flex-start;text-align:left}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.steps{grid-template-columns:repeat(2,minmax(0,1fr))}.topbar-inner{padding:11px 14px}.container{padding:18px 14px 36px}}@media(max-width:480px){.metrics{grid-template-columns:1fr}.nav{flex-wrap:wrap;justify-content:flex-end}.step{font-size:12px}.record{padding:14px}}
    </style>
</head>
<body>
<header class="topbar"><div class="topbar-inner"><div class="brand"><div class="logo">SB</div>Portal de negociaciones <span class="muted code">V<?= h(App\Application::VERSION) ?></span></div><div class="nav"><a class="btn small" href="./">Panel</a><form class="logout" method="post" action="logout"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><button class="btn small" type="submit">Salir</button></form></div></div></header>
<main class="container">
    <section class="hero"><div><h1>Flujo de creacion en Bitrix</h1><div class="muted">Hoja <?= h($config->sheetName) ?> - Pipeline <?= h($config->categoryId) ?> - Etapa <?= h($config->stageId) ?></div></div><div class="status <?= $config->active ? '' : 'off' ?>"><span class="dot"></span><?= $config->active ? 'Integracion activa' : 'Integracion inactiva' ?></div></section>

    <section class="metrics">
        <div class="metric"><strong data-metric="total"><?= h($flow['total'] ?? 0) ?></strong><span>Registros</span></div>
        <div class="metric"><strong data-metric="active"><?= h($activeFlow) ?></strong><span>En curso</span></div>
        <div class="metric"><strong data-metric="bitrix_created"><?= h($flow['bitrix_created'] ?? 0) ?></strong><span>Creadas en Bitrix</span></div>
        <div class="metric"><strong data-metric="sheet_synced"><?= h($flow['sheet_synced'] ?? 0) ?></strong><span>Cerradas en Sheet</span></div>
        <div class="metric"><strong data-metric="errors"><?= h($flow['errors'] ?? 0) ?></strong><span>Errores</span></div>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>Ultimos registros</h2><div class="muted">Actualizacion automatica cada 10 segundos.</div></div><div class="muted code" id="generated-at"><?= h(gmdate('c')) ?></div></div>
        <div class="records" id="records">
            <?php if ($records === []): ?><div class="empty">Todavia no hay registros procesados.</div><?php endif; ?>
            <?php foreach ($records as $record): ?><?php renderRecord($record); ?><?php endforeach; ?>
        </div>
    </section>
</main>
<script>
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

function recordState(record) {
    const status = String(record.status ?? '').toUpperCase();
    const hasDeal = String(record.deal_id ?? '').trim() !== '';
    const sheetSynced = String(record.sheet_synced_at ?? '').trim() !== '';
    if (status === 'ERROR') return {label: 'Revisar', className: 'error', summary: record.last_error || 'Error pendiente de revision.'};
    if (status === 'DUPLICADO') return {label: 'Duplicado', className: 'warning', summary: record.last_error || 'Correo ya registrado en Bitrix.'};
    if (sheetSynced) return {label: 'Completa', className: 'done', summary: 'Negociacion creada y Sheet actualizado.'};
    if (hasDeal) return {label: 'Cierre pendiente', className: 'warning', summary: 'Bitrix ya devolvio ID; falta confirmar el Sheet.'};
    if (status === 'PROCESANDO') return {label: 'Procesando', className: 'active', summary: 'La fila esta tomada por un proceso de sincronizacion.'};
    if (status === 'PENDIENTE') return {label: 'En cola', className: 'queued', summary: 'Lista para la siguiente ejecucion.'};
    return {label: status || 'Sin estado', className: 'queued', summary: 'Registro detectado localmente.'};
}

function stepClass(record, step) {
    const status = String(record.status ?? '').toUpperCase();
    const hasDeal = String(record.deal_id ?? '').trim() !== '';
    const sheetSynced = String(record.sheet_synced_at ?? '').trim() !== '';
    if (step === 'sheet') return 'done';
    if (step === 'process') return status === 'ERROR' ? 'error' : (['PROCESANDO', 'PENDIENTE'].includes(status) ? 'active' : 'done');
    if (step === 'bitrix') return hasDeal ? 'done' : (['ERROR', 'DUPLICADO'].includes(status) ? 'blocked' : 'pending');
    if (step === 'close') return sheetSynced ? 'done' : (hasDeal ? 'active' : (['ERROR', 'DUPLICADO'].includes(status) ? 'blocked' : 'pending'));
    return 'pending';
}

function renderRecord(record) {
    const state = recordState(record);
    const steps = [['sheet', 'Sheet'], ['process', 'Proceso'], ['bitrix', 'Bitrix'], ['close', 'Cierre']]
        .map(([key, label]) => `<div class="step ${stepClass(record, key)}"><span></span>${label}</div>`).join('');
    const dealId = String(record.deal_id ?? '').trim() || 'pendiente';
    return `<article class="record">
        <div class="record-main">
            <div><div class="row-title">${esc(record.sheet_name)} #${esc(record.row_number)}</div><div class="row-sub code">${esc(record.unique_identifier)}</div></div>
            <div class="record-side"><span class="pill ${state.className}">${esc(state.label)}</span><span class="muted">${esc(record.updated_at)}</span></div>
        </div>
        <div class="steps">${steps}</div>
        <div class="record-meta"><span>ID Bitrix: <strong>${esc(dealId)}</strong></span><span>Intentos: <strong>${esc(record.attempts ?? 0)}</strong></span><span>${esc(state.summary)}</span></div>
    </article>`;
}

async function refreshPortal() {
    try {
        const response = await fetch('api/portal/status', {headers: {'Accept': 'application/json'}, cache: 'no-store'});
        if (!response.ok) return;
        const payload = await response.json();
        const portal = payload.portal || {};
        const flow = portal.flow || {};
        const active = Number(flow.pending || 0) + Number(flow.processing || 0);
        const metrics = {total: flow.total || 0, active, bitrix_created: flow.bitrix_created || 0, sheet_synced: flow.sheet_synced || 0, errors: flow.errors || 0};
        for (const [key, value] of Object.entries(metrics)) {
            const node = document.querySelector(`[data-metric="${key}"]`);
            if (node) node.textContent = value;
        }
        const generated = document.getElementById('generated-at');
        if (generated) generated.textContent = portal.generated_at || '';
        const records = document.getElementById('records');
        if (records) {
            records.innerHTML = (portal.records || []).length
                ? portal.records.map(renderRecord).join('')
                : '<div class="empty">Todavia no hay registros procesados.</div>';
        }
    } catch (error) {
        // El portal conserva la ultima foto disponible si el refresco falla.
    }
}

setInterval(refreshPortal, 10000);
</script>
</body>
</html>
