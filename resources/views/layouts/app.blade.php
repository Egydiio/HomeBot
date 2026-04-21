<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HomeBot</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#0e0f11] text-[#e8e9eb] font-sans min-h-screen" style="font-family: 'DM Sans', sans-serif">

{{-- Sidebar --}}
<aside class="fixed left-0 top-0 bottom-0 w-60 bg-[#141618] border-r border-white/[0.07] flex flex-col px-4 py-7 z-10">

    {{-- Logo --}}
    <div class="flex items-center gap-3 px-3 mb-8">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-base">
            🏠
        </div>
        <span class="text-base font-semibold tracking-tight">HomeBot</span>
    </div>

    {{-- Nav --}}
    <p class="text-[10px] font-semibold text-white/20 uppercase tracking-widest px-3 mb-2">Menu</p>

    <nav class="flex flex-col gap-0.5">
        @php
            $links = [
                ['route' => 'dashboard',      'icon' => '▣', 'label' => 'Dashboard'],
                ['route' => 'transactions',   'icon' => '≡', 'label' => 'Transações'],
                ['route' => 'balance',         'icon' => '◈', 'label' => 'Saldo'],
                ['route' => 'monthly-report',  'icon' => '◷', 'label' => 'Fechamento'],
                ['route' => 'settings',        'icon' => '⊙', 'label' => 'Configurações'],
            ];
        @endphp

        @foreach($links as $link)
            <a href="{{ route($link['route']) }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13.5px] transition-all
                      {{ request()->routeIs($link['route'])
                          ? 'bg-emerald-500/10 text-emerald-400 font-medium border-l-2 border-emerald-400'
                          : 'text-white/40 hover:bg-white/5 hover:text-white/80' }}">
                <span class="w-5 text-center text-sm">{{ $link['icon'] }}</span>
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>

    {{-- Footer --}}
    <div class="mt-auto pt-4 border-t border-white/[0.07] px-3 flex flex-col gap-3">
        @auth
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13.5px] text-white/40 hover:bg-white/5 hover:text-white/80 transition-all text-left">
                <span class="w-5 text-center text-sm">⏻</span>
                Sair
            </button>
        </form>
        @endauth
        <p class="text-[11px] text-white/20">HomeBot v1.0</p>
    </div>
</aside>

{{-- Main --}}
<main class="ml-60 p-10 min-h-screen">
    {{ $slot }}
</main>

@livewireScripts
</body>
</html>
