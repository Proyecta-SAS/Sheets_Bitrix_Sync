# Sheets Bitrix Sync

Aplicación PHP independiente para convertir filas nuevas de Google Sheets en negociaciones de Bitrix24 de forma configurable e idempotente.

Versión actual: **V1.0.0**.

## Funciones

- Google Sheets API oficial con cuenta de servicio.
- Creación mediante `crm.deal.add` y webhook entrante de Bitrix24.
- Pipeline, etapa, responsable y mapeo de columnas configurables.
- Campos personalizados `UF_CRM_...` registrados desde la interfaz; el proyecto no inventa códigos.
- Columnas de control en el Sheet: estado, ID de Bitrix, fecha, error e identificador único.
- Prevención de duplicados en SQLite, bloqueo por fila e identificador externo en Bitrix.
- Recuperación segura si Bitrix responde pero falla la actualización de Google Sheets.
- Sincronización por cron, webhook protegido o botón manual.
- Administración responsive con autenticación, CSRF, registros y reintentos.
- Portal de monitoreo en `/portal` para ver el avance de cada negociación por etapas.

## Requisitos

- PHP 8.1 o superior.
- Composer 2.
- Extensiones PHP: cURL, PDO y PDO SQLite.
- HTTPS.
- Una cuenta de servicio de Google con acceso de editor al documento.
- Un webhook entrante de Bitrix24 con permisos de CRM.

## Instalación rápida

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php bin/hash-password.php
php bin/health.php
```

Copie el hash generado a `ADMIN_PASSWORD_HASH`, configure Google y Bitrix en `.env`, y coloque el JSON de Google en la ruta indicada por `GOOGLE_CREDENTIALS_PATH`.

Configure el document root del dominio o subdominio en la carpeta `public/`. Después ingrese al panel, pruebe ambas conexiones, lea los encabezados, complete el mapeo y ejecute una sincronización manual.

Las instrucciones detalladas para Google Cloud, Bitrix24, cPanel, cron y webhook están en [docs/INSTALL.md](docs/INSTALL.md).

## Ejecución

```bash
# Respeta el estado activo/inactivo configurado en el panel
php bin/sync.php

# Prueba manual por CLI aunque la integración esté inactiva
php bin/sync.php --force

# Procesa una fila concreta
php bin/sync.php --row=12 --force
```

Webhook:

```bash
curl -X POST 'https://integraciones.example.com/api/sheets/event' \
  -H 'Content-Type: application/json' \
  -H 'X-Webhook-Token: SU_TOKEN' \
  -d '{"row_number":12}'
```

## Configuración inicial incluida

- `CATEGORY_ID`: `216` (PRUEBA TECNOLOGIA).
- `STAGE_ID`: `C216:UC_Y5905W`.
- `Nombre` → `TITLE`.
- `Etapa` → `STAGE_ID`; si la celda está vacía se usa la etapa predeterminada.
- Teléfono, correo, ciudad, cargo y estado quedan sin código hasta registrar sus campos reales de Bitrix24.

## Pruebas

```bash
composer test
```

Las pruebas cubren conversión de columnas, creación, idempotencia, bloqueo concurrente, errores de validación y recuperación cuando Google falla después de crear la negociación.

## Seguridad

`.env`, `vendor/`, la base SQLite y los JSON de credenciales están excluidos de Git. La URL completa del webhook y los tokens se ocultan de los errores persistidos. No coloque el proyecto completo dentro del directorio público: exponga únicamente `public/`.
