<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SteamProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    private const STEAM_OPENID_URL = 'https://steamcommunity.com/openid/login';

    /**
     * Redirige al usuario a Steam para autenticación OpenID 2.0.
     */
    public function redirectToSteam()
    {
        $params = [
            'openid.ns'         => 'http://specs.openid.net/auth/2.0',
            'openid.mode'       => 'checkid_setup',
            'openid.return_to'  => route('auth.steam.callback'),
            'openid.realm'      => url('/'),
            'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ];

        return redirect(self::STEAM_OPENID_URL . '?' . http_build_query($params));
    }

    /**
     * Steam redirige acá con la prueba de identidad. Verificamos contra Steam,
     * extraemos el SteamID64 y logueamos al usuario (creándolo si no existe).
     */
    public function handleSteamCallback(Request $request)
    {
        // PHP convierte '.' por '_' en nombres de query params, así que parseamos
        // la query string original para preservar 'openid.xxx' como manda el spec.
        $params = $this->parseRawQuery($request->server('QUERY_STRING') ?? '');

        // 1. return_to debe matchear nuestro callback exacto. Previene que un
        //    atacante reuse un callback firmado para otro endpoint.
        $expectedReturn = route('auth.steam.callback');
        if (($params['openid.return_to'] ?? '') !== $expectedReturn) {
            return redirect('/')->with('error', 'Callback URL inválida.');
        }

        // 2. claimed_id tiene que estar firmado. Si no, el response no
        //    autentica al user — solo dice "Steam respondió OK". Sin esto un
        //    atacante puede mandar claimed_id arbitrario con firma valida sobre
        //    otros params.
        $signed = explode(',', $params['openid.signed'] ?? '');
        if (! in_array('claimed_id', $signed, true)) {
            return redirect('/')->with('error', 'Respuesta de Steam incompleta.');
        }

        // 3. Replay protection: response_nonce tiene que ser unico (cache 24h).
        //    Sin esto un atacante con un callback URL leakeado puede replayearlo
        //    indefinidamente.
        $nonce = $params['openid.response_nonce'] ?? '';
        if ($nonce === '' || ! Cache::add('openid:nonce:'.hash('sha256', $nonce), 1, now()->addDay())) {
            return redirect('/')->with('error', 'Respuesta de Steam ya usada o inválida.');
        }

        // 4. Verificacion criptografica final con Steam.
        if (!$this->verifyWithSteam($params)) {
            return redirect('/')->with('error', 'Steam no validó la identidad.');
        }

        $steamId = $this->extractSteamId($params['openid.claimed_id'] ?? '');
        if ($steamId === null) {
            return redirect('/')->with('error', 'No se pudo extraer el SteamID.');
        }

        $user = User::firstOrCreate(['steam_id' => $steamId]);

        // Refrescar persona_name + avatar via Steam Web API. No-op si no hay
        // STEAM_API_KEY configurado o si los datos son recientes.
        SteamProfile::refresh($user);

        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    /**
     * Genera un nuevo token Sanctum para el companion del usuario.
     * Revoca los tokens anteriores (un solo companion activo por user).
     * Devuelve el token en plain text una sola vez para que lo copie.
     */
    public function generateCompanionToken(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('companion')->plainTextToken;

        return back()->with('companion_token', $token);
    }

    /**
     * Repreguntamos a Steam con mode=check_authentication. Si responde
     * "is_valid:true", la firma fue genuina.
     */
    private function verifyWithSteam(array $params): bool
    {
        $params['openid.mode'] = 'check_authentication';

        $response = Http::asForm()->post(self::STEAM_OPENID_URL, $params);

        return $response->successful() &&
               str_contains($response->body(), 'is_valid:true');
    }

    private function extractSteamId(string $claimedId): ?string
    {
        // claimed_id es algo como https://steamcommunity.com/openid/id/76561198xxxxxxxxx
        if (preg_match('#/openid/id/(\d{17})$#', $claimedId, $m)) {
            return $m[1];
        }
        return null;
    }

    private function parseRawQuery(string $queryString): array
    {
        $out = [];
        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') continue;
            $parts = explode('=', $pair, 2);
            $key   = urldecode($parts[0]);
            $value = isset($parts[1]) ? urldecode($parts[1]) : '';
            $out[$key] = $value;
        }
        return $out;
    }
}
