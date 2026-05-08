<?php

namespace App\Services;

use App\Models\GameMatch;

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
        //    truncate (alt+f4 mid-game, crash, etc). En esos casos no contamos
        //    como match valido — el cron de forfeit/abandon decide.
        if (isset($parsed['completed']) && $parsed['completed'] === false) {
            $reason = $parsed['truncate_reason'] ?? 'sin razon';
            $errors[] = "replay truncado/incompleto: {$reason}";
        }

        // 2) Cantidad de humans esperada. PvP real = 2; vs bot = 1 humano + AI.
        $expectedHumans = ($match->host->isBot() || $match->opponent?->isBot()) ? 1 : 2;
        if (count($humans) !== $expectedHumans) {
            $errors[] = "se esperaban {$expectedHumans} humano(s) en el replay, encontrados " . count($humans);
        }

        // 3) Civs: validamos que las dos civs esperadas (segun civ draft)
        //    aparezcan en los humans del replay. Sin identity matching (no
        //    sabemos cual humano es host vs opponent porque profile_id != steam_id),
        //    asi que comparamos como sets ordenados. Robusto a swap de slots.
        $civDraft = $match->civDraft;
        if ($civDraft !== null && ! empty($civDraft->host_final_civ) && ! empty($civDraft->opponent_final_civ)) {
            $expected = [self::norm($civDraft->host_final_civ), self::norm($civDraft->opponent_final_civ)];
            sort($expected);

            // Para matches vs bot, solo el humano (host real) deberia tener su civ del draft.
            // Para PvP, ambas civs tienen que aparecer.
            $actualCivs = array_filter(array_map(fn ($h) => self::norm($h['civilization'] ?? ''), $humans));

            if ($expectedHumans === 1) {
                // Solo validamos que la civ del humano matchee al host_final_civ
                $hostExpected = self::norm($civDraft->host_final_civ);
                $humanCiv     = $actualCivs[0] ?? null;
                if ($humanCiv !== $hostExpected) {
                    $errors[] = "host: civ esperada '{$civDraft->host_final_civ}', jugo '" . ($humans[0]['civilization'] ?? '?') . "'";
                }
            } else {
                $actualSorted = $actualCivs;
                sort($actualSorted);
                if ($expected !== $actualSorted) {
                    $errors[] = "civs esperadas [" . implode(', ', $expected) . "], encontradas [" . implode(', ', $actualSorted) . "]";
                }
            }
        }

        // 4) Mapa: comparamos contra `map_name` (derivado de rms_map_id ->
        //    DE_MAP_NAMES, ej: 33 -> "Nomad"). NO usamos `rms_filename` porque
        //    cuando hay un map pack del Steam Workshop cargado, el archivo
        //    RMS literal puede ser "LP Arena.rms" o similar — el id estándar
        //    sigue siendo el correcto.
        $mapDraft = $match->mapDraft;
        if ($mapDraft !== null && ! empty($mapDraft->final_map)) {
            $expectedMap = self::norm($mapDraft->final_map);
            $actualMap   = self::norm($parsed['map_name'] ?? '');

            if ($actualMap === '') {
                $errors[] = "el replay no tiene map_name (rms_map_id=" . ($parsed['rms_map_id'] ?? '?') . ")";
            } elseif ($actualMap !== $expectedMap) {
                $errors[] = "mapa esperado '{$mapDraft->final_map}', jugado '{$parsed['map_name']}'";
            }
        }

        // 5) Mods: ranked es vanilla. El campo `mod` del replay deberia estar vacio.
        $mod = $parsed['mod'] ?? '';
        if ($mod !== '' && $mod !== null) {
            $errors[] = "el replay tiene mod cargado: '{$mod}'";
        }

        // 6) Settings de ranked. Cada key tiene que matchear el valor esperado.
        $settings = $parsed['settings'] ?? [];
        foreach (self::RANKED_SETTINGS as $key => $expected) {
            if (! array_key_exists($key, $settings) || $settings[$key] === null) {
                continue; // skip si el campo no esta poblado
            }
            if ($settings[$key] !== $expected) {
                $actual = is_bool($settings[$key]) ? ($settings[$key] ? 'true' : 'false') : (string) $settings[$key];
                $exp    = is_bool($expected)        ? ($expected        ? 'true' : 'false') : (string) $expected;
                $errors[] = "setting '{$key}': esperado {$exp}, encontrado {$actual}";
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
