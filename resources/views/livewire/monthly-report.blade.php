<?php

use App\Models\Group;
use App\Models\MonthlyClose;
use Livewire\Component;

new class extends Component
{
    public $closes;

    public function mount(): void
    {
        $group = Group::where('active', true)->first();

        $this->closes = MonthlyClose::with(['debtor', 'creditor'])
            ->where('group_id', $group?->id)
            ->latest()
            ->get();
    }
};
?>


<div>
    <div class="mb-7">
        <h1 class="text-xl font-semibold tracking-tight">Fechamento mensal</h1>
        <p class="text-sm text-white/40 mt-1">Histórico de cobranças e pagamentos</p>
    </div>

    @forelse($closes as $close)
        <div class="bg-[#141618] border border-white/[0.07] rounded-xl p-6 mb-4 hover:border-white/[0.12] transition-all">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-7 h-7 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-xs font-semibold text-emerald-400">
                            {{ strtoupper(substr($close->debtor->name, 0, 1)) }}
                        </div>
                        <span class="text-sm font-medium">{{ $close->debtor->name }}</span>
                        <span class="text-white/30 text-sm">→</span>
                        <div class="w-7 h-7 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-xs font-semibold text-white/50">
                            {{ strtoupper(substr($close->creditor->name, 0, 1)) }}
                        </div>
                        <span class="text-sm font-medium">{{ $close->creditor->name }}</span>
                    </div>
                    <p class="text-xs text-white/30">
                        Referência: {{ \Carbon\Carbon::parse($close->reference_month)->translatedFormat('F \d\e Y') }}
                    </p>
                </div>

                <div class="text-right">
                    <p class="text-2xl font-semibold tracking-tight text-white mb-2" style="font-family:'DM Mono',monospace">
                        R$ {{ number_format($close->amount, 2, ',', '.') }}
                    </p>
                    @if($close->status === 'paid')
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Pago
                    </span>
                    @elseif($close->status === 'charged')
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Aguardando
                    </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-white/5 text-white/40">
                        <span class="w-1.5 h-1.5 rounded-full bg-white/20"></span> Pendente
                    </span>
                    @endif
                </div>
            </div>

            @if($close->charged_at || $close->paid_at)
                <div class="mt-4 pt-4 border-t border-white/[0.07] flex gap-6">
                    @if($close->charged_at)
                        <div>
                            <p class="text-[10px] text-white/20 uppercase tracking-widest mb-0.5">Cobrança enviada</p>
                            <p class="text-xs text-white/40">{{ $close->charged_at->format('d/m/Y H:i') }}</p>
                        </div>
                    @endif
                    @if($close->paid_at)
                        <div>
                            <p class="text-[10px] text-white/20 uppercase tracking-widest mb-0.5">Pago em</p>
                            <p class="text-xs text-emerald-400">{{ $close->paid_at->format('d/m/Y H:i') }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <div class="bg-[#141618] border border-white/[0.07] rounded-xl p-16 text-center">
            <div class="text-3xl mb-3">◷</div>
            <div class="text-sm text-white/30">Nenhum fechamento ainda</div>
            <div class="text-xs text-white/20 mt-1">Acontece automaticamente no 5º dia útil de cada mês</div>
        </div>
    @endforelse
</div>
