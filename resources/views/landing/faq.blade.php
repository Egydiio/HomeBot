<section id="faq" class="hb-section hb-section-surface-muted">
    <div class="hb-landing-container max-w-3xl">
        <p class="hb-section-kicker">FAQ</p>
        <h2 class="hb-section-title">Perguntas frequentes</h2>

        <div class="mt-9 flex flex-col gap-1" x-data="{ open: null }">
            @foreach ([
                ['Preciso dar acesso ao meu WhatsApp?', 'Não. O HomeBot usa um número próprio. Você apenas adiciona o contato e começa a mandar fotos.'],
                ['Como funciona a divisão de despesas pessoais?', 'Quando o bot processa uma nota, ele pergunta se algum item é pessoal. O que for pessoal fica fora da divisão da casa.'],
                ['O Pix é enviado automaticamente?', 'O HomeBot gera o link de pagamento Pix. A confirmação do envio ainda é manual — por segurança, você decide quando pagar.'],
                ['Funciona para repúblicas com muitos moradores?', 'Sim. O plano República suporta membros ilimitados e cada morador tem seu próprio painel com saldo individual.'],
                ['Meus dados financeiros são seguros?', 'Todos os dados são criptografados em trânsito e em repouso. Nunca compartilhamos informações com terceiros.'],
            ] as $i => [$q, $a])
                <div class="overflow-hidden rounded-[14px] border border-[#1d2028] bg-[#131517]">
                    <button
                        type="button"
                        id="faq-trigger-{{ $i }}"
                        @click="open = open === {{ $i }} ? null : {{ $i }}"
                        class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm font-medium text-[#eef0f5] transition-colors"
                        :class="open === {{ $i }} ? 'bg-[#1a1c21]' : 'bg-[#131517] hover:bg-[#1a1c21]'"
                        :aria-expanded="(open === {{ $i }}).toString()"
                        aria-controls="faq-panel-{{ $i }}"
                    >
                        <span>{{ $q }}</span>
                        <span
                            class="shrink-0 text-lg leading-none text-[#414858] transition-transform"
                            :class="open === {{ $i }} ? 'rotate-45' : ''"
                            aria-hidden="true"
                        >+</span>
                    </button>
                    <div
                        id="faq-panel-{{ $i }}"
                        role="region"
                        aria-labelledby="faq-trigger-{{ $i }}"
                        x-show="open === {{ $i }}"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                        class="border-t border-[#1d2028] bg-[#131517] px-5 pb-4 text-[13px] leading-relaxed text-[#737a8a]"
                    >
                        {{ $a }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
