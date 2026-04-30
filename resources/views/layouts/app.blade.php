<!DOCTYPE html>
<html lang="pt-BR" x-data="{ sidebarOpen: false, botOpen: false }" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HomeBot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-[#060709] text-[#eef0f5] font-sans">
@php
    $layoutGroupName        = $layoutGroupName        ?? 'Minha Casa';
    $layoutPendingClose     = $layoutPendingClose     ?? 0;
    $layoutPendingPix       = $layoutPendingPix       ?? 0;
    $layoutRecentTx         = $layoutRecentTx         ?? collect();
    $layoutMemberBalances   = $layoutMemberBalances   ?? [];

    $navItems = [
        ['route' => 'dashboard',      'icon' => 'home',     'label' => 'Visão geral'],
        ['route' => 'transactions',   'icon' => 'receipt',  'label' => 'Transações'],
        ['route' => 'balance',        'icon' => 'wallet',   'label' => 'Saldo'],
        ['route' => 'monthly-report', 'icon' => 'calendar', 'label' => 'Fechamento'],
        ['route' => 'settings',       'icon' => 'settings', 'label' => 'Config'],
    ];

    $pageLabels = [
        'dashboard'      => 'Visão geral',
        'transactions'   => 'Transações',
        'balance'        => 'Saldo',
        'monthly-report' => 'Fechamento',
        'settings'       => 'Configurações',
    ];

    $currentRoute = request()->route()?->getName();
@endphp

<div class="flex h-full">
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-black/60 lg:hidden" @click="sidebarOpen = false"></div>

    <aside class="fixed inset-y-0 left-0 z-40 w-[200px] border-r border-[#1a1c23] bg-[#060709] transition-transform duration-200 lg:static lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-full flex-col">
            {{-- Logo --}}
            <div class="border-b border-[#1a1c23] px-4 py-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-[#1fcc8a] to-[#12a870]">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <path d="M9 2L3 8l6 8 6-8-6-6z" fill="white" fill-opacity="0.9"/>
                            <path d="M6 8l3 4 3-4" stroke="white" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-[13px] font-bold tracking-tight">HomeBot</div>
                        <div class="text-[10px] text-[#2e3244]">{{ $layoutGroupName }}</div>
                    </div>
                </a>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 space-y-0.5 p-2.5 pt-3">
                @foreach($navItems as $item)
                    @php $active = request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-[12.5px] transition {{ $active ? 'bg-[rgba(31,204,138,0.1)] text-[#1fcc8a] font-medium' : 'text-[#414858] hover:bg-[#111218] hover:text-[#9da5b4]' }}">
                        @include('partials.icon', ['name' => $item['icon'], 'size' => 15])
                        <span>{{ $item['label'] }}</span>
                        @if($item['route'] === 'monthly-report' && $layoutPendingClose > 0)
                            <span class="ml-auto h-1.5 w-1.5 rounded-full bg-[#f5a100]"></span>
                        @endif
                    </a>
                @endforeach
            </nav>

            {{-- Member balances mini-list --}}
            @if(!empty($layoutMemberBalances))
            <div class="border-t border-[#1a1c23] px-3 py-3">
                <div class="mb-2 text-[9.5px] font-semibold uppercase tracking-[0.08em] text-[#2e3244]">Saldo do mês</div>
                <div class="flex flex-col gap-1.5">
                    @foreach($layoutMemberBalances as $mb)
                    <div class="flex items-center gap-2">
                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-[rgba(31,204,138,0.25)] bg-[rgba(31,204,138,0.1)] text-[9px] font-bold text-[#1fcc8a]">
                            {{ $mb['initials'] }}
                        </div>
                        <span class="flex-1 truncate text-[11px] text-[#414858]">{{ $mb['name'] }}</span>
                        <span class="font-mono text-[11px] {{ $mb['diff'] >= 0 ? 'text-[#1fcc8a]' : 'text-[#f04040]' }}">
                            {{ $mb['diff'] >= 0 ? '+' : '−' }}{{ number_format(abs($mb['diff']), 0, ',', '.') }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Footer --}}
            <div class="space-y-2.5 border-t border-[#1a1c23] p-3">
                <button @click="botOpen = true; $nextTick(() => window.hbBotStart && window.hbBotStart())"
                        class="w-full rounded-lg border border-[rgba(31,204,138,0.22)] bg-[rgba(31,204,138,0.08)] px-3 py-2 text-left text-[12px] font-medium text-[#1fcc8a] transition hover:bg-[rgba(31,204,138,0.13)]">
                    WhatsApp Bot <span class="ml-1 rounded-full bg-[#1fcc8a] px-1.5 py-0.5 text-[9px] font-bold text-black">ON</span>
                </button>

                @auth
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[rgba(31,204,138,0.27)] bg-[rgba(31,204,138,0.13)] text-[10px] font-semibold text-[#1fcc8a]">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[12px] font-medium">{{ auth()->user()->name ?? 'Usuário' }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded p-1 text-[#2e3244] transition hover:text-[#737a8a]" aria-label="Sair">
                                <svg width="13" height="13" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                                    <path d="M13 10H3M7 6l-4 4 4 4M17 4v12"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                @endauth
            </div>
        </div>
    </aside>

    {{-- Main content --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 border-b border-[#1a1c23] bg-[#060709]/95 backdrop-blur">
            <div class="flex h-13 items-center gap-3 px-4 sm:px-5">
                <button @click="sidebarOpen = true" class="rounded-lg border border-[#1a1c23] p-2 text-[#414858] lg:hidden" aria-label="Abrir menu">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M3 5h14M3 10h14M3 15h14" stroke-linecap="round"/>
                    </svg>
                </button>

                <div class="min-w-0 flex-1 text-[12.5px] text-[#414858]">
                    <span class="text-[#2e3244]">{{ $layoutGroupName }}</span>
                    <span class="mx-1.5">/</span>
                    <span class="text-[#9da5b4]">{{ $pageLabels[$currentRoute] ?? 'Dashboard' }}</span>
                </div>

                @if($layoutPendingPix > 0)
                    <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-[rgba(245,161,0,0.2)] bg-[rgba(245,161,0,0.08)] px-2.5 py-1 text-[11px] font-medium text-[#f5a100]">
                        {{ $layoutPendingPix }} Pix pendente{{ $layoutPendingPix > 1 ? 's' : '' }}
                    </span>
                @endif

                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" class="relative rounded-lg border border-[#1a1c23] p-2 text-[#414858] transition hover:border-[#232630]">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M10 2a6 6 0 00-6 6v4l-2 2h16l-2-2V8a6 6 0 00-6-6z"/>
                            <path d="M8 16a2 2 0 004 0"/>
                        </svg>
                        @if($layoutRecentTx->isNotEmpty())
                            <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-[#1fcc8a]"></span>
                        @endif
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 overflow-hidden rounded-xl border border-[#1a1c23] bg-[#0e0f13] shadow-2xl">
                        <div class="border-b border-[#1a1c23] px-4 py-3 text-[12px] font-semibold text-[#9da5b4]">Notificações</div>
                        @forelse($layoutRecentTx as $tx)
                            <div class="flex items-start gap-2.5 px-4 py-3 {{ !$loop->last ? 'border-b border-[#1a1c23]' : '' }}">
                                <div class="mt-0.5 rounded-lg bg-[rgba(31,204,138,0.1)] p-1.5 text-xs">🧾</div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[12px] font-medium text-[#eef0f5]">{{ \Illuminate\Support\Str::limit($tx->description, 28) }}</div>
                                    <div class="text-[10.5px] text-[#2e3244]">R$ {{ number_format($tx->total_amount, 2, ',', '.') }} · {{ $tx->member?->name }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-6 text-center text-[11px] text-[#2e3244]">Sem notificações</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </header>

        <main class="min-h-0 flex-1 overflow-auto">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Bot panel --}}
<div x-show="botOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/60" @click="botOpen = false"></div>
<aside x-show="botOpen" x-cloak x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-[380px] border-l border-[#1a1c23] bg-[#09090e]">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between border-b border-[#1a1c23] bg-[#0b2218] px-4 py-3">
            <div>
                <div class="text-[13px] font-semibold text-[#e9edef]">HomeBot no WhatsApp</div>
                <div class="text-[11px] text-[#5d7068]">Exemplo de fluxo automático</div>
            </div>
            <button @click="botOpen = false" class="rounded p-1 text-[#5d7068] transition hover:text-white" aria-label="Fechar">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
            </button>
        </div>

        <div id="hb-bot-chat" class="flex-1 overflow-auto bg-[#0b1219] p-4" style="display:flex;flex-direction:column;gap:6px;">
            {{-- msg 0: user sends nota --}}
            <div class="wa-row u"><div data-msg="0" class="wa-bbl usr">
                <div class="wa-img-ph"><div class="ico">🧾</div><div class="cap">nota_fiscal.jpg</div></div>
                <div class="wa-ts">14:03 <span class="wa-ck">✓✓</span></div>
            </div></div>

            {{-- typing --}}
            <div class="wa-row"><div data-typing class="wa-typing"><div class="wa-tdot"></div><div class="wa-tdot"></div><div class="wa-tdot"></div></div></div>

            {{-- msg 1: bot processes --}}
            <div class="wa-row"><div data-msg="1" class="wa-bbl bot">
                <div>✅ <strong>Nota processada</strong></div>
                <div class="wa-rcpt-row"><span>Mercado</span><span>R$&nbsp;42,30</span></div>
                <div class="wa-rcpt-row"><span>Cerveja 🍺</span><span>R$&nbsp;18,90</span></div>
                <div class="wa-rcpt-total"><span>Total</span><span>R$&nbsp;61,20</span></div>
                <div style="margin-top:8px;font-size:10.5px;color:#8696a0">A cerveja é pessoal ou da casa?</div>
                <div class="wa-ts">14:03</div>
            </div></div>

            {{-- msg 2: user replies --}}
            <div class="wa-row u"><div data-msg="2" class="wa-bbl usr">
                É pessoal 🍻<div class="wa-ts">14:04 <span class="wa-ck">✓✓</span></div>
            </div></div>

            {{-- msg 3: bot confirms --}}
            <div class="wa-row"><div data-msg="3" class="wa-bbl res">
                <div class="wa-res-amount">R$ 42,30</div>
                <div style="font-size:10.5px;color:#8696a0;margin-top:3px">Adicionado à casa · Saldo atualizado ✓</div>
                <div class="wa-ts">14:04</div>
            </div></div>

            {{-- floating balance card --}}
            <div data-card class="hb-phone-card" style="position:relative;right:auto;bottom:auto;width:100%;margin-top:4px;">
                <div class="pc-label">Saldo da casa</div>
                <div class="pc-amount">R$ 42,30</div>
                <div class="pc-sub">João deve R$&nbsp;21,15 para Ana</div>
                <div class="pc-foot">
                    <div class="pc-mem">
                        <div class="pc-mem-av">A</div>
                        <span class="pc-mem-name">Ana pagou mais</span>
                    </div>
                    <div class="pc-cobrar">Cobrar Pix</div>
                </div>
            </div>
        </div>
    </div>
</aside>

@livewireScripts

<script>
window.hbBotStart = function () {
    var chat = document.getElementById('hb-bot-chat');
    if (!chat) return;
    var msgs    = chat.querySelectorAll('[data-msg]');
    var typing  = chat.querySelector('[data-typing]');
    var card    = chat.querySelector('[data-card]');

    msgs.forEach(function(m){ m.classList.remove('show'); });
    if (typing) typing.classList.remove('show');
    if (card)   card.classList.remove('on');

    function s(el, delay){ if(el) setTimeout(function(){ el.classList.add('show'); }, delay); }
    function h(el, delay){ if(el) setTimeout(function(){ el.classList.remove('show'); }, delay); }

    s(msgs[0], 300);
    s(typing,  900);
    h(typing, 1900); s(msgs[1], 1900);
    s(msgs[2], 2700);
    s(typing,  3400);
    h(typing, 4300); s(msgs[3], 4300);
    s(card,    4900);

    setTimeout(function(){
        msgs.forEach(function(m){ m.classList.remove('show'); });
        if (typing) typing.classList.remove('show');
        if (card)   card.classList.remove('on');
        setTimeout(window.hbBotStart, 600);
    }, 8500);
};
</script>
</body>
</html>
