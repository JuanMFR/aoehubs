@props([
    'id',                            // ID unico del dialog
    'title',                         // Titulo del modal
    'confirmLabel' => 'Confirmar',
    'cancelLabel'  => 'Cancelar',
    'danger'       => false,         // si true: confirm rojo + accent rojo en titulo
    'action'       => null,          // URL para el form. Si null, el confirm solo cierra.
    'method'       => 'POST',
])
{{--
    Modal de confirmacion reusable. Construido sobre <dialog> nativo —
    soporta ESC para cerrar, focus trap automatico, backdrop styled via CSS.

    Uso tipico:
        <button type="button" onclick="document.getElementById('cancel-X').showModal()">Cancelar</button>
        <x-confirm-modal id="cancel-X" title="..." :action="route('matches.cancel', $m->id)" danger>
            <p>Texto de advertencia con consecuencias detalladas.</p>
        </x-confirm-modal>

    Si :action no se pasa, el confirm es no-op (solo cierra). Util cuando
    el confirm dispara JS custom (ej. evitar que se haga un POST y en cambio
    dispara un fetch o algo).
--}}
<dialog id="{{ $id }}"
        class="rounded-xl bg-zinc-900 border border-zinc-800 backdrop:bg-black/70 backdrop:backdrop-blur-sm max-w-md w-[90%] p-0 text-zinc-100 m-auto">
    <div class="p-5 sm:p-6">
        <h3 class="text-lg font-bold {{ $danger ? 'text-red-300' : 'text-zinc-100' }}">{{ $title }}</h3>
        <div class="mt-3 text-sm text-zinc-400 space-y-2">
            {{ $slot }}
        </div>
        <div class="mt-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <button type="button"
                    onclick="this.closest('dialog').close()"
                    class="rounded border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-700 transition-colors">
                {{ $cancelLabel }}
            </button>
            @if ($action)
                <form method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}" class="inline">
                    @csrf
                    @if (! in_array(strtoupper($method), ['GET', 'POST']))
                        @method($method)
                    @endif
                    <button type="submit"
                            class="w-full rounded {{ $danger
                                ? 'bg-red-900 border border-red-800 text-red-100 hover:bg-red-800'
                                : 'bg-accent text-accent-dark hover:bg-accent-hover' }} px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-60 disabled:cursor-wait"
                            data-loading-text="Procesando...">
                        {{ $confirmLabel }}
                    </button>
                </form>
            @else
                <button type="button"
                        onclick="this.closest('dialog').close()"
                        class="rounded {{ $danger
                            ? 'bg-red-900 border border-red-800 text-red-100 hover:bg-red-800'
                            : 'bg-accent text-accent-dark hover:bg-accent-hover' }} px-4 py-2 text-sm font-semibold transition-colors">
                    {{ $confirmLabel }}
                </button>
            @endif
        </div>
    </div>
</dialog>
