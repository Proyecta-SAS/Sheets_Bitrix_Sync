<?php

declare(strict_types=1);

use App\Config\IntegrationConfig;
use App\Http\AdminAuth;
use App\Http\Csrf;
use App\Http\Response;
use App\Support\Env;
use App\Support\SensitiveData;

try {
    /** @var App\Application $app */
    $app = require dirname(__DIR__) . '/bootstrap.php';
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No fue posible iniciar la aplicación: ' . $exception->getMessage();
    exit;
}

function routePath(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $scriptDirectory = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    if ($scriptDirectory !== '' && $scriptDirectory !== '.' && str_starts_with($path, $scriptDirectory)) {
        $path = substr($path, strlen($scriptDirectory)) ?: '/';
    }

    return '/' . ltrim($path, '/');
}

function requestHeader(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

    return trim((string) ($_SERVER[$serverKey] ?? ''));
}

function appBasePath(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

    return $base === '' || $base === '.' || $base === '/public' ? '' : $base;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function goHome(): never
{
    $base = appBasePath();
    Response::redirect($base === '' ? '/' : $base . '/');
}

function portalPayload(App\Application $app, int $limit = 100): array
{
    $config = $app->config();

    return [
        'generated_at' => gmdate('c'),
        'integration' => [
            'active' => $config->active,
            'sheet_name' => $config->sheetName,
            'category_id' => $config->categoryId,
            'stage_id' => $config->stageId,
        ],
        'counts' => $app->rows->counts(),
        'flow' => $app->rows->flowCounts(),
        'records' => $app->rows->latest($limit),
    ];
}

$path = routePath();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($path === '/api/sheets/event') {
    if ($method !== 'POST') {
        header('Allow: POST');
        Response::json(['ok' => false, 'error' => 'Método no permitido.'], 405);
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $limit = max(1, Env::int('WEBHOOK_RATE_LIMIT', 60));
    if (!$app->rateLimiter->allow('webhook:' . $ip, $limit, 60)) {
        Response::json(['ok' => false, 'error' => 'Demasiadas solicitudes.'], 429);
    }

    $expectedToken = Env::get('WEBHOOK_SECRET');
    $authorization = requestHeader('Authorization');
    $providedToken = requestHeader('X-Webhook-Token');
    if (str_starts_with($authorization, 'Bearer ')) {
        $providedToken = trim(substr($authorization, 7));
    }
    if ($expectedToken === '') {
        Response::json(['ok' => false, 'error' => 'WEBHOOK_SECRET no está configurado.'], 503);
    }
    if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        Response::json(['ok' => false, 'error' => 'No autorizado.'], 401);
    }

    try {
        $input = json_decode((string) file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
        $row = filter_var($input['row_number'] ?? $input['row'] ?? null, FILTER_VALIDATE_INT);
        if ($row === false || $row === null || $row < 1) {
            Response::json(['ok' => false, 'error' => 'Envíe row_number como entero positivo.'], 422);
        }
        $result = $app->sync->run($app->config(), (int) $row);
        if ($result['status'] === 'inactive') {
            Response::json(['ok' => false, 'error' => 'La integración está inactiva.', 'sync' => $result], 409);
        }
        Response::json(['ok' => $result['errors'] === 0, 'sync' => $result], $result['errors'] === 0 ? 200 : 422);
    } catch (JsonException) {
        Response::json(['ok' => false, 'error' => 'JSON no válido.'], 400);
    } catch (Throwable $exception) {
        $message = SensitiveData::clean($exception->getMessage(), [Env::get('BITRIX_WEBHOOK_URL'), Env::get('WEBHOOK_SECRET')]);
        $app->logger->error('webhook.failed', $message);
        Response::json(['ok' => false, 'error' => $message], 422);
    }
}

$auth = new AdminAuth();
$auth->startSession();

if (!$auth->isConfigured()) {
    http_response_code(503);
    require $app->root . '/views/setup-required.php';
    exit;
}

if ($path === '/login') {
    if ($auth->check()) {
        goHome();
    }

    $error = null;
    if ($method === 'POST') {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!$app->rateLimiter->allow('login:' . $ip, 10, 300)) {
            $error = 'Demasiados intentos. Espere unos minutos.';
        } elseif (!Csrf::validate($_POST['_csrf'] ?? null)) {
            $error = 'La sesión expiró. Recargue la página.';
        } elseif (!$auth->attempt(trim((string) ($_POST['user'] ?? '')), (string) ($_POST['password'] ?? ''))) {
            $error = 'Usuario o contraseña incorrectos.';
        } else {
            goHome();
        }
    }

    require $app->root . '/views/login.php';
    exit;
}

if (!$auth->check()) {
    $base = appBasePath();
    Response::redirect((($base === '' || $base === '.') ? '' : $base) . '/login');
}

if ($path === '/api/portal/status') {
    if ($method !== 'GET') {
        header('Allow: GET');
        Response::json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    Response::json(['ok' => true, 'portal' => portalPayload($app)]);
}

if ($method === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        flash('error', 'La sesión expiró. Intente de nuevo.');
        goHome();
    }

    try {
        switch ($path) {
            case '/logout':
                $auth->logout();
                goHome();

            case '/settings':
                $columns = (array) ($_POST['mapping_column'] ?? []);
                $fields = (array) ($_POST['mapping_field'] ?? []);
                $mapping = [];
                foreach ($columns as $index => $column) {
                    $column = trim((string) $column);
                    $field = strtoupper(trim((string) ($fields[$index] ?? '')));
                    if ($column === '' || $field === '' || in_array($column, IntegrationConfig::controlHeaders(), true)) {
                        continue;
                    }
                    if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $field) || (str_starts_with($field, 'UF_') && !str_starts_with($field, 'UF_CRM_'))) {
                        throw new InvalidArgumentException('Código Bitrix inválido para la columna ' . $column . '.');
                    }
                    $mapping[$column] = $field;
                }
                if (!in_array('TITLE', $mapping, true)) {
                    throw new InvalidArgumentException('Debe mapear una columna al campo TITLE.');
                }

                $spreadsheetId = trim((string) ($_POST['spreadsheet_id'] ?? ''));
                if ($spreadsheetId !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $spreadsheetId)) {
                    throw new InvalidArgumentException('El ID de Google Sheets tiene un formato inválido.');
                }
                $categoryId = trim((string) ($_POST['category_id'] ?? ''));
                if (!ctype_digit($categoryId)) {
                    throw new InvalidArgumentException('CATEGORY_ID debe ser numérico.');
                }
                $assignedById = trim((string) ($_POST['assigned_by_id'] ?? ''));
                if ($assignedById !== '' && !ctype_digit($assignedById)) {
                    throw new InvalidArgumentException('El responsable debe ser un ID numérico.');
                }

                $app->settings->setMany([
                    'active' => isset($_POST['active']),
                    'spreadsheet_id' => $spreadsheetId,
                    'sheet_name' => trim((string) ($_POST['sheet_name'] ?? '')),
                    'header_row' => max(1, (int) ($_POST['header_row'] ?? 1)),
                    'category_id' => $categoryId,
                    'stage_id' => trim((string) ($_POST['stage_id'] ?? '')),
                    'assigned_by_id' => $assignedById,
                    'batch_size' => max(1, min(100, (int) ($_POST['batch_size'] ?? 25))),
                    'mapping' => $mapping,
                    'detected_headers' => array_values(array_unique(array_filter(array_map('trim', $columns)))),
                ]);
                $app->config(true);
                flash('success', 'Configuración guardada.');
                break;

            case '/test/google':
                $config = $app->config();
                $result = $app->sheets->testConnection($config->spreadsheetId);
                $app->settings->set('available_sheets', $result['sheets']);
                flash('success', 'Google Sheets conectado: ' . $result['title'] . ' (' . count($result['sheets']) . ' pestañas).');
                break;

            case '/headers':
                $config = $app->config();
                $headers = $app->sheets->getHeaders($config->spreadsheetId, $config->sheetName, $config->headerRow);
                if ($headers === []) {
                    throw new RuntimeException('No se encontraron encabezados.');
                }
                $businessHeaders = array_values(array_diff($headers, IntegrationConfig::controlHeaders()));
                $app->settings->set('detected_headers', $businessHeaders);
                $app->config(true);
                flash('success', 'Se leyeron ' . count($businessHeaders) . ' encabezados.');
                break;

            case '/test/bitrix':
                $fields = $app->bitrix->dealFields();
                $app->settings->set('bitrix_field_codes', array_keys($fields));
                flash('success', 'Bitrix24 conectado: ' . count($fields) . ' campos disponibles.');
                break;

            case '/sync':
                $result = $app->sync->run($app->config(), null, true);
                if (($result['duplicates'] ?? 0) > 0) {
                    flash('success', $result['duplicates'] . ' filas marcadas como DUPLICADO por correo existente.');
                }
                flash($result['errors'] > 0 ? 'error' : 'success', sprintf(
                    'Sincronización terminada: %d creadas, %d errores, %d ya existentes.',
                    $result['created'],
                    $result['errors'],
                    $result['already_created'],
                ));
                break;

            case '/retry':
                $id = max(0, (int) ($_POST['id'] ?? 0));
                if (!$app->rows->retry($id)) {
                    throw new RuntimeException('El registro ya no está disponible para reintento.');
                }
                flash('success', 'Registro marcado como pendiente. Ejecute la sincronización manual o espere al cron.');
                break;

            case '/retry-all':
                $total = $app->rows->retryAll();
                flash('success', $total . ' registros quedaron pendientes para reintento.');
                break;

            default:
                http_response_code(404);
                echo 'No encontrado';
                exit;
        }
    } catch (Throwable $exception) {
        $message = SensitiveData::clean($exception->getMessage(), [Env::get('BITRIX_WEBHOOK_URL'), Env::get('WEBHOOK_SECRET')]);
        $app->logger->error('admin.action_failed', $message, ['path' => $path]);
        flash('error', $message);
    }

    goHome();
}

if (!in_array($path, ['/', '/portal'], true)) {
    http_response_code(404);
    echo 'No encontrado';
    exit;
}

$config = $app->config();
$records = $app->rows->latest(100);
$counts = $app->rows->counts();
$flow = $app->rows->flowCounts();
$logs = $app->logger->latest(50);
$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$csrf = Csrf::token();
$knownFields = (array) $app->settings->get('bitrix_field_codes', []);

require $app->root . ($path === '/portal' ? '/views/portal.php' : '/views/dashboard.php');
