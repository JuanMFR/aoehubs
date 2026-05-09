# Pendientes

Cosas que quedaron sin terminar / sin verificar al cierre de la fase de desarrollo single-user. Se dividen en tres categorías: **bugs/limitaciones conocidas**, **escenarios a probar con tráfico real**, y **features futuras**.

---

## Bugs / limitaciones conocidas

### 1. ✅ RESUELTO — Parser de replays funcional via mgz-fast
- **Antes**: `happyleavesaoc/aoc-mgz` upstream estaba atrasado vs el patch actual de DE (issue #138 abierto sin fix).
- **Solución**: migramos a `mgz-fast` ([github.com/AoEInsights/mgz-fast](https://github.com/AoEInsights/mgz-fast)), el fork que usa aoe2insights. Soporta el patch actual.
- **Como instalar**: `pip install mgz-fast` (no instalar `mgz` standalone — tienen el mismo nombre de package y conflictan).
- **API distinta**: en vez de la `Summary` de mgz vieja, ahora usamos `mgz.fast.header.parse(file)` que devuelve un dict del header + body iteration via `mgz.fast.operation()`. Output documentado en [scripts/parse_replay.py](../scripts/parse_replay.py).
- **Limitaciones del fork**: stripped-down (solo header y body iter, no high-level helpers). En particular, **no expone `winner` directo** — el winner del match hay que sacarlo iterando el body buscando eventos de Resign/Postgame, lo cual quedó pendiente. Por ahora usamos fallback al `result` del companion.

### 1.1 — ✅ RESUELTO — Winner detection desde el body del replay
- `scripts/parse_replay.py` ahora itera el body con `mgz.fast.operation()` y trackea: events `Action.RESIGN` (player_id), bloque `Operation.POSTGAME` con `leaderboards` (rank=1 = ganador).
- `MatchValidator::winnerUserId()` mapea `winner_player_num` del replay al `host_user_id`/`opponent_user_id` usando convención de slots (slot menor = host, porque AoE2 asigna slots en orden de join al lobby).
- Maneja matches vs Bot (1 humano) — distingue si ganó humano o AI.
- El campo `result` del companion se quitó del endpoint y de `UploadReplayAsync`. Detección de pantallas `GameEndWin`/`GameEndLoss` se eliminó del `ScreenDetector` por no fiable (el user puede skipear con "Salir al menú" desde pausa, alt+f4, fast-click, etc).
- Los archivos `screens/game_end_*.png` quedan en disco pero no se cargan más — pueden borrarse cuando el companion se redistribuya.

### 2. `chilecentral` no medible
- AoE2 lista la región pero Microsoft no tiene servicios PaaS resolvibles ahí (es región nueva). El `ServerPinger` la salta y queda fuera del reporte. Si un usuario en Chile querría jugar en chilecentral por ping bajo, hoy el matchmaker ni la considera.
- **Fix**: cuando Azure deploye más servicios en la región, agregar el endpoint a `ServerPinger.cs::Endpoints`. Mientras tanto: los chilenos juegan en el siguiente más cercano (`brazilsouth` o `southcentralus`), perdiendo algo de ping.

### 3. Detección de abort de lobby es time-based (60s)
- Cuando un user entra al Lobby y lo cierra sin empezar partida, el companion espera 60s antes de reportar abort. Es una heurística — el ideal sería instantáneo.
- **Fix**: agregar template para `MainMenu` / `MultiplayerBrowser` al `ScreenDetector`. Cuando se detecte transición `Lobby → MainMenu` con replayStarted=false, abortar inmediatamente.
- Por hacer: capturar screenshots de las dos pantallas en AoE2 vanilla y meterlos en `companion/screens/`.

### 4. Pings cacheados no chequean staleness
- Si un user abre el companion una vez (mide pings), nunca más lo abre, después se muda a otro país, sus pings cacheados quedan obsoletos. El matchmaker los usa igual.
- **Fix**: en `Matchmaking::pickOptimalServer`, ignorar `pings_json` si `pings_updated_at` > 24h. O forzar re-medición cuando entra a queue.
- Más importante en producción: bloquear queue si no hay pings frescos, y forzar al user a abrir el companion antes.

### 5. FIFO matchmaking, sin rating-based pairing
- Hoy el `Matchmaking::tryPair` agarra el `joined_at` más viejo. Funciona porque solo hay 2 users en la cola (vos + bot), pero a producción es problema: un 1500 puede emparejar contra un 2400.
- **Fix futuro**: rangos de rating expandibles con el tiempo de espera. Ej. al entrar buscar ±50 RD, después de 30s ±100, después 1min ±200, etc.

### 6. Bot Dev cuenta para rating
- Decidido al inicio. Cuando haya usuarios reales conviene reconsiderarlo: rateos contra IA inflan/deflanan ratings.
- **Fix**: en `CompanionApiController::uploadReplay`, si alguno de los participantes es el bot, saltear `applyGlicko2`. Igual para `ExpireStaleMatches` (forfeit no aplica vs bot, ya excluido).

### 7. ✅ RESUELTO — Anti-griefing / cooldowns progresivos
- `App\Services\CooldownService` con escalado en ventana móvil de 24h: 1ra=warning, 2da=5min, 3ra=30min, 4ta=2h, 5ta+=24h.
- Tabla `match_offenses` registra `LOBBY_ABORT` y `MID_GAME_DISCONNECT`.
- `User::isInCooldown()` + chequeo en `QueueController::join` y `Matchmaking::joinQueue`.

---

## Escenarios a probar con tráfico real (multi-user / multi-network)

Son cosas que single-user en localhost no permite verificar. Lista priorizada:

### Server selection por ping
- ✅ Componentes individuales testeados (smoke test del picker, pings desde mi IP funcionan)
- ❌ End-to-end con 2 users desde geografías distintas. Espero: matchmaker elige el server con menor max(host, opp).
- **Cómo testear**: 2 cuentas Steam, 2 máquinas en distintas regiones (LATAM + Europa, idealmente). Cada uno corre el companion → /api/companion/pings. Hacer cola simultáneamente. Verificar que el match resultante tenga `config_json.server` consistente con la lógica del picker.

### Forfeit por desconexión mid-game
- ✅ Lógica testeada con scenarios sintéticos en una sesión anterior (script `test_forfeit.php` que se borró post-test). Pasaron los 4 casos: PvP host vivo / opp stale → forfeit a host; PvP ambos stale → abandoned; vs bot humano vivo → no action; vs bot humano stale → abandoned.
- ❌ End-to-end real: 2 users en partida, uno cierra AoE2, el otro deja correr 5min, espera al cron.
- **Cómo testear**: 2 cuentas, 2 máquinas. Iniciar partida normal → uno alt+f4 → el otro queda en "esperando jugador" en AoE2 y NO declara victoria → confirmar que después de 5min el cron lo marca completed con winner=el que quedó, replay_path null, y el rating se actualiza.
- **Variante**: ambos cierran AoE2 a mitad → confirmar que va a `abandoned` sin rating change.

### Heartbeat real
- En dev el companion siempre puede llegar al backend (localhost). En prod hay que verificar que el heartbeat aguante:
  - Cortes de internet breves (5s - 30s) → companion recupera, hearbeat sigue
  - Latencia alta (300ms+) → 30s timeout del HttpClient debería ser suficiente
  - DNS issues, certificados SSL — testear con redes de varios ISPs

### Multi-companion concurrencia
- En una match de 2 humanos hay 2 companions ejecutando. Eventos a verificar:
  - Ambos detectan ReplayStarted casi simultáneamente → ambos POST `/match-started`. El segundo recibe `alreadyStarted: true`. ✓ (idempotente, ya está)
  - Ambos detectan ReplayFinalized casi simultáneamente → ambos suben replay. El primero gana, el segundo recibe 409. ✓ (ya está)
  - Edge: ¿qué pasa si el primero sube un replay corrupto y el segundo tenía uno bueno? El segundo recibe 409 y nunca sube → el match queda mal.
    - **Fix probable**: cambiar el endpoint para aceptar el segundo upload cuando el primero quedó en `pending_validation`. Riesgo de ataque: alguien sube basura primero adrede y rompe matches. Hay que pensarlo.

### Drafts con timeouts reales
- En dev los timeouts del map/civ draft funcionan, pero los probé puntualmente. Con tráfico real:
  - ¿Los timeouts disparan correctamente cuando un jugador se desconecta del navegador a mitad de draft? El polling cliente desaparece pero el backend procesa los timeouts vía la lógica `processBots()` del state endpoint... ¿se garantiza que se ejecuta sin alguien polleando?
  - **Verificar**: cerrar el navegador a mitad de un draft, esperar el timeout, ver que el otro user (con su navegador abierto polleando) ve la acción auto-aplicada.

### Companion con AoE2 modeado
- Toda la detección visual del companion (CreateLobby, Lobby, JoinPassword) usa templates de AoE2 vanilla. Con mods de UI (UserPatch, AoCnotes UI mods, etc.) puede romperse.
- **Política**: mod = match invalid (ya está en MatchValidator). Pero el companion ANTES de la match igual rompe — no detecta CreateLobby si el botón está movido.
- **Fix futuro**: detectar la presencia de mods en el client (¿cómo?) y avisarle al user que los desactive antes de jugar ranked.

### Diferentes resoluciones / fullscreen / windowed
- Probaste con tu resolución habitual. Otras resoluciones, windowed mode, monitores secundarios pueden romper la captura de pantalla del companion.
- **Verificar**: el `CaptureScreen()` usa `Screen.PrimaryScreen` — falla si AoE2 corre en un monitor secundario. Habría que detectar la ventana de AoE2 con `FindWindow` y capturar solo esa.

### Flujos de error desde el companion
- ¿Qué pasa si el token expira o se invalida? El companion sigue intentando con 401s. No hay UX para "tu sesión venció, regenerá el token".
- **Fix**: si HttpClient devuelve 401, mostrar un dialog en el companion pidiendo re-pegar el token desde la web.

---

## Features futuras (priorizadas)

### A — Companion como instalador (Phase c)
- ✅ c.1 Self-contained single-file publish (`companion/publish.ps1`)
- ✅ c.2 Inno Setup installer (`companion/installer.iss` → `dist/AoE2CompanionSetup-X.Y.Z.exe`)
- ❌ c.3 Auto-update con [Velopack](https://github.com/velopack/velopack) — pospuesto. Reemplaza el setup actual por uno de Velopack que después self-update vía un release feed (GitHub Releases o HTTP). Hace falta cuando haya >10 beta testers y avisarles uno por uno para cada update sea fricción real. Por ahora con manda-el-setup-por-Discord alcanza.
- ❌ c.4 Code-signing — pospuesto, requiere comprar cert (~$100-500/año). Detalles del proceso en `PRODUCTION.md`. Hook listo en `publish.ps1` para activarlo cuando haya cert.

### B — Steam Web API integration ✅ código listo, ❌ bloqueado por dominio
- ✅ `app/Services/SteamProfile.php` — llama a `ISteamUser/GetPlayerSummaries` (TTL 24h)
- ✅ `AuthController` invoca el refresh al loguear
- ✅ Avatar visible en header + placeholder con la inicial cuando no hay
- ✅ Comando `php artisan users:refresh-profiles` para forzar refresh masivo
- ✅ `User::isBot()` excluye al bot de cualquier intento de refresh
- ❌ **Bloqueado**: Steam Web API key requiere un dominio asociado para emitirse (Steam tightened esto hace 1-2 años; ya no acepta `localhost` ni placeholders genéricos como antes). Hasta no comprar el dominio para el deploy del backend, no se puede activar — los users quedan con `persona_name=null` y `avatar_url=null`. Las views muestran fallbacks correctos (steam_id truncado o inicial en círculo).
- **Cómo destrabar cuando haya dominio**: `STEAM_API_KEY=...` en `.env` → `php artisan config:clear` → `php artisan users:refresh-profiles`. Listo.

### B.1 — Historial de nicknames (estilo aoe2insights) — FUTURO
- Steam Web API NO da directamente el historial — sitios como aoe2.net/aoe2insights lo construyen ellos mismos comparando snapshots a lo largo del tiempo.
- Plan: tabla `user_personanames(user_id, name, first_seen_at, last_seen_at)`. En `SteamProfile::refresh()`, antes de actualizar, si el `personaname` actual no está en el historial → insertar nuevo row. Mostrar como "Aliases conocidos" en `/users/{steamId}`.
- Schedule `users:refresh-profiles` a frecuencia diaria así el historial se va poblando solo.
- **Bloqueado por lo mismo (B)** — sin API key no hay cómo testear ni acumular data útil. Implementar después de B.

### C — Profile pages públicas (`/users/{steamId}`)
- Stats, historial reciente, civs más jugadas, mapas, win rates por civ/map.
- Solo Glicko-2 + matches existing data, no necesita features nuevas backend.

### D — Match history con filtros + paginación
- Hoy `/matches` muestra todos sin paginación. Romperá con muchos matches.
- Filtros básicos: por status, por opponent, por map, por civ.

### E — Queue UI
- Hoy entrás a la cola y la página queda igual. Conviene un polling con "buscando rival... 0:34" + posición en cola.
- Backend: `QueueController::status` ya existe — solo falta UX en el dashboard.

### F — ✅ RESUELTO — Anti-griefing cooldowns
- Ver bug #7 más arriba.

### G — Detección de mods en client
- Ver "Companion con AoE2 modeado".

### H — ✅ RESUELTO via migración a mgz-fast
- Ver bug #1.

### I — ✅ RESUELTO — Refactor: eliminar duplicación de Glicko-2 + persistencia
- Extraído a `GameMatch::applyRatingChange(int $winnerUserId)` con guard de idempotencia interno (chequea `host_rating_change !== null`).
- Los 4 callers (`CompanionApiController::uploadReplay`, `ExpireStaleMatches`, `ReprocessPendingMatches`, `AdminController`) lo invocan en 1 línea bajo `lockForUpdate` para protección TOCTOU.

### J — Sistema admin: features extras útiles para debugging
- Vista admin tiene overview + users + matches + match-detail. Cosas que faltarían en producción:
  - Force-promote a player (saltear el flujo Steam OpenID para crear test users)
  - Ver tokens activos por user (Sanctum personal_access_tokens) + revocar individuales
  - Ver pings_json de un user específico (hoy no se muestra en ningún lado)
  - Ban / unban users (estado nuevo `role='banned'` que el queue rechaza)
  - Botón "ejecutar `matches:reprocess-pending` ahora" en overview (sin esperar al cron)
- No urgente — la estructura admin ya está y agregar más vistas es trivial.

### K — Verificar companion con cambios recientes en heartbeats
- La columna `last_heartbeat_at` se reemplazó por `host_heartbeat_at` + `opponent_heartbeat_at`. El backend está actualizado, pero **el companion local (.exe corriendo) no rebuildeó después de ese cambio**. La API en sí no cambió (sigue siendo POST `/heartbeat` con `matchId`), pero el comportamiento puede haber cambiado.
- **Verificar**: rebuildear companion (`pwsh publish.ps1`), arrancarlo con un match activo, confirmar que: (a) el heartbeat sigue llegando, (b) en `/admin/matches/{id}` se ve la columna correcta poblándose según el role del user que corre el companion.

### L — ✅ RESUELTO — Distinguir replay completo de replay truncado
- `completed` ahora se determina por la presencia del bloque `Operation.POSTGAME` en el body — solo se escribe cuando AoE2 termina la partida con condición de victoria. Alt+f4 / "Salir al menú" desde pausa = no postgame = `completed: false`.
- `MatchValidator` rechaza replays con `completed: false`.
- Smoke test confirmado: match #30 (alt+f4) ahora reporta `saw_postgame: false`, `completed: false`, `winner_player_num: null`. Match queda `invalid` correctamente.

### M — PENDIENTE — Companion no está aplicando la config del draft en AoE2
- **Síntoma observado en match #30 y #31**: el draft de civ + map se completa correctamente en la web, pero cuando vas a la partida real en AoE2, jugás con civs/mapas distintos a los del draft. El validator detecta el mismatch y marca el match `invalid`.
  - Match #30: draft = Japanese/Aztecs en Nomad → jugó Georgians en Arena
  - Match #31: draft = Celts en Mediterranean → jugó Georgians en Arena
- **Hipótesis**: el `LobbyConfigurator` y/o `LobbyCorrector` del companion no logra aplicar las diferencias de la config del lobby antes de que el user inicie la partida. Posibles causas:
  - El companion no espera lo suficiente entre Restablecer y Apply
  - OCR del lobby falla en algún paso y el companion piensa que ya aplicó cambios
  - El user clickea "Iniciar partida" antes de que el companion termine
  - La config de civ del jugador es selectable solo después de que entra al lobby pero el companion no entra a configurarla
- **Para debuggear**: rebuildear companion, abrir partida vs bot, mirar el log del companion para ver qué pasos hizo entre el lobby y el inicio de partida. Buscar mensajes de `[Restablecer]`, `LobbyComparator.LogDiffs`, `LobbyCorrector.ApplyAsync`.
- **Bloqueante para beta**: SÍ. Sin esto, todas las matches van a quedar `invalid` por mismatch de civs/mapa.

### N — Map fingerprint refactor: follow-ups
Implementado en commit `a742aa8` (validación por `rms_map_id` para vanilla / `rms_filename` para custom, dispatch por flag `is_custom`). Quedan tres follow-ups:

- **N.1 — Calcular `rms_hash` en `parse_replay.py`**: el validator ya tiene la rama lista (`mapMismatchCustom` chequea `hash_equals` si `parsed['rms_hash']` existe). Hace falta extraer el contenido del `.rms` referenciado por el rec y devolver su `sha256`. Solo necesario cuando armemos el primer pack de pro-maps con integridad.
- **N.2 — Switch UI ES/EN**: los campos `name_es`/`name_en` ya se persisten en `maps`, pero el blade sigue usando `__($map->name)` con `lang/es.json` (legacy). Cambiar el resolver a `$map->name_es ?? __($map->name) ?? $map->name` cuando se agregue toggle de locale al user.
- **N.3 — `map_drafts.map_id` FK**: hoy `map_drafts.final_map` guarda string. Si admin renombra un mapa, drafts viejos quedan colgando. Refactor: agregar FK `map_id` que apunte a `maps.id`, y derivar `final_map` (string) solo para display. Más invasivo — tocar `MapDraftController`, validator, view drafts. Worth it cuando el pool empiece a tener turnover real.

### O — Map pool voting (community-driven map rotation)
Inspirado en el sistema de votación de pool de AoE2 ranked oficial. Permitir a la comunidad votar la próxima rotación de mapas desde admin.

Diseño aún por definir — ver discusión abierta. Escenarios principales:
- Frecuencia: por season, mensual, ad-hoc.
- Mecánica de voto: top-N por user (multi-select) vs single-vote.
- Auto-rotación: ¿el cierre de votación reemplaza pool automáticamente o requiere confirmación admin?
- Coexistencia con eventos pro-pack (item N): la votación tiene que poder pausarse/cancelarse cuando el admin quiera forzar un pool especial.

### L.1 — PENDIENTE — Allow second upload to override (replay race)
- **Pendiente**: cuando un user alt+f4 mid-partida, el companion sube un replay incompleto que va a `invalid` (correcto, sin rating change). PERO si el OTRO companion sube su replay completo después, el endpoint lo rechaza con 409 (match ya resuelto).
- **Fix**: permitir un segundo upload del OTRO participante si el primero quedó como `invalid`. Restricción anti-spam: solo el otro user, una sola vez. Si el segundo replay parsea bien y tiene winner, sobreescribe → `completed`.
- Sin esto, en producción cada vez que un user alt+f4 mid-partida, el otro pierde el rating ganado aunque tenga el replay completo en su lado. Fricción real.
- Alcance estimado: 30-45min. Cambio chico en `uploadReplay` (allow upload sobre status `invalid` por el otro user) + lógica de "reverse rating" si la nueva resolución cambia el resultado.

---

## Cosas que dependen de testing real antes de tocar

- **No tocar el algoritmo de selección de server hasta no haber probado con 2+ users en distintas geografías**. Es fácil que el "fix" rompa el caso real.
- **No agregar pairing por rating hasta no haber visto cómo se distribuye la población real de ratings**. Diseñar el rango de expansión sin datos es adivinar.
- **No agregar más reglas al MatchValidator hasta no tener parser funcionando**. Hoy ni siquiera sabemos qué keys exactas devuelve `mgz::get_settings()` en nuestro entorno.

---

## Cómo navegar este doc

Cuando vuelvas a esta tarea (o entre alguien nuevo al proyecto):
1. Leer `ARCHITECTURE.md` primero — overview de cómo funciona.
2. Antes de cualquier cambio en producción, leer `PRODUCTION.md` — checklist.
3. Cuando algo no funciona como esperás, chequear este doc — probablemente esté listado como limitación conocida.
