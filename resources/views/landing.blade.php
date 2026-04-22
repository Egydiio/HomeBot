<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HomeBot — Divida contas da casa pelo WhatsApp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="hb-landing-body">
    @include('landing.nav')

    <main>
        @include('landing.hero')
        @include('landing.how')
        @include('landing.benefits')
        @include('landing.pricing')
        @include('landing.faq')
        @include('landing.cta-footer')
    </main>

    @livewireScripts
</body>
</html>
