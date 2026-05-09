<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Map;
use Illuminate\Support\Facades\Log;

/**
 * Compara la metadata parseada de un .aoe2record (output de scripts/parse_replay.py
 * usando mgz-fast) contra el draft + reglas de ranked. Devuelve la lista de
 * errores; vacio = match valido.
 *
 * Estos chequeos son la red anti-griefing/anti-cheat: aunque un host modifique
 * la sala antes de iniciar (mapa cambiado, civ cambiada, mod cargado), la
 * validacion lo detecta y el match queda 'invalid' sin afectar rating.
 *
 * Filosofia: mejor permitir un match dudoso por error de validacion (falso
 * positivo) que invalidar uno legitimo. Cuando no estamos seguros (campo
 * faltante en el output), warning en logs en vez de error duro.
 */
class MatchValidator
{
    /**
     * Settings obligatorios para ranked. Las claves matchean lo que devuelve
     * scripts/parse_replay.py en `settings.*`. Valores esperados son los
     * defaults de un 1v1 competitivo.
     *
     * NO se valida `reveal_map`/`exploration` porque varia por mapa (Selva
     * Negra: Todo visible; Arena: Explorado; otros: Normal). Es preferencia
     * del host y no afecta el balance competitivo. El companion tampoco lo
     * fuerza al setear el lobby.
     */
    private const RANKED_SETTINGS = [
        'population_limit' => 200,
        'lock_teams'       => true,
        'lock_speed'       => true,
        'cheats'           => false,
        'treaty_length'    => 0,
        'multiplayer'      => true,
    ];

    /**
     * @return string[] Lista de errores. Vacio si el match es valido.
     */
    public static function validate(GameMatch $match, array $parsed): array
    {
        $errors = [];

        $humans = $parsed['humans'] ?? [];

        // 1) Game completed: si la iteracion del body no llego a EOF, hubo
        //    truncate (alt+f4 mid-game, crash, etc).
        if (isset($parsed['completed']) && $parsed['completed'] === false) {
            $errors[] = "La partida no terminó correctamente (cierre forzado o crash).";
        }

        // 2) Cantidad de humans esperada. PvP real = 2; vs bot = 1 humano + AI.
        $expectedHumans = ($match->host->isBot() || $match->opponent?->isBot()) ? 1 : 2;
        if (count($humans) !== $expectedHumans) {
            $errors[] = "El replay no muestra la cantidad correcta de jugadores reales.";
        }

        // 3) Civs: validamos que las civs del replay coincidan con las del draft.
        $civDraft = $match->civDraft;
        if ($civDraft !== null && ! empty($civDraft->host_final_civ) && ! empty($civDraft->opponent_final_civ)) {
            $expected = [self::norm($civDraft->host_final_civ), self::norm($civDraft->opponent_final_civ)];
            sort($expected);
            $actualCivs = array_filter(array_map(fn ($h) => self::norm($h['civilization'] ?? ''), $humans));

            if ($expectedHumans === 1) {
                $hostExpected = self::norm($civDraft->host_final_civ);
                $humanCiv     = $actualCivs[0] ?? null;
                if ($humanCiv !== $hostExpected) {
                    $played = $humans[0]['civilization'] ?? '?';
                    $errors[] = "Jugaste con {$played} en lugar de {$civDraft->host_final_civ} (la civ que elegiste en el draft).";
                }
            } else {
                $actualSorted = $actualCivs;
                sort($actualSorted);
                if ($expected !== $actualSorted) {
                    $errors[] = "Las civilizaciones del replay no coinciden con las del draft.";
                }
            }
        }

        // 4) Mapa: validacion estructural por fingerprint, no por nombre.
        //
        // El nombre que devuelve mgz (`map_name`) viene de DE_MAP_NAMES, una
        // tabla hardcoded dentro de la libreria. Si mgz queda atrasado vs un
        // patch de DE, los nombres se desfasan y rompen la comparacion contra
        // el nombre del draft. La fuente confiable es lo que escribe el juego
        // mismo en el .aoe2record:
        //
        //   - Para mapas vanilla (built-in del juego): `rms_map_id`. Es un
        //     entero de enum hardcoded en DE (Arabia=9, Nomad=33, ...) que
        //     NO varia entre clientes ni se ensucia con map packs del Workshop.
        //     Comparamos `parsed.rms_map_id == map.rms_map_id`.
        //
        //   - Para mapas custom (un pack distribuido por nosotros): el
        //     `rms_map_id` suele ser un sentinel compartido (CUSTOM=59), asi
        //     que comparamos por `rms_filename`. Si la fila tiene `rms_hash`
        //     guardado, ademas chequeamos integridad del .rms — pero ese
        //     hash hoy no se calcula (placeholder para pro-maps futuros).
        //
        // El `mapDraft.final_map` es el nombre canonical (string `Map.name`)
        // elegido en el draft. Resolvemos a la fila Map y dispatch por
        // is_custom.
        $mapDraft = $match->mapDraft;
        if ($mapDraft !== null && ! empty($mapDraft->final_map)) {
            $expectedMap = Map::where('name', $mapDraft->final_map)->first();

            if ($expectedMap === null) {
                // Defensivo: no deberia pasar (el draft solo permite mapas del
                // pool activo). Si pasa, marcamos error en lugar de aprobar.
                $errors[] = "El mapa '{$mapDraft->final_map}' del draft ya no esta en el pool — re-creá el mapa en admin antes de validar.";
            } else {
                $mismatch = $expectedMap->is_custom
                    ? self::mapMismatchCustom($expectedMap, $parsed)
                    : self::mapMismatchVanilla($expectedMap, $parsed);

                if ($mismatch !== null) {
                    $errors[] = $mismatch;
                }
            }
        }

        // 5) Mods: ranked es vanilla.
        $mod = $parsed['mod'] ?? '';
        if ($mod !== '' && $mod !== null) {
            $errors[] = "La partida se jugó con un mod cargado ({$mod}). Ranked tiene que ser vanilla.";
        }

        // 6) Settings de ranked. Cada key tiene que matchear el valor esperado.
        $settingLabels = [
            'population_limit' => 'el límite de población',
            'lock_teams'       => 'el lock de equipos',
            'lock_speed'       => 'el lock de velocidad',
            'cheats'           => 'los cheats',
            'treaty_length'    => 'el tiempo de tratado',
            'multiplayer'      => 'el modo multiplayer',
        ];
        $settings = $parsed['settings'] ?? [];
        foreach (self::RANKED_SETTINGS as $key => $expected) {
            if (! array_key_exists($key, $settings) || $settings[$key] === null) {
                continue;
            }
            if ($settings[$key] !== $expected) {
                $label = $settingLabels[$key] ?? "la configuración '{$key}'";
                $errors[] = "Se modificó {$label} respecto a la configuración estándar de ranked.";
            }
        }

        // 7) Identity (skip por ahora). El profile_id que devuelve el replay
        //    es el ID interno de Microsoft/Relic (ej: 9365436), distinto al
        //    SteamID64 que tenemos en User.steam_id (76561198164357154).
        //    Para validar identidad necesitariamos un mapping steam_id ->
        //    profile_id (via Steam Web API o aprendido de primer replay).
        //    Documentado en docs/PENDING.md como follow-up.

        return $errors;
    }

    /**
     * Determina el winner_user_id desde el replay parseado.
     *
     * `winner_player_num` viene del parser (postgame.leaderboards con rank=1,
     * o fallback a 1v1 con un solo resign). Lo mapeamos al user_id de host u
     * opponent usando una convención de slots: el primer humano (menor `number`)
     * es el host, el segundo es el opponent. Funciona porque AoE2 asigna slots
     * en orden de join al lobby, y nuestro host siempre entra primero.
     *
     * Para matches vs Bot (1 humano), si winner_player_num matchea al humano,
     * devolvemos el user_id del humano (host). Si winner es la AI, devolvemos
     * el user_id del bot (opponent).
     *
     * Devuelve null si no se puede determinar (sin postgame, ni resigns claros).
     */
    public static function winnerUserId(GameMatch $match, array $parsed): ?int
    {
        $winnerNum = $parsed['winner_player_num'] ?? null;
        if ($winnerNum === null) return null;

        $humans = $parsed['humans'] ?? [];
        $ais    = $parsed['ais']    ?? [];

        // Match vs bot: solo 1 humano. Si gano el humano → host (real); si gano
        // la AI → opponent (bot). El bot NO tiene number en de.players, asi
        // que comparamos contra el humano.
        if ($match->host->isBot() || $match->opponent?->isBot()) {
            $human = $humans[0] ?? null;
            if (! $human) return null;

            $humanIsHost = ! $match->host->isBot();
            if (($human['number'] ?? null) === $winnerNum) {
                // Gano el humano
                return $humanIsHost ? $match->host_user_id : $match->opponent_user_id;
            }
            // Gano la AI → el otro lado (el bot)
            return $humanIsHost ? $match->opponent_user_id : $match->host_user_id;
        }

        // PvP real: 2 humanos. Mapeamos por orden de slot.
        usort($humans, fn ($a, $b) => ($a['number'] ?? 0) <=> ($b['number'] ?? 0));
        if (count($humans) < 2) return null;

        if (($humans[0]['number'] ?? null) === $winnerNum) return $match->host_user_id;
        if (($humans[1]['number'] ?? null) === $winnerNum) return $match->opponent_user_id;

        return null;
    }

    /**
     * Validacion vanilla: el fingerprint primario es `rms_map_id`.
     *
     * Backwards-compat: si la fila Map todavia no tiene rms_map_id seteado
     * (legacy — la columna se introdujo despues, y el admin podria no haber
     * completado todas las filas todavia), caemos al matching por nombre vs
     * `parsed.map_name` con un log warning. El banner en admin/maps avisa
     * cuantas filas estan en este estado.
     *
     * Devuelve null si OK, o un string con el error si hay mismatch.
     */
    private static function mapMismatchVanilla(Map $expected, array $parsed): ?string
    {
        // Fallback legacy: sin rms_map_id, comparamos por nombre. mgz puede
        // estar atrasado — preferimos falso negativo (no validar) sobre falso
        // positivo (matchear cualquier mapa).
        if ($expected->rms_map_id === null) {
            Log::warning("Map '{$expected->name}' sin rms_map_id — usando fallback por nombre. Admin debe subir rec para autopopular.");
            $actualMap = self::norm($parsed['map_name'] ?? '');
            if ($actualMap === '') {
                return "No se pudo identificar el mapa en el replay (sin rms_map_id en admin).";
            }
            if ($actualMap !== self::norm($expected->name)) {
                $playedLabel = $parsed['map_name'] ?? ('rms_id=' . ($parsed['rms_map_id'] ?? '?'));
                return "Se jugó en {$playedLabel} en lugar de {$expected->name} (el mapa elegido en el draft).";
            }
            return null;
        }

        $actualId = $parsed['rms_map_id'] ?? null;
        if ($actualId === null) {
            return "El replay no expone rms_map_id, no se puede identificar el mapa.";
        }

        if ((int) $actualId !== (int) $expected->rms_map_id) {
            // Mensaje amigable: si el parsed devolvio map_name (mgz lo conoce),
            // lo usamos para el error. Si no, mostramos el id crudo.
            $playedLabel = $parsed['map_name'] ?? ('rms_id=' . $actualId);
            return "Se jugó en {$playedLabel} en lugar de {$expected->name} (el mapa elegido en el draft).";
        }

        return null;
    }

    /**
     * Validacion custom: comparamos `rms_filename` (estable solo si la
     * distribucion del pack la controlamos nosotros). Si la fila tiene
     * `rms_hash`, ademas chequeamos integridad del .rms para detectar
     * tampering local — pero hoy parse_replay.py no devuelve el hash, asi
     * que esa rama queda como follow-up.
     */
    private static function mapMismatchCustom(Map $expected, array $parsed): ?string
    {
        if ($expected->rms_filename === null) {
            return "El mapa custom '{$expected->name}' no tiene rms_filename configurado en admin — no se puede validar.";
        }

        $actualFile = $parsed['rms_filename'] ?? null;
        if (empty($actualFile)) {
            return "El replay no expone rms_filename, no se puede identificar el mapa custom.";
        }

        if (self::norm($actualFile) !== self::norm($expected->rms_filename)) {
            return "Se jugó con {$actualFile} en lugar de {$expected->rms_filename} (el .rms del mapa custom elegido).";
        }

        // Hash check — solo si la fila tiene hash guardado y el parser nos
        // devuelve uno (TODO en parse_replay.py). Por ahora no se ejecuta.
        if (! empty($expected->rms_hash) && ! empty($parsed['rms_hash'])) {
            if (! hash_equals($expected->rms_hash, $parsed['rms_hash'])) {
                return "El contenido del .rms no coincide con el oficial — el script fue modificado.";
            }
        }

        return null;
    }

    /**
     * Normaliza nombres para comparacion: lowercase + trim + colapsar espacios.
     */
    private static function norm(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }
}
