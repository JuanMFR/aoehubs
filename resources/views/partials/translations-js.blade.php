{{--
    Inyecta el JSON de traducciones del locale activo como variable global JS
    `TRANSLATIONS`. Sirve para que los drafts (donde el JS arma tiles dinamicos
    con nombres de civ/mapa) puedan mostrar la traduccion sin tener que llamar
    al backend por cada nombre.

    Uso: @include('partials.translations-js')
    Despues en JS: `TRANSLATIONS[name] || name`.
--}}
@php
    $localeFile = lang_path(app()->getLocale() . '.json');
    $translations = file_exists($localeFile)
        ? (json_decode(file_get_contents($localeFile), true) ?? [])
        : [];
@endphp
<script>
    window.TRANSLATIONS = @json($translations);
    window.t = (key) => window.TRANSLATIONS[key] || key;
</script>
