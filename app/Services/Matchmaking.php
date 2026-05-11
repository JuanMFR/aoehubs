<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Map;
use App\Models\MapDraft;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Matchmaking
{
    /**
     * Pool de mapas competitivos para 1v1 ranked. Lee desde la tabla `maps`
     * (gestionada via /admin/maps). Cantidad IMPAR para que el draft de bans
     * tenga ventaja simetrica.
     *
     * Si la tabla esta vacia (pre-deploy de la feature), cae a una lista
     * hardcoded como safety net asi el matchmaking no se rompe.
     */
    public static function mapPool(): array
    {
        $names = Map::activeNames();
        if (!empty($names)) return $names;

        return [
            'Arabia', 'Arena', 'Black Forest', 'Nomad', 'Hideout',
            'Hill Fort', 'Acropolis', 'Land Madness', 'Mediterranean',
        ];
    }

    // Pool de civilizaciones de AoE2 DE — incluye DLCs hasta Three Kingdoms.
    public const CIV_POOL = [
        'Armenians', 'Aztecs', 'Bengalis', 'Berbers', 'Bohemians', 'Britons',
        'Bulgarians', 'Burgundians', 'Burmese', 'Byzantines', 'Celts', 'Chinese',
        'Cumans', 'Dravidians', 'Ethiopians', 'Franks', 'Georgians', 'Goths',
        'Gurjaras', 'Hindustanis', 'Huns', 'Incas', 'Italians', 'Japanese',
        'Jurchens', 'Khitans', 'Khmer', 'Koreans', 'Lithuanians', 'Magyars',
        'Malay', 'Malians', 'Mapuche', 'Mayans', 'Mongols', 'Muisca',
        'Persians', 'Poles', 'Portuguese', 'Romans', 'Saracens', 'Shu',
        'Sicilians', 'Slavs', 'Spanish', 'Tatars', 'Teutons', 'Tupi',
        'Turks', 'Vietnamese', 'Vikings', 'Wei', 'Wu',
    ];

    /**
     * Mete al user en la queue. Si hay otro esperando, los empareja y devuelve
     * la match recién creada. Si no, devuelve null y el user queda esperando.
     *
     * Lanza CooldownException si el user está en cooldown anti-griefing.
     */
    public function joinQueue(User $user): ?GameMatch
    {
        if ($user->isInCooldown()) {
            throw new \RuntimeException("user en cooldown hasta {$user->cooldown_until->toIso8601String()}");
        }

        return DB::transaction(function () use ($user) {
            // Si ya está en queue, refrescamos timestamp y no hacemos nada más
            $existing = QueueEntry::where('user_id', $user->id)->first();
            if ($existing && !$existing->is_bot) {
                return $this->tryPair($user);
            }

            QueueEntry::create([
                'user_id'   => $user->id,
                'is_bot'    => false,
                'joined_at' => now(),
            ]);

            return $this->tryPair($user);
        });
    }

    /**
     * Saca al user de la queue (si estaba). No-op si no estaba.
     */
    public function leaveQueue(User $user): void
    {
        QueueEntry::where('user_id', $user->id)->where('is_bot', false)->delete();
    }

    /**
     * Crea una match con el user como host y un Bot fantasma como opponent,
     * directamente en estado pending (sin pasar por drafts). Útil para testear
     * el lado del host sin esperar otro jugador real.
     */
    public function createTestHostMatch(User $host): GameMatch
    {
        $bot = User::where('steam_id', 'BOTDEV_PERMANENT_QUEUE')->firstOrFail();
        $pool = static::mapPool();
        return $this->createPendingMatch($host, $bot, $pool[array_rand($pool)]);
    }

    /**
     * Crea una match con el user como joiner y el Bot como host. Como el bot
     * no corre companion, el lobby_id y la config los provee el user manualmente
     * (después de crear el lobby a mano en AoE2 y copiar el código).
     */
    public function createTestJoinerMatch(User $joiner, string $lobbyId, string $password): GameMatch
    {
        $bot = User::where('steam_id', 'BOTDEV_PERMANENT_QUEUE')->firstOrFail();

        // Auto-abandonar pendings previas del joiner para no superponer
        GameMatch::where(function ($q) use ($joiner) {
                $q->where('host_user_id',     $joiner->id)
                  ->orWhere('opponent_user_id', $joiner->id);
            })
            ->where('status', GameMatch::STATUS_PENDING)
            ->update(['status' => GameMatch::STATUS_ABANDONED]);

        return GameMatch::create([
            'host_user_id'     => $bot->id,
            'opponent_user_id' => $joiner->id,
            'config_json'      => [
                'lobbyName' => "test-joiner-{$lobbyId}",
                'password'  => $password,
                'server'    => 'westeurope',
                'map'       => static::mapPool()[0],
            ],
            'lobby_id' => $lobbyId,
            'status'   => GameMatch::STATUS_PENDING,
        ]);
    }

    /**
     * Si en la queue hay alguien además del user, los empareja. El que entró
     * primero queda como host. Devuelve la match creada o null si no hubo pareo.
     */
    private function tryPair(User $user): ?GameMatch
    {
        // lockForUpdate sobre el lookup del rival. Sin esto, dos transactions
        // concurrentes (3+ users hitting /queue/join al mismo tiempo) podian
        // leer el mismo $waiting → ambos lo emparejaban → match duplicada.
        // El lock serializa el acceso: el segundo ve la queue ya consumida
        // y devuelve null (queda esperando a otro rival).
        $waiting = QueueEntry::where('user_id', '!=', $user->id)
            ->lockForUpdate()
            ->orderBy('joined_at')
            ->first();

        if ($waiting === null) return null;

        // Cuando el rival es Bot Dev, el usuario real siempre es host. Si fuera
        // al revés (bot es host), no hay companion real que cree el lobby y el
        // flujo no se puede testear.
        if ($waiting->is_bot) {
            $hostId = $user->id;
            $oppId  = $waiting->user_id;
        } else {
            $userEntry = QueueEntry::where('user_id', $user->id)->lockForUpdate()->first();
            $hostId    = $waiting->joined_at < $userEntry->joined_at ? $waiting->user_id : $user->id;
            $oppId     = $hostId === $user->id ? $waiting->user_id : $user->id;
        }

        $host     = User::find($hostId);
        $opponent = User::find($oppId);

        $match = $this->createMatch($host, $opponent);

        // Sacamos a ambos de la queue (excepto al bot, que vive ahí permanentemente)
        QueueEntry::where('user_id', $user->id)->where('is_bot', false)->delete();
        if (!$waiting->is_bot) {
            QueueEntry::where('user_id', $waiting->user_id)->delete();
        }

        return $match;
    }

    /**
     * Crea una match con drafts pendientes. Se usa al emparejar dos players
     * desde la queue. La match arranca en status=drafting y NO la ve el
     * companion hasta que termine el draft (status pasa a pending).
     */
    private function createMatch(User $host, User $opponent): GameMatch
    {
        $this->abandonPreviousMatches($host);
        $this->abandonPreviousMatches($opponent);

        $match = GameMatch::create([
            'host_user_id'     => $host->id,
            'opponent_user_id' => $opponent->id,
            'config_json'      => [
                // Nombre fijo de la plataforma — facilita identificar visualmente
                // los matches en el lobby browser de AoE2 (casters/streamers).
                // No se usa para validacion: la identidad del match se establece
                // via lobby_id que el companion reporta tras OCR del header.
                'lobbyName' => 'aoehubs.com',
                'password'  => Str::lower(Str::random(8)),
                'server'    => self::pickOptimalServer($host, $opponent),
                'map'       => null, // se setea cuando termine el map draft
            ],
            'status' => GameMatch::STATUS_DRAFTING,
        ]);

        // Quién banea primero: random
        $startingUserId = (rand(0, 1) === 0) ? $host->id : $opponent->id;
        MapDraft::create([
            'match_id'         => $match->id,
            'starting_user_id' => $startingUserId,
            'bans_json'        => [],
            'final_map'        => null,
        ]);

        return $match;
    }

    /**
     * Crea una match directamente en pending (sin drafts). Para testing y
     * para el flow de Test Joiner donde proveés todos los datos a mano.
     */
    private function createPendingMatch(User $host, User $opponent, ?string $map = null): GameMatch
    {
        $this->abandonPreviousMatches($host);

        return GameMatch::create([
            'host_user_id'     => $host->id,
            'opponent_user_id' => $opponent->id,
            'config_json'      => [
                // Nombre fijo de la plataforma — facilita identificar visualmente
                // los matches en el lobby browser de AoE2 (casters/streamers).
                // No se usa para validacion: la identidad del match se establece
                // via lobby_id que el companion reporta tras OCR del header.
                'lobbyName' => 'aoehubs.com',
                'password'  => Str::lower(Str::random(8)),
                'server'    => 'westeurope',
                'map'       => $map ?? (function () {
                    $pool = static::mapPool();
                    return $pool[array_rand($pool)];
                })(),
            ],
            'status' => GameMatch::STATUS_PENDING,
        ]);
    }

    private function abandonPreviousMatches(User $user): void
    {
        GameMatch::where(function ($q) use ($user) {
                $q->where('host_user_id',     $user->id)
                  ->orWhere('opponent_user_id', $user->id);
            })
            ->whereIn('status', [GameMatch::STATUS_PENDING, GameMatch::STATUS_DRAFTING])
            ->update(['status' => GameMatch::STATUS_ABANDONED]);
    }

    /**
     * Elige el server de AoE2 que minimiza el peor ping entre los dos players.
     * Lee pings_json de cada user (que reporta el companion vía /api/companion/pings).
     *
     * Algoritmo:
     *   - Si ambos tienen pings: para cada server donde ambos midieron, score
     *     = max(host_ping, opp_ping). Devuelve el server con menor score.
     *   - Si sólo uno tiene pings: devuelve su server más rápido.
     *   - Si ninguno tiene: fallback a 'westeurope'.
     *
     * Retorna null si quisieramos forzar el fallback default; siempre retorna
     * algo en la práctica.
     */
    public static function pickOptimalServer(User $host, User $opponent): string
    {
        $default = 'westeurope';

        $hostPings = is_array($host->pings_json)     ? $host->pings_json     : [];
        $oppPings  = is_array($opponent->pings_json) ? $opponent->pings_json : [];

        $common = array_intersect_key($hostPings, $oppPings);

        if (! empty($common)) {
            $best   = null;
            $bestMs = PHP_INT_MAX;
            foreach ($common as $server => $_) {
                $score = max((int) $hostPings[$server], (int) $oppPings[$server]);
                if ($score < $bestMs) {
                    $bestMs = $score;
                    $best   = $server;
                }
            }
            return $best ?? $default;
        }

        // Cae acá si Bot Dev (sin pings) o si los users ping-earon regiones
        // disjuntas (raro). Usamos el mejor del que sí tenga.
        $available = ! empty($hostPings) ? $hostPings : $oppPings;
        if (empty($available)) return $default;

        asort($available);
        return array_key_first($available);
    }
}
