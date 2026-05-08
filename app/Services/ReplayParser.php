<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Parsea un .aoe2record invocando el script Python `scripts/parse_replay.py`
 * vía subprocess. El script siempre devuelve JSON por stdout (incluso cuando
 * mgz falla por incompatibilidad de versión), así que esta clase nunca lanza
 * por errores "esperables" del parser — los devuelve como ParseResult.
 *
 * Errores que sí lanzan: el ejecutable de Python no existe, el script no
 * está, timeout del proceso, JSON corrupto. Esos son problemas de
 * infraestructura del backend, no de la replay.
 */
class ReplayParser
{
    private const TIMEOUT_SECONDS = 30;

    public static function parse(string $absoluteReplayPath): ParseResult
    {
        $python = config('services.python.bin', 'python');
        $script = base_path('scripts/parse_replay.py');

        if (! file_exists($script)) {
            throw new \RuntimeException("parse_replay.py no encontrado en {$script}");
        }

        $process = new Process([$python, $script, $absoluteReplayPath]);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        // En Windows el subprocess de Python necesita ciertas env vars para
        // inicializar Winsock (asyncio/_overlapped). Cuando PHP corre bajo el
        // built-in HTTP server (php artisan serve / php -S), el child request
        // PHP hereda un environment recortado y al spawnear Python, falla con
        // WinError 10106 al importar asyncio. Passing explicit env fixes it.
        if (PHP_OS_FAMILY === 'Windows') {
            $envKeys = ['SYSTEMROOT', 'COMSPEC', 'PATH', 'TEMP', 'TMP', 'USERPROFILE',
                        'USERNAME', 'USERDOMAIN', 'LOCALAPPDATA', 'APPDATA',
                        'ALLUSERSPROFILE', 'WINDIR', 'COMPUTERNAME', 'HOMEDRIVE', 'HOMEPATH'];
            $env = [];
            foreach ($envKeys as $k) {
                $v = getenv($k);
                if ($v !== false && $v !== '') $env[$k] = $v;
            }
            // Defaults seguros si getenv() devolvió vacío (entorno realmente pelado)
            $env['SYSTEMROOT'] = $env['SYSTEMROOT'] ?? 'C:\\Windows';
            $env['WINDIR']     = $env['WINDIR']     ?? 'C:\\Windows';
            $process->setEnv($env);
        }

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new \RuntimeException("parse_replay.py timeout después de " . self::TIMEOUT_SECONDS . "s");
        }

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        // El script SIEMPRE imprime JSON. Si stdout está vacío, algo muy malo
        // pasó (Python no se pudo ejecutar, etc).
        if (trim($stdout) === '') {
            throw new \RuntimeException("parse_replay.py no produjo output. stderr: " . trim($stderr));
        }

        $decoded = json_decode($stdout, true);
        if (! is_array($decoded) || ! isset($decoded['ok'])) {
            throw new \RuntimeException("parse_replay.py output inválido: " . substr($stdout, 0, 200));
        }

        return new ParseResult(
            ok:    (bool) $decoded['ok'],
            data:  $decoded['data']  ?? null,
            error: $decoded['error'] ?? null,
            type:  $decoded['type']  ?? null,
        );
    }
}

/**
 * Resultado del parser. ok=true → data tiene metadata. ok=false → error+type
 * describen el motivo (parse_error = mgz incompatible con el patch actual,
 * mgz_missing, file_not_found, bad_args).
 */
class ParseResult
{
    public function __construct(
        public readonly bool    $ok,
        public readonly ?array  $data  = null,
        public readonly ?string $error = null,
        public readonly ?string $type  = null,
    ) {}
}
