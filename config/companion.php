<?php

/**
 * Configuracion del AoEHubs Companion (la app desktop que captura los
 * replays). Centralizamos URL de descarga + version mostrada en la web
 * asi cuando subimos una release nueva a GitHub Releases solo tocamos
 * estos valores (o las env vars asociadas).
 */

return [

    /**
     * URL publica de descarga del instalador. Por default apunta al
     * "latest" de GitHub Releases — ese link se auto-actualiza cuando
     * subis una nueva release sin tener que cambiar config aca.
     *
     * Override via env si en algun momento migrás a S3/server propio.
     */
    'download_url' => env(
        'COMPANION_DOWNLOAD_URL',
        'https://github.com/JuanMFR/aoehubs/releases/latest/download/AoE2CompanionSetup.exe'
    ),

    /**
     * Version que se muestra en la pagina /companion. Subila al publicar
     * una release nueva. Es informativa — no hay verificacion server-side
     * de que el companion del user este actualizado.
     */
    'version' => env('COMPANION_VERSION', '0.1.0'),

    /**
     * Tamaño aprox del setup, para mostrar en la pagina antes de descargar.
     */
    'size_mb' => env('COMPANION_SIZE_MB', '135'),

    /**
     * URL al codigo fuente / changelog. Vacio = ocultar el link.
     */
    'source_url' => env('COMPANION_SOURCE_URL', 'https://github.com/JuanMFR/aoehubs'),

];
