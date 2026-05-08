# Arquitectura

Plataforma ranked competitiva 1v1 para AoE2 DE. Inspirada en Faceit/GamersClub.

## Componentes

- **Web (Laravel 12 / PHP 8.2 + Tailwind v4 + Vite)** — `C:/xampp/htdocs/companionweb`
  - Auth Steam OpenID, queue de matchmaking, drafts (mapas + civs), API para companion, leaderboard, historial, panel admin
  - Frontend: layout compartido `resources/views/layouts/app.blade.php` que extienden todas las views; design tokens y componentes en `resources/css/app.css` (compilado a `public/build/` por Vite)
- **Companion (.NET 8 / C#)** — `c:/xampp-56/htdocs/aoe2rank/companion`
  - Console app + WinForms para setup. Automatiza AoE2 DE: configura lobby (OCR + SendInput), une joiner via `aoe2de://`, detecta inicio/fin de partida, sube replay
  - Distribución: `publish.ps1` produce un single-file .exe + Inno Setup installer (`AoE2CompanionSetup-X.Y.Z.exe`)
- **DB** — SQLite en dev (`database/database.sqlite`). Plan: MariaDB/Postgres en producción
- **Parser de replay (Python + mgz-fast)** — `scripts/parse_replay.py`, invocado vía `Symfony\Process` desde `app/Services/ReplayParser.php`. Usa `mgz-fast` (fork de aoe2insights, soporta el patch actual de DE — `pip install mgz-fast`).
- **Storage de replays** — disco local Laravel en `storage/app/private/replays/`

## Modelo de usuarios

Roles minimal:
- **`player`** (default al loguear con Steam) — acceso normal: queue, drafts, ver sus matches, leaderboard
- **`admin`** — además de lo anterior, accede a `/admin/*` (overview, listar todos los users con promote/demote, listar todos los matches con force-cancel + reprocesar)

Promote/demote: `php artisan users:promote {steam_id} --role=admin|player` (CLI), o desde `/admin/users` si ya sos admin (no podés sacarte el rol a vos mismo).

Datos del user vienen de dos fuentes:
- **Steam OpenID** (`AuthController::handleSteamCallback`): provee solo el `steam_id` (SteamID64). Si es la primera vez, crea el user con `role=player`.
- **Steam Web API** (`SteamProfile::refresh`): si hay `STEAM_API_KEY` en `.env`, levanta `persona_name` + `avatar_url`. TTL 24h. Si no hay key, esos campos quedan null y la web muestra placeholder/SteamID truncado. **Opcional pero recomendado para producción.**

## Flujo punta a punta

```
[user clickea "Buscar partida"]
        ↓
[QueueController::join] → matchea con bot dev o con otro real
        ↓
[Matchmaking::createMatch] → status=drafting, server elegido por ping
        ↓
[Map draft] → 10 maps, bans alternados, 30s timeout/turno
        ↓
[Civ draft] → fase 1: cada uno picks 4 civs; fase 2: cada uno bans 2 del rival; fase 3: cada uno final pick. 60s/fase
        ↓
[status = pending] → companion del host lee /api/companion/match
        ↓
[host companion] → en CreateLobby: configura via OCR. En Lobby: Restablecer + apply diffs + reporta lobby_id
        ↓
[joiner companion] → polling ve lobby_id, abre aoe2de://0/{id}, completa password
        ↓
[ambos clickean "Iniciar"] → AoE2 escribe .aoe2record → ReplayWatcher Created → /api/companion/match-started → status=in_progress
        ↓
[partida en curso] → companions hartbeatean cada 30s → host_heartbeat_at / opponent_heartbeat_at
        ↓
[partida termina] → AoE2 cierra el .aoe2record → ReplayWatcher detecta estable >10s → /api/companion/replay (multipart upload)
        ↓
[backend] → ReplayParser (mgz) → MatchValidator → Glicko-2 → status=completed (o invalid, o pending_validation)
```

## Máquina de estados de un match

```
       ┌───────────────┐
       │   drafting    │  drafts en curso
       └──────┬────────┘
              ▼
       ┌───────────────┐  drafts done; host arma lobby
       │   pending     │
       └──────┬────────┘
              ▼ (companion ve .aoe2record Created)
       ┌─────────────────┐
       │  in_progress    │  partida realmente arrancó
       └──────┬──────────┘
              │ (companion sube replay finalizada)
              ▼
   ┌──────────┴──────────────────────┐
   ▼                                  ▼
┌──────────┐                  ┌─────────────────────┐
│ completed│ parser+validator │ pending_validation  │ parser falló (mgz vs DE patch)
└──────────┘  OK              │                     │ → reintenta `matches:reprocess-pending`
   │                          └─────────────────────┘
   │                                   │
   ▼                                   ▼ (cuando mgz se actualice)
[Glicko-2 aplicado]           [completed | invalid]

         ┌──────────────────────┐
         │      invalid         │  parser OK pero validación falló
         └──────────────────────┘  (mods, civs cambiadas, settings, etc)
                                   sin rating, final.

         ┌──────────────────────┐
         │     abandoned        │  lobby cerrado sin jugar / timeout / corte mutuo
         └──────────────────────┘  sin rating, final.
```

Forfeit (walkover): si en `in_progress` un lado deja de heartbeatear y el otro sigue vivo por 5min, el cron `matches:expire-stale` aplica forfeit. El vivo gana, Glicko-2 normal, replay_path queda null (marker de walkover).

## Endpoints API (auth via Sanctum, prefix `/api/companion/`)

| Método | Path             | Quién llama | Para qué |
|--------|------------------|-------------|----------|
| GET    | `match`          | companion   | Pedir match activa del user (pending o in_progress) |
| POST   | `pings`          | companion   | Reportar latencias medidas a las regiones Azure |
| POST   | `lobby-ready`    | host        | Reportar lobby_id que extrajo del header AoE2 |
| POST   | `match-started`  | cualquiera  | Confirmar que apareció el .aoe2record (transición pending → in_progress) |
| POST   | `match-aborted`  | cualquiera  | Reportar que el lobby se cerró sin jugar |
| POST   | `heartbeat`      | cualquiera  | Pulso de vida (cada 30s mientras hay match) |
| POST   | `replay`         | cualquiera  | Subir el .aoe2record (multipart) |

## Rutas web (auth web Steam, prefix `/`)

Para usuarios logueados:
- `/dashboard` — CTA queue, stats personales, gestión de companion token
- `/matches` — historial propio + acciones (test rápido vs bot, crear manual)
- `/leaderboard` — top 50 (público; sin login muestra solo lectura)
- `/queue/{join,leave,status}` — control de cola
- `/matches/{id}/draft/maps` y `/matches/{id}/draft/civs` — UI de drafts (con polling JS cada 1s)
- `/companion/token` — generar token Sanctum nuevo (revoca el anterior)

Solo admins (middleware `admin`):
- `/admin` — overview con counts globales, queue size, últimos matches/users
- `/admin/users` — listar/buscar/paginar usuarios + promote/demote
- `/admin/matches` — listar TODOS los matches con filtros por status + force-cancel + reprocesar
- `/admin/matches/{id}` — detalle: parsed_metadata, validation_errors, ciclo de vida, drafts, replay

## Servicios principales (PHP)

- `Matchmaking` — queue, pareo, server-by-ping (`pickOptimalServer`)
- `Glicko2` — algoritmo completo con Illinois solver para volatility
- `ReplayParser` — wrapper PHP del subprocess Python
- `MatchValidator` — 7 reglas de validación (identidad, mapa, civs, mods, diplomacia, completed, settings)
- `SteamProfile` — refresca persona_name + avatar via Steam Web API (no-op si no hay `STEAM_API_KEY`)

## Servicios principales (companion C#)

- `ScreenDetector` — template matching de pantallas (CreateLobby, Lobby, JoinPassword, GameEndWin/Loss)
- `LobbyConfigurator` — flujo Click+Tab para configurar el dialogo "Crear sala"
- `LobbyInspector` / `LobbyCorrector` / `LobbyComparator` — OCR del lobby, diff vs config esperada, aplicar correcciones
- `JoinPasswordHandler` — OCR-less: click + Tab + type + Tab + Enter
- `ReplayWatcher` — `FileSystemWatcher` recursivo sobre `Games/Age of Empires 2 DE/*/savegame/*.aoe2record`
- `ServerPinger` — TCP connect a port 443 a endpoints regionales de Azure cognitive services
- `MatchApiClient` — todos los POSTs/GETs hacia el backend
- `Program.cs` — main loop: detector cada 500ms, polling de match cada 5s, heartbeat cada 30s, pings al startup + cada 30min

## Comandos artisan

| Comando | Schedule | Para qué |
|---------|----------|----------|
| `matches:expire-stale`        | every minute | Marca abandonadas las matches sin heartbeat / aplica forfeit |
| `matches:reprocess-pending`   | hourly       | Reintenta parsear replays que quedaron en pending_validation cuando mgz se actualice |
| `users:promote {steam_id}`    | manual       | Cambiar role de un user (`--role=admin\|player`). Para crear el primer admin tras deploy |
