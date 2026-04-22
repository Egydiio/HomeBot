<section id="beneficios" class="hb-section hb-section-surface-muted">
    <div class="hb-landing-container">
        <div class="grid grid-cols-1 items-start gap-12 lg:grid-cols-2 lg:items-center lg:gap-20">
            <div class="max-w-xl">
                <p class="hb-section-kicker">Benefícios</p>
                <h2 class="hb-section-title">Feito para quem divide casa de verdade</h2>
                <p class="hb-section-desc">Sem planilhas compartilhadas, sem confusão, sem dívida esquecida.</p>
            </div>

            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                @foreach ([
                    ['rgba(31,204,138,0.1)', '🏠', 'Saldo em tempo real', 'Sempre sabe quem deve quanto. Sem precisar perguntar.'],
                    ['rgba(72,128,245,0.1)', '📊', 'Relatórios mensais', 'Resumo bonito de tudo que foi gasto na casa por categoria.'],
                    ['rgba(245,161,0,0.1)', '⚡', 'Zero atrito', 'Tudo via WhatsApp. Não precisa instalar app ou aprender nada.'],
                    ['rgba(139,92,246,0.1)', '🔒', 'Divisão flexível', '50/50, proporcional ou personalizado. Você define as regras.'],
                ] as [$bg, $ico, $title, $desc])
                    <div class="flex gap-4 rounded-[14px] border border-[#1d2028] bg-[#131517] p-5 transition-colors hover:border-[#2a2f3a]">
                        <div
                            class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[10px] text-lg"
                            style="background: {{ $bg }}"
                            aria-hidden="true"
                        >{{ $ico }}</div>
                        <div class="min-w-0">
                            <h4 class="mb-1 text-sm font-semibold text-white">{{ $title }}</h4>
                            <p class="text-[13px] leading-relaxed text-[#737a8a]">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
