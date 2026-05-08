# Producción

Pasos y consideraciones para llevar el proyecto a un server real (linux + dominio + SSL).

## Stack mínimo del server

- **PHP 8.2+** con extensiones: pdo, pdo_mysql/pdo_pgsql, gd, mbstring, zip, openssl, intl, json
- **Web server**: nginx + php-fpm (recomendado), o Apache
- **DB**: MariaDB 10.6+ o MySQL 8 o Postgres 14+ (migrar desde SQLite — ver más abajo)
- **Composer 2.x**
- **Node.js 20+ y npm** (solo para `npm run build` que compila Tailwind/Vite — una vez por release)
- **Python 3.11+** + `pip install mgz-fast` (fork de aoe2insights — soporta el patch actual de DE; el `mgz` upstream está atrasado y NO funciona)
- **Cron** del sistema (Linux: cron estándar; Windows: Task Scheduler)

## Setup paso a paso

### 1. Clonar + dependencias

```bash
git clone <repo>
cd companionweb
composer install --no-dev --optimize-autoloader

# Frontend: instalar deps + compilar Tailwind/Vite a public/build/
npm install
npm run build
```

`npm run build` produce `public/build/manifest.json` + assets CSS/JS minificados.
Sin esto, `@vite()` en los blade templates no encuentra los archivos y la web
se ve sin estilos. **Necesario una vez por deploy** (cuando cambia el código del
front: vistas o resources/css). Si solo se cambia código PHP, no hace falta
rebuildear el front.

> En servers chicos (VPS de 1-2 GB de RAM) `npm run build` puede consumir bastante.
> Si pasa eso, podés buildear local + commitear `public/build/`, o usar un CI
> que builde y suba el artifact.

### 2. Configurar `.env`

Copiar `.env.example` a `.env`, generar `APP_KEY`, setear:

```ini
APP_NAME=AoE2Rank
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=aoe2rank
DB_USERNAME=...
DB_PASSWORD=...

# Steam Web API key. Opcional pero recomendado para producción — sin ella
# los usuarios quedan sin nombre/avatar (solo SteamID truncado en la UI).
# Para conseguirla: https://steamcommunity.com/dev/apikey (requiere dominio
# asociado y cuenta Steam logueada; gratis).
# El login con Steam OpenID NO la necesita; la key es solo para enriquecer
# perfiles vía ISteamUser/GetPlayerSummaries.
STEAM_API_KEY=

# Path al python del server. Si está en PATH como `python3`, dejarlo así.
PYTHON_BIN=python3

# Disco de Laravel para los replays. Local es OK al inicio; si crece, mover a S3.
FILESYSTEM_DISK=local
```

### 3. Migrar DB

```bash
php artisan migrate --force
```

Si se trae data del dev (SQLite), exportar/importar con `php artisan tinker` o usar un dump.

### 4. Storage link

```bash
php artisan storage:link
```

(No estrictamente necesario porque los replays están en `private/`, pero buena práctica.)

### 5. Cache de configuración

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. Permisos

`storage/` y `bootstrap/cache/` deben ser escribibles por el user del web server (ej. `www-data`).

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 7. Cron del sistema

Sin esto, los comandos `matches:expire-stale` y `matches:reprocess-pending` nunca corren. **Crítico** para que el sistema limpie zombies y reintente parseo.

**Linux**:

```bash
crontab -e
# agregar:
* * * * * cd /var/www/aoe2rank && php artisan schedule:run >> /dev/null 2>&1
```

**Windows Server** (Programador de tareas):

- Acción: `C:\php\php.exe`
- Argumentos: `C:\inetpub\aoe2rank\artisan schedule:run`
- Disparador: cada 1 minuto, indefinidamente
- Sin ventana de consola (`Ejecutar oculto`)

### 8. Verificar el cron está corriendo

```bash
php artisan schedule:list   # debería listar matches:expire-stale + matches:reprocess-pending
tail -f storage/logs/laravel.log  # buscar entries de los comandos
```

Si pasan 5min sin entradas relacionadas a `matches:`, el cron del SO no está disparando `schedule:run` — revisar el cronjob.

### 9. Steam OpenID — callback URL

En el código (`app/Http/Controllers/AuthController.php`) el callback se arma con `route('auth.steam.callback')`. Asegurarse que `APP_URL` esté correcto en `.env` y que el dominio sea HTTPS — Steam requiere HTTPS para el flow OpenID.

### 10. Promover el primer admin

Apenas el primer user se loguea con Steam queda como `role=player`. Para tener acceso a `/admin/*` hay que promocionarlo manualmente desde CLI:

```bash
# Tu SteamID64 lo encontrás en steamid.io o en la URL de tu perfil de Steam
php artisan users:promote 76561198xxxxxxxxx --role=admin
```

Después podés gestionar el resto desde `/admin/users` (botón "Hacer admin" / "Quitar admin"). El sistema impide que te saques el rol a vos mismo, pero entre admins se pueden demover entre sí — cuidado si vas a tener un solo admin.

## Distribución del companion

### Estado actual

Implementado en fase c.1 + c.2. Para generar la build distribuible:

```powershell
cd companion
.\publish.ps1
```

Outputs:
- `companion/publish/` — carpeta portable (124MB exe + screens/ + tessdata/ = 144MB total). Para uso "extraer y correr".
- `companion/dist/AoE2CompanionSetup-X.Y.Z.exe` — instalador Inno Setup (135MB). Si Inno Setup está instalado, `publish.ps1` lo compila automáticamente.

El instalador va a `%LOCALAPPDATA%\Programs\AoE2Companion\` (no requiere admin), crea shortcut en Start Menu, registra desinstalador en "Agregar/quitar programas". El config del usuario (token, URL del backend) vive separado en `%APPDATA%\AoE2Companion\config.json` para que reinstalaciones no rompan la sesión.

Para nueva versión: bumpear `MyAppVersion` en [companion/installer.iss](../companion/installer.iss), correr `publish.ps1`, distribuir el nuevo `setup.exe`.

### Code signing (pendiente)

**Por qué importa:** sin firma, Windows SmartScreen muestra una pantalla naranja "Microsoft Defender SmartScreen impidió el inicio de una aplicación no reconocida" cuando un usuario corre el setup. El usuario tiene que clickear "Más información → Ejecutar de todos modos" para continuar. Esto es fricción real — muchos beta testers no van a saber qué hacer.

**Tipos de certificado Authenticode:**

| Tipo | Costo aprox/año | Validación | Cómo funciona | SmartScreen |
|------|-----------------|------------|---------------|-------------|
| Self-signed | $0 | ninguna | `New-SelfSignedCertificate` PowerShell | **NO sirve** — sigue mostrando warning |
| OV (Organization Validation) | $80-300 | docs de empresa/identidad, llamada telefónica | Cert almacenado en archivo .pfx | Reduce warning, gana "reputation" después de N descargas |
| EV (Extended Validation) | $300-500 | misma OV + validación reforzada | Cert en hardware token USB (HSM) | **Sin warning desde día 1** — instant reputation |

**Recomendación:** EV cert para producción (DigiCert, Sectigo, SSL.com). Si presupuesto ajustado, OV cert al menos.

**Proveedores que se pueden barajear (al momento de escribir):**
- DigiCert — $400-700/año, soporte sólido
- Sectigo (ex Comodo) — $200-400/año, popular
- SSL.com — $150-300/año, suele tener promos
- SignMyCode (reseller) — más barato, ~$100/año

**Una vez que tenés el cert (.pfx para OV, hardware token para EV):**

`publish.ps1` ya tiene el hook listo. Setear variables de entorno:

```powershell
$env:SIGN_CERT_PATH = "C:\path\to\codesign.pfx"   # ruta al .pfx (OV)
$env:SIGN_CERT_PASS = "tu-password-del-pfx"        # password del .pfx
# o, para EV con hardware token:
$env:SIGN_CERT_THUMBPRINT = "abc123..."            # thumbprint del cert en el token

.\publish.ps1
```

El script detecta las variables, firma el `AoE2Companion.exe` antes de empaquetarlo en Inno Setup, y firma el `AoE2CompanionSetup-X.Y.Z.exe` final. Ambos quedan validables con `signtool verify /pa /v file.exe`.

**Timestamping:** importante. Sin timestamp, la firma se invalida cuando el cert expira (1 año típico). Con timestamp, las builds firmadas siguen válidas indefinidamente. `publish.ps1` siempre firma con `/tr http://timestamp.digicert.com /td sha256` (gratis, público).

**Build reputation (caso OV):** durante las primeras semanas/meses con un cert OV nuevo, SmartScreen sigue mostrando warning aunque firmado. Microsoft mide cuántas descargas tiene tu cert + cuántos usuarios "Run anyway"-eron sin reportar — después de un threshold (típicamente cientos de descargas únicas), SmartScreen confía y deja de avisar. Por eso para una beta chica un OV cert sigue mostrando warning hasta que acumule reputation. EV salta este proceso completamente.

### Auto-update (pendiente — Velopack)

Para releases continuos sin que el usuario tenga que reinstalar a mano. Documentado en `PENDING.md` sección A. Resumen del trabajo: reemplazar Inno Setup por Velopack (no son compatibles entre sí), agregar `VelopackApp.Build().Run()` al `Program.cs`, hostear releases en GitHub Releases o S3.

Vale la pena cuando haya >10 beta testers o flujo continuo de updates. Por ahora distribución manual del `setup.exe` por Discord/email alcanza.

## Operaciones / monitoreo

### Storage de replays

Cada match completed deja un .aoe2record (típicamente 200KB - 2MB). Para 100 matches/día son 20-200MB/día — manejable en disco local por meses.

Si crece mucho:
- Migrar a S3-compatible storage (MinIO, Backblaze, AWS S3)
- En `config/filesystems.php` configurar disk `s3`
- En `CompanionApiController::uploadReplay` cambiar `'local'` a `'s3'` y en `ReprocessPendingMatches` igual

Consideración: replays de matches inválidos / abandonados podrían eliminarse después de N meses. No hay job de cleanup todavía.

### Logs

`storage/logs/laravel.log` — todo lo de Laravel (incluyendo errores del parser cuando falla).

Buscar regularmente:
- `ReplayParser exception` — problemas de infraestructura del parser (Python no encontrado, timeout)
- entries de `match #N` con status irregular

### Métricas a vigilar

Cuando haya tráfico real:
- % matches que llegan a `completed` vs `invalid` vs `pending_validation` vs `abandoned`
  - High `pending_validation` rate → mgz desactualizado, hay que patchearlo / esperar upstream
  - High `invalid` rate → posible problema de validación demasiado estricta o griefing real
  - High `abandoned` rate → desconexiones / heartbeat issues
- Tiempo promedio en cola
- % de matches con forfeit
- Tamaño promedio del replay

(Nada de esto está instrumentado todavía. Métrica básica: query SQL sobre tabla matches agrupando por status.)

## Migración de SQLite a MariaDB / Postgres

Las migraciones que escribimos son portables (no usan tipos específicos de SQLite). El proceso:

1. En `.env` cambiar `DB_CONNECTION=sqlite` → `DB_CONNECTION=mysql` (o `pgsql`)
2. Configurar credenciales
3. Crear DB vacía
4. `php artisan migrate --force`
5. (Opcional) reimportar data del dev — ej. `php artisan db:seed` o exportar tabla por tabla

No hay datos productivos que migrar — el desarrollo arrancó en SQLite y los matches del dev no son relevantes para producción.
