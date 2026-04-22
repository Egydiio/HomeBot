<section id="preco" class="hb-section hb-section-surface">
    <div class="hb-landing-container text-center">
        <p class="hb-section-kicker">Preço</p>
        <h2 class="hb-section-title-center">Sem surpresa no bolso</h2>
        <p class="hb-section-desc-center">Comece grátis. Faça upgrade quando precisar.</p>

        <div class="mt-12 grid grid-cols-1 gap-5 text-left md:mt-14 md:grid-cols-3">
            <div class="hb-pricing-card">
                <div class="mb-2 text-[13px] font-semibold uppercase tracking-wide text-[#737a8a]">Grátis</div>
                <div class="font-mono text-4xl font-medium tracking-tight text-white">
                    <sup class="align-top text-base">R$</sup>0<sub class="text-[13px] font-normal text-[#737a8a]">/mês</sub>
                </div>
                <div class="mt-1 text-xs text-[#414858]">Pra sempre</div>
                <ul class="mt-5 flex flex-col gap-2.5">
                    @foreach (['Até 2 membros', '10 notas por mês', 'Fechamento mensal'] as $f)
                        <li class="flex items-start gap-2 text-[13px] text-[#737a8a]">
                            <span class="shrink-0 text-[#1fcc8a]" aria-hidden="true">✓</span>
                            {{ $f }}
                        </li>
                    @endforeach
                    @foreach (['Pix automático', 'Relatórios avançados'] as $f)
                        <li class="flex items-start gap-2 text-[13px] text-[#414858]">
                            <span class="shrink-0" aria-hidden="true">–</span>
                            {{ $f }}
                        </li>
                    @endforeach
                </ul>
                <x-hb.button href="{{ route('register') }}" variant="secondary-muted" class="mt-6 w-full justify-center">Começar grátis</x-hb.button>
            </div>

            <div class="hb-pricing-featured relative pt-2">
                <span class="absolute -top-3 left-1/2 z-10 -translate-x-1/2 rounded-full bg-[#1fcc8a] px-3 py-0.5 text-[11px] font-bold text-black">Mais popular</span>
                <div class="mb-2 text-[13px] font-semibold uppercase tracking-wide text-[#737a8a]">Casa</div>
                <div class="font-mono text-4xl font-medium tracking-tight text-white">
                    <sup class="align-top text-base">R$</sup>29<sub class="text-[13px] font-normal text-[#737a8a]">/mês</sub>
                </div>
                <div class="mt-1 text-xs text-[#414858]">por residência</div>
                <ul class="mt-5 flex flex-col gap-2.5">
                    @foreach (['Até 6 membros', 'Notas ilimitadas', 'Pix automático', 'Relatórios em PDF', 'Divisão personalizada'] as $f)
                        <li class="flex items-start gap-2 text-[13px] text-[#737a8a]">
                            <span class="shrink-0 text-[#1fcc8a]" aria-hidden="true">✓</span>
                            {{ $f }}
                        </li>
                    @endforeach
                </ul>
                <x-hb.button href="{{ route('register') }}" variant="primary" class="mt-6 w-full justify-center">Começar agora</x-hb.button>
            </div>

            <div class="hb-pricing-card">
                <div class="mb-2 text-[13px] font-semibold uppercase tracking-wide text-[#737a8a]">República</div>
                <div class="font-mono text-4xl font-medium tracking-tight text-white">
                    <sup class="align-top text-base">R$</sup>59<sub class="text-[13px] font-normal text-[#737a8a]">/mês</sub>
                </div>
                <div class="mt-1 text-xs text-[#414858]">por residência</div>
                <ul class="mt-5 flex flex-col gap-2.5">
                    @foreach (['Membros ilimitados', 'Tudo do plano Casa', 'Dashboard por membro', 'API de integração', 'Suporte prioritário'] as $f)
                        <li class="flex items-start gap-2 text-[13px] text-[#737a8a]">
                            <span class="shrink-0 text-[#1fcc8a]" aria-hidden="true">✓</span>
                            {{ $f }}
                        </li>
                    @endforeach
                </ul>
                <x-hb.button
                    href="mailto:egydiio13@gmail.com?subject=HomeBot%20República"
                    variant="secondary-muted"
                    class="mt-6 w-full justify-center text-center"
                >Falar com vendas</x-hb.button>
            </div>
        </div>
    </div>
</section>
