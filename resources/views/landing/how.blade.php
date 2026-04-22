<section id="como-funciona" class="hb-section hb-section-surface">
    <div class="hb-landing-container">
        <div class="max-w-xl">
            <p class="hb-section-kicker">Como funciona</p>
            <h2 class="hb-section-title">Simples como mandar uma mensagem</h2>
            <p class="hb-section-desc">Três passos e o HomeBot faz todo o resto automaticamente.</p>
        </div>

        <div class="mt-12 grid grid-cols-1 gap-6 md:mt-14 md:grid-cols-3">
            @foreach ([
                ['01', '📷', 'Manda a nota no WhatsApp', 'Foto da nota fiscal ou cupom fiscal. O bot lê todos os itens em segundos usando OCR com IA.'],
                ['02', '🤖', 'Bot separa e classifica', 'Identifica automaticamente o que é da casa e o que é pessoal. Você confirma em um toque.'],
                ['03', '💸', 'Pix automático no fechamento', 'No fim do mês, o HomeBot calcula tudo e gera o Pix já com o valor exato pra quem deve.'],
            ] as [$num, $icon, $title, $desc])
                <article class="hb-step-card">
                    <div class="mb-4 inline-flex h-[26px] w-[26px] items-center justify-center rounded-md border border-[rgba(31,204,138,0.2)] bg-[rgba(31,204,138,0.1)] font-mono text-[11px] text-[#1fcc8a]">
                        {{ $num }}
                    </div>
                    <div class="mb-3 text-3xl" aria-hidden="true">{{ $icon }}</div>
                    <h3 class="mb-2 text-base font-semibold text-white">{{ $title }}</h3>
                    <p class="text-[13px] leading-relaxed text-[#737a8a]">{{ $desc }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
