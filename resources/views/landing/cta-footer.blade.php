<section class="border-t border-[#1d2028] bg-gradient-to-br from-[rgba(31,204,138,0.08)] to-transparent py-20 text-center sm:py-24">
    <div class="hb-landing-container max-w-2xl">
        <div class="text-5xl" aria-hidden="true">🏠</div>
        <h2 class="hb-section-title-center mt-5">Chega de perrengue na hora de dividir a conta</h2>
        <p class="hb-section-desc-center mt-4">Comece grátis hoje. Sem cartão de crédito.</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <x-hb.button href="{{ route('register') }}" variant="primary" size="lg">Começar agora →</x-hb.button>
            <x-hb.button href="{{ route('login') }}" variant="secondary-muted" size="lg">Já tenho conta</x-hb.button>
        </div>
        <p class="mt-4 text-xs text-[#414858]">Grátis pra sempre no plano básico · Sem cartão</p>
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
