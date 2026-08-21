# Instalación y despliegue

## 1. Preparar Google Cloud

1. Cree o seleccione un proyecto en Google Cloud.
2. Habilite **Google Sheets API**.
3. Cree una cuenta de servicio y descargue su clave JSON.
4. Guarde el archivo fuera del directorio web, por ejemplo en `storage/credentials/google-service-account.json`.
5. Abra el documento de Google Sheets y compártalo como **Editor** con el correo `client_email` que aparece en el JSON.

La aplicación necesita permiso de escritura porque agrega y actualiza las columnas de control.

## 2. Preparar Bitrix24

1. En Bitrix24 cree un webhook entrante.
2. Asigne permisos de CRM y copie la URL base, con una forma similar a:

   ```text
   https://suportal.bitrix24.com/rest/123/SECRETO/
   ```

3. Confirme en Bitrix los códigos reales de los campos personalizados para teléfono, correo, ciudad, cargo y estado.
4. No agregue `crm.deal.add` a la URL configurada; la aplicación agrega el nombre del método.

La conexión se prueba con `crm.deal.fields`. Antes de crear se consulta `crm.deal.list` por `ORIGINATOR_ID` y `ORIGIN_ID` para reforzar la idempotencia.

## 3. Instalar en cPanel

Una estructura recomendada es:

```text
/home/USUARIO/apps/Sheets_Bitrix/
/home/USUARIO/apps/Sheets_Bitrix/public/   <- document root del subdominio
```

Desde **Select PHP Version** o **MultiPHP Manager**, seleccione PHP 8.1 o superior y habilite `curl`, `pdo`, `pdo_sqlite`, `openssl` y `mbstring`.

En la terminal de cPanel:

```bash
cd /home/USUARIO/apps/Sheets_Bitrix
composer install --no-dev --optimize-autoloader
cp .env.example .env
chmod 600 .env storage/credentials/google-service-account.json
chmod 770 storage storage/credentials
php bin/health.php
```

Si cPanel no ofrece Composer global, descargue Composer siguiendo su instalador oficial o ejecute Composer localmente y cargue también `vendor/`. `composer.lock` debe conservarse para instalar las mismas versiones.

Configure el dominio/subdominio para que apunte a `public/`. Esto evita exponer `.env`, `storage/`, `vendor/` y la credencial JSON.

Si HostGator obliga a instalar el proyecto completo dentro de `public_html`, conserve el `.htaccess` de la raiz del proyecto. Ese archivo redirige las visitas hacia `public/` y bloquea el acceso web a `app/`, `bin/`, `docs/`, `storage/`, `tests/`, `vendor/`, `.env` y credenciales.

Permisos recomendados en cPanel:

```bash
find /home/USUARIO/public_html -type d -exec chmod 755 {} \;
find /home/USUARIO/public_html -type f -exec chmod 644 {} \;
chmod 600 /home/USUARIO/public_html/.env
chmod 750 /home/USUARIO/public_html/storage /home/USUARIO/public_html/storage/credentials
chmod 660 /home/USUARIO/public_html/storage/app.sqlite
chmod 600 /home/USUARIO/public_html/storage/credentials/google-service-account.json
```

Si el navegador muestra `DNS_PROBE_FINISHED_NXDOMAIN`, el dominio o subdominio todavia no resuelve en DNS. Ese error ocurre antes de que Apache, PHP o `.htaccess` reciban la solicitud. En cPanel debe existir el subdominio `sheetsxbitrix.proyectasolutions.co` y el DNS debe tener un registro `A` o `CNAME` valido apuntando al servidor de HostGator.

## 4. Configurar `.env`

Genere primero el hash administrativo:

```bash
php bin/hash-password.php
```

Complete como mínimo:

```dotenv
APP_ENV=production
APP_TIMEZONE=America/Bogota
APP_URL=https://integraciones.example.com

ADMIN_USER=admin
ADMIN_PASSWORD_HASH=$2y$...
WEBHOOK_SECRET=un_token_aleatorio_largo

GOOGLE_SPREADSHEET_ID=ID_DEL_DOCUMENTO
GOOGLE_SHEET_NAME=Nombre de pestaña
GOOGLE_CREDENTIALS_PATH=storage/credentials/google-service-account.json

BITRIX_WEBHOOK_URL=https://suportal.bitrix24.com/rest/123/SECRETO/
BITRIX_CATEGORY_ID=216
BITRIX_STAGE_ID=C216:UC_Y5905W
BITRIX_ASSIGNED_BY_ID=
```

Para generar un token aleatorio desde PHP:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

No use una contraseña en texto plano en `ADMIN_PASSWORD_HASH`.

## 5. Configurar desde el panel

1. Ingrese con `ADMIN_USER` y la contraseña que usó para generar el hash.
2. Guarde el ID del documento, nombre de la pestaña y fila de encabezados.
3. Pulse **Probar Google Sheets**. Las pestañas quedarán disponibles en el selector.
4. Pulse **Leer encabezados**.
5. Pulse **Probar Bitrix24**. Los códigos accesibles se cargarán como sugerencias.
6. Asigne `TITLE`, `STAGE_ID` y los `UF_CRM_...` reales.
7. Ejecute **Sincronizar ahora** con una fila de prueba.
8. Active la integración cuando valide la negociación creada.

Si la columna `Etapa` contiene valores, deben ser IDs válidos de etapa de Bitrix, por ejemplo `C216:UC_Y5905W`. Una celda vacía usa el `STAGE_ID` predeterminado.

## 6. Programar el cron

En **Cron Jobs** de cPanel, por ejemplo cada cinco minutos:

```cron
*/5 * * * * /usr/local/bin/php -q /home/USUARIO/apps/Sheets_Bitrix/bin/sync.php >> /home/USUARIO/logs/sheets-bitrix-cron.log 2>&1
```

La ruta del binario PHP puede variar. cPanel suele mostrarla en el selector de versión. El intervalo se define en cron y la cantidad máxima por ciclo se configura con `SYNC_BATCH_SIZE` o desde el panel.

## 7. Usar el endpoint webhook

Endpoint:

```text
POST /api/sheets/event
```

Encabezado recomendado:

```text
X-Webhook-Token: valor_de_WEBHOOK_SECRET
```

También se acepta `Authorization: Bearer valor_de_WEBHOOK_SECRET`.

Cuerpo:

```json
{
  "row_number": 12
}
```

El endpoint solo acepta POST, valida JSON, exige token y limita solicitudes por IP. Para que Google Sheets lo llame automaticamente al editar o agregar una fila, use el script de ejemplo en `docs/google-apps-script-webhook.js`.

En Google Sheets:

1. Abra **Extensiones > Apps Script**.
2. Pegue el contenido de `docs/google-apps-script-webhook.js`.
3. En **Configuracion del proyecto > Propiedades del script**, cree:

   ```text
   WEBHOOK_URL=https://su-dominio.com/api/sheets/event
   WEBHOOK_SECRET=el_mismo_valor_de_WEBHOOK_SECRET
   HEADER_ROW=1
   REQUIRED_HEADERS=Nombre
   ```

4. Ejecute una vez `installTriggers()` y acepte los permisos.

`REQUIRED_HEADERS` evita disparar la integracion con filas incompletas. Si la hoja real usa otro encabezado obligatorio para el titulo de la negociacion, cambielo por ese nombre. No dependa exclusivamente de `onEdit`: mantenga el cron como mecanismo de respaldo.

## 8. Columnas de control e idempotencia

Cuando falten, la aplicación agrega:

- `Estado sincronización`
- `ID negociación Bitrix`
- `Fecha de sincronización`
- `Error de sincronización`
- `Identificador único`

Una fila se identifica por documento, pestaña, número de fila e identificador único. SQLite impone restricciones únicas y bloqueos. Bitrix recibe además `ORIGINATOR_ID=SHEETS_BITRIX_SYNC` y un `ORIGIN_ID` determinista.

El orden de confirmación es deliberado:

1. Se bloquea la fila localmente.
2. Se comprueba si Bitrix ya conoce el origen.
3. Se crea la negociación si no existe.
4. Se guarda inmediatamente el ID en SQLite.
5. Se actualiza Google Sheets.

Si el paso 5 falla, el siguiente ciclo repara el Sheet usando el ID local y no crea una segunda negociación.

## 9. Diagnóstico

```bash
php bin/health.php
php bin/sync.php --force
```

Revise en el panel **Eventos y errores**. Los secretos se filtran, pero los mensajes de validación de Google y Bitrix se conservan para facilitar el diagnóstico.

Problemas frecuentes:

- `No se encuentra el JSON`: corrija `GOOGLE_CREDENTIALS_PATH` y permisos.
- `403` de Google: comparta el Sheet con `client_email` como editor y habilite Sheets API.
- Error de Bitrix: revise HTTPS, permisos del webhook, `CATEGORY_ID`, `STAGE_ID` y códigos `UF_CRM_...`.
- Base no escribible: asigne permisos de escritura al usuario PHP sobre `storage/`.
