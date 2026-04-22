<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Página não encontrada · HomeBot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-[#0c0d0f] px-6 text-[#eef0f5] antialiased">
    <div class="max-w-md text-center">
        <a href="{{ route('home') }}" class="mb-10 inline-flex items-center justify-center gap-2.5">
            <x-hb.logo-mark />
            <span class="text-[15px] font-bold tracking-tight">HomeBot</span>
        </a>

        <div class="font-mono text-[96px] font-medium leading-none tracking-tighter text-[#1d2028]">404</div>

        <h1 class="mt-2 text-[22px] font-semibold tracking-tight text-[#eef0f5]">Página não encontrada</h1>
        <p class="mt-2 text-sm leading-relaxed text-[#737a8a]">
            A rota que você tentou acessar não existe.<br>
            Redirecionando em <span id="hb-404-seconds" class="font-mono text-[#1fcc8a]">10</span>
            <span id="hb-404-label"> segundos</span>…
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <x-hb.button href="{{ route('home') }}" variant="primary">Ir para o início</x-hb.button>
            @auth
                <x-hb.button href="{{ route('dashboard') }}" variant="secondary-muted">Dashboard</x-hb.button>
            @endauth
        </div>
    </div>
    <script>
        (function () {
            const home = @json(route('home'));
            const nEl = document.getElementById('hb-404-seconds');
            const lEl = document.getElementById('hb-404-label');
            let s = 10;
            const iv = setInterval(function () {
                s -= 1;
                nEl.textContent = String(s);
                lEl.textContent = s === 1 ? ' segundo' : ' segundos';
                if (s <= 0) {
                    clearInterval(iv);
                    window.location.href = home;
                }
            }, 1000);
        })();
    </script>
</body>
</html>
