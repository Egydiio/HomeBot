<section class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 pb-24 pt-28 text-center sm:px-6 sm:pb-28 sm:pt-32 lg:px-8 lg:pb-32 lg:pt-36">
    <div class="hb-hero-glow" aria-hidden="true"></div>
    <div class="hb-hero-grid" aria-hidden="true"></div>

    <div class="hb-kicker hb-fade-up">
        <svg class="h-3 w-3 shrink-0 text-[#1fcc8a]" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
            <path d="M6 1L1.5 6l4.5 5.3L10.5 6 6 1z"/>
        </svg>
        Gestão financeira doméstica via WhatsApp
    </div>

    <h1 class="hb-display hb-fade-up hb-delay-1">
        Divida compras e contas da casa pelo
        <span class="text-[#1fcc8a]">WhatsApp</span>
    </h1>

    <p class="hb-lead hb-fade-up hb-delay-2">
        Automatize saldos compartilhados e cobranças Pix em poucos minutos. Sem planilhas, sem mensagens confusas no grupo.
    </p>

    <div class="relative mt-9 flex flex-wrap items-center justify-center gap-3 sm:gap-3.5 hb-fade-up hb-delay-3">
        <x-hb.button href="{{ route('register') }}" variant="primary" size="lg">Começar agora →</x-hb.button>
        <x-hb.button href="#como-funciona" variant="secondary-muted" size="lg">Ver como funciona</x-hb.button>
    </div>

    <p class="relative mt-5 text-xs text-[#414858] hb-fade-up hb-delay-4">
        Já usado por <strong class="font-medium text-[#737a8a]">+2.400 casas</strong> em todo o Brasil
    </p>

    <div class="relative mt-10 grid w-full max-w-3xl grid-cols-1 gap-3 sm:grid-cols-3 hb-fade-up hb-delay-4">
        @foreach ([
            ['+89%', 'menos tempo', 'com planilhas e cobranças manuais'],
            ['3 min', 'para fechar o mês', 'com resumo pronto e Pix sugerido'],
            ['24/7', 'via WhatsApp', 'sem instalar app para os moradores'],
        ] as [$value, $label, $desc])
            <div class="rounded-xl border border-[#1d2028] bg-[#111317] p-4 text-left">
                <div class="text-xl font-bold tracking-tight text-[#eef0f5]">{{ $value }}</div>
                <div class="mt-1 text-[13px] font-medium text-[#1fcc8a]">{{ $label }}</div>
                <p class="mt-1.5 text-xs leading-relaxed text-[#737a8a]">{{ $desc }}</p>
            </div>
        @endforeach
    </div>

    <div class="relative mt-16 w-full max-w-5xl sm:mt-20 lg:max-w-[1100px] hb-fade-up hb-delay-5">
        <div class="hb-mock-browser">
            <div class="flex items-center gap-2 border-b border-[#1d2028] bg-[#0c0d0f] px-4 py-3">
                <div class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#ff5f57]" aria-hidden="true"></div>
                <div class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#febc2e]" aria-hidden="true"></div>
                <div class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#28c840]" aria-hidden="true"></div>
                <div class="mx-4 flex h-[22px] flex-1 items-center rounded-md bg-[#1a1c21] px-2.5">
                    <span class="truncate font-mono text-[11px] text-[#414858]">app.homebot.com.br/dashboard</span>
                </div>
            </div>

            <div class="grid gap-3.5 p-5 lg:grid-cols-[180px_minmax(0,1fr)] lg:h-[380px]">
                <div class="flex flex-col gap-1.5 rounded-lg bg-[#08090a] p-3.5">
                    <div class="mb-2 flex items-center gap-2 px-2 py-1.5">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-gradient-to-br from-[#1fcc8a] to-[#12a870]">
                            <svg width="12" height="12" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                                <path d="M9 2L3 8l6 8 6-8-6-6z" fill="white" fill-opacity="0.9"/>
                            </svg>
                        </div>
                        <div class="min-w-0 text-left">
                            <div class="text-[11px] font-bold text-[#eef0f5]">HomeBot</div>
                            <div class="truncate text-[9px] text-[#414858]">Casa dos Pereira</div>
                        </div>
                    </div>
                    @foreach ([['Visão geral', true], ['Transações', false], ['Saldo', false], ['Fechamento', false], ['Config.', false]] as [$label, $active])
                        <div
                            @class([
                                'flex h-7 items-center gap-2 rounded-md px-2.5 text-xs',
                                'bg-[rgba(31,204,138,0.1)] text-[#1fcc8a]' => $active,
                                'text-[#414858]' => ! $active,
                            ])
                        >
                            <span @class(['h-1.5 w-1.5 shrink-0 rounded-full', 'bg-[#1fcc8a]' => $active, 'bg-[#414858]' => ! $active])></span>
                            {{ $label }}
                        </div>
                    @endforeach
                    <div class="mt-auto border-t border-[#1d2028] pt-2">
                        <div class="flex h-7 items-center gap-1.5 rounded-md border border-[rgba(31,204,138,0.2)] bg-[rgba(31,204,138,0.1)] px-2.5 text-xs text-[#1fcc8a]">
                            <svg class="h-2.5 w-2.5 shrink-0 text-[#1fcc8a]" viewBox="0 0 10 10" fill="currentColor" aria-hidden="true">
                                <path d="M5 1L1 5l4 4.5L9 5 5 1z"/>
                            </svg>
                            WhatsApp Bot
                        </div>
                    </div>
                </div>

                <div class="flex min-h-0 flex-col gap-3">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-lg border border-[rgba(31,204,138,0.2)] bg-[rgba(31,204,138,0.06)] p-3">
                            <div class="text-[9px] font-medium uppercase tracking-[0.06em] text-[#414858]">A receber</div>
                            <div class="mt-1.5 font-mono text-lg font-medium text-[#1fcc8a]">R$70,00</div>
                        </div>
                        <div class="rounded-lg border border-[#1d2028] bg-[#1a1c21] p-3">
                            <div class="text-[9px] font-medium uppercase tracking-[0.06em] text-[#414858]">Despesas</div>
                            <div class="mt-1.5 font-mono text-base font-medium text-[#eef0f5]">R$1.840</div>
                        </div>
                        <div class="rounded-lg border border-[#1d2028] bg-[#1a1c21] p-3">
                            <div class="text-[9px] font-medium uppercase tracking-[0.06em] text-[#414858]">Notas</div>
                            <div class="mt-1.5 font-mono text-base font-medium text-[#eef0f5]">23</div>
                        </div>
                        <div class="rounded-lg border border-[rgba(245,161,0,0.2)] bg-[#1a1c21] p-3">
                            <div class="text-[9px] font-medium uppercase tracking-[0.06em] text-[#414858]">Pix</div>
                            <div class="mt-1.5 font-mono text-lg font-medium text-[#f5a100]">R$99,90</div>
                        </div>
                    </div>

                    <div class="grid min-h-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-2">
                        <div class="flex flex-col gap-2.5 rounded-lg border border-[#1d2028] bg-[#1a1c21] p-3">
                            <div class="text-[9px] font-medium uppercase tracking-[0.06em] text-[#414858]">Histórico 6 meses</div>
                            <div class="flex h-[60px] items-end gap-1">
                                @foreach ([40, 55, 48, 62, 65, 72] as $i => $h)
                                    <div
                                        class="flex-1 rounded-[2px]"
                                        style="height: {{ $h }}%; background: {{ $i === 5 ? '#1fcc8a' : '#1d2028' }}"
                                    ></div>
                                @endforeach
                            </div>
                            <div class="flex gap-1">
                                @foreach (['Nov', 'Dez', 'Jan', 'Fev', 'Mar', 'Abr'] as $l)
                                    <span class="flex-1 text-center font-mono text-[8px] text-[#414858]">{{ $l }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 rounded-lg border border-[#1d2028] bg-[#1a1c21] p-3">
                            <div class="text-[9px] font-medium uppercase tracking-[0.06em] text-[#414858]">Transações recentes</div>
                            @foreach ([['🛒 Supermercado', 'R$287,40', '#1fcc8a'], ['⚡ Luz', 'R$156,80', '#737a8a'], ['📡 Internet', 'R$99,90', '#f5a100']] as $i => [$t, $v, $c])
                                <div @class(['flex items-center justify-between py-1.5', 'border-b border-[#1d2028]' => $i < 2])>
                                    <span class="text-[10px] text-[#737a8a]">{{ $t }}</span>
                                    <span class="font-mono text-[10px]" style="color: {{ $c }}">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hb-whatsapp-card" aria-hidden="true">
            <div class="mb-2.5 flex items-center gap-2 border-b border-white/[0.06] pb-2">
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#128c7e] text-sm">💬</div>
                <div class="min-w-0 flex-1 text-left text-[11px] font-semibold text-[#e9edef]">HomeBot</div>
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#1fcc8a]" aria-hidden="true"></span>
            </div>
            <div class="mb-1.5 ml-4 rounded-[0_8px_8px_8px] bg-[#005c4b] px-2.5 py-2 text-right">
                <span class="block text-[11px] leading-snug text-[#e9edef]">📷 [nota_extra.jpg]</span>
                <span class="text-[10px] text-[#8696a0]">14:02 ✓✓</span>
            </div>
            <div class="mb-1.5 rounded-[0_8px_8px_8px] bg-[#202c33] px-2.5 py-2 text-left">
                <span class="block text-[11px] leading-snug text-[#e9edef]">✅ R$287,40 processado! Esse item é 🏠 casa ou 👤 pessoal?</span>
                <span class="text-[10px] text-[#8696a0]">14:02</span>
            </div>
            <div class="mb-1.5 ml-4 rounded-[0_8px_8px_8px] bg-[#005c4b] px-2.5 py-2 text-right">
                <span class="block text-[11px] leading-snug text-[#e9edef]">A cerveja é pessoal</span>
                <span class="text-[10px] text-[#8696a0]">14:03 ✓✓</span>
            </div>
            <div class="rounded-[0_8px_8px_8px] bg-[#202c33] px-2.5 py-2 text-left">
                <span class="block text-[11px] leading-snug text-[#e9edef]">🏠 R$245,40 dividido. Ana te deve <strong class="font-semibold text-[#1fcc8a]">R$30,55</strong></span>
                <span class="text-[10px] text-[#8696a0]">14:03</span>
            </div>
        </div>
    </div>
</section>
