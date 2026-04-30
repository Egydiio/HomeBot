<nav
    id="main-nav"
    x-data="{ scrolled: false, mobileMenu: false }"
    @scroll.window="scrolled = window.scrollY > 40"
    class="fixed top-0 z-50 w-full border-b transition-all duration-300"
    :class="scrolled ? 'border-[#1d2028] bg-[#0a0b0d]/85 backdrop-blur-2xl' : 'border-transparent bg-transparent'"
>
    <div class="hb-landing-container flex h-16 items-center gap-4 md:gap-8">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
            <x-hb.logo-mark size="md" />
            <span class="text-[15px] font-bold tracking-tight text-[#eef0f5]">HomeBot</span>
        </a>

        <div class="hidden flex-1 items-center gap-8 md:flex">
            <a href="#como" class="text-sm text-[#737a8a] transition-colors hover:text-[#eef0f5]">Como funciona</a>
            <a href="#quem" class="text-sm text-[#737a8a] transition-colors hover:text-[#eef0f5]">Para quem</a>
            <a href="#preco" class="text-sm text-[#737a8a] transition-colors hover:text-[#eef0f5]">Preço</a>
            <a href="#faq" class="text-sm text-[#737a8a] transition-colors hover:text-[#eef0f5]">FAQ</a>
        </div>

        <div class="hidden shrink-0 items-center gap-2.5 md:flex">
            <x-hb.button href="{{ route('login') }}" variant="secondary-muted" size="nav">Entrar</x-hb.button>
            <x-hb.button href="{{ route('register') }}" variant="primary" size="nav">Começar grátis</x-hb.button>
        </div>

        <button
            type="button"
            @click="mobileMenu = !mobileMenu"
            class="ml-auto flex h-10 w-10 items-center justify-center rounded-lg border border-[#1d2028] text-[#eef0f5] md:hidden"
            :aria-expanded="mobileMenu.toString()"
            aria-controls="mobile-nav"
            aria-label="Menu"
        >
            <svg x-show="!mobileMenu" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
            </svg>
            <svg x-show="mobileMenu" x-cloak class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M6 6l12 12M18 6l-12 12" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <div
        id="mobile-nav"
        x-show="mobileMenu"
        x-cloak
        x-transition.opacity
        @click.outside="mobileMenu = false"
        class="border-b border-[#1d2028] bg-[#0f1114] px-4 py-4 md:hidden"
    >
        <div class="hb-landing-container space-y-3">
            <a @click="mobileMenu = false" href="#como" class="block text-sm text-[#737a8a] hover:text-[#eef0f5]">Como funciona</a>
            <a @click="mobileMenu = false" href="#quem" class="block text-sm text-[#737a8a] hover:text-[#eef0f5]">Para quem</a>
            <a @click="mobileMenu = false" href="#preco" class="block text-sm text-[#737a8a] hover:text-[#eef0f5]">Preço</a>
            <a @click="mobileMenu = false" href="#faq" class="block text-sm text-[#737a8a] hover:text-[#eef0f5]">FAQ</a>
            <div class="flex gap-2 border-t border-[#1d2028] pt-3">
                <x-hb.button href="{{ route('login') }}" variant="secondary-muted" class="flex-1 justify-center">Entrar</x-hb.button>
                <x-hb.button href="{{ route('register') }}" variant="primary" class="flex-1 justify-center">Começar</x-hb.button>
            </div>
        </div>
    </div>
</nav>
