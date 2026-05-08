@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

        {{-- Resumen de items mostrados --}}
        <p class="text-xs text-zinc-500">
            @if ($paginator->firstItem())
                Mostrando <span class="font-mono text-zinc-300">{{ $paginator->firstItem() }}</span>
                a <span class="font-mono text-zinc-300">{{ $paginator->lastItem() }}</span>
                de <span class="font-mono text-zinc-300">{{ $paginator->total() }}</span>
            @else
                {{ $paginator->count() }} resultados
            @endif
        </p>

        {{-- Botones de paginacion --}}
        <div class="inline-flex items-center -space-x-px text-sm">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="px-3 py-1.5 rounded-l border border-zinc-800 bg-zinc-950 text-zinc-600 cursor-not-allowed">«</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="px-3 py-1.5 rounded-l border border-zinc-700 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 transition-colors">«</a>
            @endif

            {{-- Page links --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    {{-- "..." --}}
                    <span class="px-3 py-1.5 border border-zinc-800 bg-zinc-950 text-zinc-600">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="px-3 py-1.5 border border-steam bg-steam-dark text-steam font-semibold">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}"
                               class="px-3 py-1.5 border border-zinc-700 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 transition-colors">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="px-3 py-1.5 rounded-r border border-zinc-700 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 transition-colors">»</a>
            @else
                <span class="px-3 py-1.5 rounded-r border border-zinc-800 bg-zinc-950 text-zinc-600 cursor-not-allowed">»</span>
            @endif
        </div>
    </nav>
@endif
