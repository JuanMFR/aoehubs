<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Map;

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

        // 4) Mapa: comparamos contra `map_name` con fallback a `rms_map_id`.
        $mapDraft = $match->mapDraft;
        if ($mapDraft !== null && ! empty($mapDraft->final_map)) {
            $expectedMap = self::norm($mapDraft->final_map);
            $actualMap   = self::norm($parsed['map_name'] ?? '');

            // Fallback: si mgz no tiene el nombre (mapa nuevo no en DE_MAP_NAMES)
            // pero si el rms_map_id, lo resolvemos via la tabla Map del admin.
            if ($actualMap === '' && !empty($parsed['rms_map_id'])) {
                $mapByRms = Map::where('rms_map_id', $parsed['rms_map_id'])->first();
                if ($mapByRms) {
                    $actualMap = self::norm($mapByRms->name);
                }
            }

            if ($actualMap === '') {
                $errors[] = "No se pudo identificar el mapa en el replay.";
            } elseif ($actualMap !== $expectedMap) {
                $playedName = $parsed['map_name'] ?? ('rms_id=' . ($parsed['rms_map_id'] ?? '?'));
                $errors[] = "Se jugó en {$playedName} en lugar de {$mapDraft->final_map} (el mapa elegido en el draft).";
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
     * Normaliza nombres para comparacion: lowercase + trim + colapsar espacios.
     */
    private static function norm(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }
}
