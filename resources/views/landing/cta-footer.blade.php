<section class="border-t border-[#1a1f2e] bg-[#0c0f14] py-20 text-center sm:py-24" style="position:relative;overflow:hidden">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:600px;height:300px;background:radial-gradient(ellipse,rgba(31,204,138,.07),transparent 65%);pointer-events:none" aria-hidden="true"></div>
    <div class="hb-landing-container max-w-2xl" style="position:relative">
        <h2 style="font-size:clamp(30px,4vw,52px);font-weight:800;letter-spacing:-.05em;color:#edf0f8;text-wrap:balance;margin-bottom:14px">Chega de discussão<br>sobre dinheiro em casa.</h2>
        <p style="font-size:17px;color:#5d6880;margin-bottom:36px;font-family:'IBM Plex Sans',sans-serif">Comece grátis hoje. Sem cartão de crédito.</p>
        <div class="flex flex-wrap items-center justify-center gap-3">
            <x-hb.button href="{{ route('register') }}" variant="primary" size="lg">Começar no WhatsApp →</x-hb.button>
            <x-hb.button href="{{ route('login') }}" variant="secondary-muted" size="lg">Ver o dashboard</x-hb.button>
        </div>
        <p class="mt-5 text-xs" style="color:#323a50">Grátis pra sempre no plano básico · 2 minutos pra configurar</p>
    </div>
</section>

<footer class="border-t border-[#1d2028] bg-[#0a0b0d]">
    <div class="hb-landing-container flex flex-col gap-6 py-10 md:flex-row md:items-center md:justify-between md:gap-8 md:py-8">
        <div class="flex items-center gap-2.5">
            <x-hb.logo-mark size="sm" />
            <span class="text-sm font-semibold text-[#eef0f5]">HomeBot</span>
        </div>
        <p class="text-xs text-[#414858] md:text-center">© {{ now()->year }} HomeBot. Feito no Brasil 🇧🇷</p>
        <div class="flex flex-wrap gap-5 text-xs">
            <a href="{{ route('register') }}" class="text-[#414858] transition-colors hover:text-[#737a8a]">Privacidade</a>
            <a href="{{ route('register') }}" class="text-[#414858] transition-colors hover:text-[#737a8a]">Termos</a>
            <a href="mailto:egydiio13@gmail.com" class="text-[#414858] transition-colors hover:text-[#737a8a]">Contato</a>
        </div>
    </div>
</footer>
