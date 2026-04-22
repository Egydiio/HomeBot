<!DOCTYPE html>
<html lang="pt-BR" x-data="{ sidebarOpen: false, botOpen: false }" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HomeBot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-[#0c0d0f] text-[#eef0f5] font-sans">
@php
    $layoutGroupName = $layoutGroupName ?? 'Minha Casa';
    $layoutPendingClose = $layoutPendingClose ?? 0;
    $layoutPendingPix = $layoutPendingPix ?? 0;
    $layoutRecentTx = $layoutRecentTx ?? collect();

    $navItems = [
        ['route' => 'dashboard', 'icon' => 'home', 'label' => 'Visão geral'],
        ['route' => 'transactions', 'icon' => 'receipt', 'label' => 'Transações'],
        ['route' => 'balance', 'icon' => 'wallet', 'label' => 'Saldo'],
        ['route' => 'monthly-report', 'icon' => 'calendar', 'label' => 'Fechamento'],
        ['route' => 'settings', 'icon' => 'settings', 'label' => 'Configurações'],
    ];

    $pageLabels = [
        'dashboard' => 'Visão geral',
        'transactions' => 'Transações',
        'balance' => 'Saldo',
        'monthly-report' => 'Fechamento',
        'settings' => 'Configurações',
    ];

    $currentRoute = request()->route()?->getName();
@endphp

<div class="flex h-full">
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-black/60 lg:hidden" @click="sidebarOpen = false"></div>

    <aside class="fixed inset-y-0 left-0 z-40 w-64 border-r border-[#1d2028] bg-[#08090a] transition-transform duration-200 lg:static lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-full flex-col">
            <div class="border-b border-[#1d2028] px-5 py-5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-[#1fcc8a] to-[#12a870]">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <path d="M9 2L3 8l6 8 6-8-6-6z" fill="white" fill-opacity="0.9"/>
                            <path d="M6 8l3 4 3-4" stroke="white" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold tracking-tight">HomeBot</div>
                        <div class="text-[11px] text-[#414858]">{{ $layoutGroupName }}</div>
                    </div>
                </a>
            </div>

            <nav class="flex-1 space-y-1 p-3">
                @foreach($navItems as $item)
                    @php $active = request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm transition {{ $active ? 'bg-[rgba(31,204,138,0.1)] text-[#1fcc8a] font-medium' : 'text-[#737a8a] hover:bg-[#1a1c21] hover:text-[#eef0f5]' }}">
                        @include('partials.icon', ['name' => $item['icon'], 'size' => 16])
                        <span>{{ $item['label'] }}</span>
                        @if($item['route'] === 'monthly-report' && $layoutPendingClose > 0)
                            <span class="ml-auto h-1.5 w-1.5 rounded-full bg-[#f5a100]"></span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="space-y-3 border-t border-[#1d2028] p-4">
                <button @click="botOpen = true"
                        class="w-full rounded-lg border border-[rgba(31,204,138,0.25)] bg-[rgba(31,204,138,0.1)] px-3 py-2.5 text-left text-sm font-medium text-[#1fcc8a] transition hover:bg-[rgba(31,204,138,0.15)]">
                    WhatsApp Bot <span class="ml-1 rounded-full bg-[#1fcc8a] px-1.5 py-0.5 text-[10px] font-bold text-black">ON</span>
                </button>

                @auth
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full border border-[rgba(31,204,138,0.27)] bg-[rgba(31,204,138,0.13)] text-xs font-semibold text-[#1fcc8a]">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">{{ auth()->user()->name ?? 'Usuário' }}</div>
                            <div class="text-[11px] text-[#414858]">Administrador</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded p-1 text-[#414858] transition hover:text-[#737a8a]" aria-label="Sair">
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                                    <path d="M13 10H3M7 6l-4 4 4 4M17 4v12"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                @endauth
            </div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 border-b border-[#1d2028] bg-[#0c0d0f]/95 backdrop-blur">
            <div class="flex h-14 items-center gap-3 px-4 sm:px-6">
                <button @click="sidebarOpen = true" class="rounded-lg border border-[#1d2028] p-2 text-[#737a8a] lg:hidden" aria-label="Abrir menu">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M3 5h14M3 10h14M3 15h14" stroke-linecap="round"/>
                    </svg>
                </button>

                <div class="min-w-0 flex-1 text-sm text-[#414858]">
                    <span class="text-[#737a8a]">{{ $layoutGroupName }}</span>
                    <span class="mx-2">/</span>
                    <span class="text-[#eef0f5]">{{ $pageLabels[$currentRoute] ?? 'Dashboard' }}</span>
                </div>

                @if($layoutPendingPix > 0)
                    <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-[rgba(245,161,0,0.2)] bg-[rgba(245,161,0,0.1)] px-2.5 py-1 text-xs font-medium text-[#f5a100]">
                        {{ $layoutPendingPix }} Pix pendente{{ $layoutPendingPix > 1 ? 's' : '' }}
                    </span>
                @endif

                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" class="relative rounded-lg border border-[#1d2028] p-2 text-[#737a8a] transition hover:border-[#2a2f3a]">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M10 2a6 6 0 00-6 6v4l-2 2h16l-2-2V8a6 6 0 00-6-6z"/>
                            <path d="M8 16a2 2 0 004 0"/>
                        </svg>
                        @if($layoutRecentTx->isNotEmpty())
                            <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-[#1fcc8a]"></span>
                        @endif
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 overflow-hidden rounded-xl border border-[#1d2028] bg-[#131517] shadow-2xl">
                        <div class="border-b border-[#1d2028] px-4 py-3 text-sm font-semibold">Notificações</div>
                        @forelse($layoutRecentTx as $tx)
                            <div class="flex items-start gap-2.5 px-4 py-3 {{ !$loop->last ? 'border-b border-[#1d2028]' : '' }}">
                                <div class="mt-0.5 rounded-lg bg-[rgba(31,204,138,0.1)] p-1.5 text-xs">🧾</div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-xs font-medium text-[#eef0f5]">{{ \Illuminate\Support\Str::limit($tx->description, 28) }}</div>
                                    <div class="text-[11px] text-[#414858]">R$ {{ number_format($tx->total_amount, 2, ',', '.') }} · {{ $tx->member?->name }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-6 text-center text-xs text-[#414858]">Sem notificações</div>
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

<div x-show="botOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/60" @click="botOpen = false"></div>
<aside x-show="botOpen" x-cloak x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-md border-l border-[#1d2028] bg-[#111]">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between border-b border-[#1d2028] bg-[#0f3d28] px-4 py-3">
            <div>
                <div class="text-sm font-semibold text-[#e9edef]">HomeBot no WhatsApp</div>
                <div class="text-xs text-[#8696a0]">Exemplo de fluxo automático</div>
            </div>
            <button @click="botOpen = false" class="rounded p-1 text-[#8696a0] transition hover:text-white" aria-label="Fechar">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
            </button>
        </div>
        <div class="flex-1 space-y-3 overflow-auto bg-[#0b1e13] p-4 text-sm text-[#e9edef]">
            <div class="ml-auto max-w-[85%] rounded-[12px_2px_12px_12px] bg-[#005c4b] px-3 py-2">📷 Nota fiscal enviada</div>
            <div class="max-w-[85%] rounded-[2px_12px_12px_12px] bg-[#202c33] px-3 py-2">✅ Nota processada. Qual item e pessoal?</div>
            <div class="ml-auto max-w-[85%] rounded-[12px_2px_12px_12px] bg-[#005c4b] px-3 py-2">A cerveja e pessoal.</div>
            <div class="max-w-[85%] rounded-[2px_12px_12px_12px] bg-[#202c33] px-3 py-2">Perfeito. Saldo da casa atualizado e pronto para fechamento.</div>
        </div>
    </div>
</aside>

@livewireScripts
</body>
</html>
