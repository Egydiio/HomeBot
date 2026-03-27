<?php

use App\Models\Balance;
use App\Models\Group;
use Livewire\Component;

new class extends Component {

    public $members;
    public int $totalCredit = 0;
    public int $totalDebt = 0;
    public int $net = 0;

    public function mount(): void
    {
        $group = Group::where('active', true)->first();
        $month = now()->format('Y-m-01');

        if (!$group) return;

        $this->members = $group->members()->where('active', true)->get()->map(function ($member) use ($month) {
            $debts = Balance::with('creditor')->where('debtor_id', $member->id)->where('reference_month', $month)->get();
            $credits = Balance::with('debtor')->where('creditor_id', $member->id)->where('reference_month', $month)->get();

            $member->debts = $debts;
            $member->credits = $credits;
            $member->balance = $credits->sum('amount') - $debts->sum('amount');

            $this->totalCredit += $credits->sum('amount');
            $this->totalDebt += $debts->sum('amount');

            return $member;
        });

        $this->net = $this->totalCredit - $this->totalDebt;
    }
};
?>

<div>
    <div class="mb-7">
        <h1 class="text-xl font-semibold tracking-tight">Saldo do mês</h1>
        <p class="text-sm text-white/40 mt-1">{{ now()->translatedFormat('F \d\e Y') }}</p>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-[#141618] border border-white/[0.07] rounded-xl p-5 hover:border-white/[0.12] transition-all">
            <p class="text-[10px] font-semibold text-white/30 uppercase tracking-widest mb-2">Te devem</p>
            <p class="text-2xl font-semibold text-emerald-400 tracking-tight" style="font-family:'DM Mono',monospace">
                R$ {{ number_format($totalCredit, 2, ',', '.') }}
            </p>
        </div>
        <div class="bg-[#141618] border border-white/[0.07] rounded-xl p-5 hover:border-white/[0.12] transition-all">
            <p class="text-[10px] font-semibold text-white/30 uppercase tracking-widest mb-2">Você deve</p>
            <p class="text-2xl font-semibold text-red-400 tracking-tight" style="font-family:'DM Mono',monospace">
                R$ {{ number_format($totalDebt, 2, ',', '.') }}
            </p>
        </div>
        <div class="bg-[#141618] border border-white/[0.07] rounded-xl p-5 hover:border-white/[0.12] transition-all">
            <p class="text-[10px] font-semibold text-white/30 uppercase tracking-widest mb-2">
                {{ $net >= 0 ? 'Saldo a receber' : 'Saldo a pagar' }}
            </p>
            <p class="text-2xl font-semibold {{ $net >= 0 ? 'text-emerald-400' : 'text-red-400' }} tracking-tight" style="font-family:'DM Mono',monospace">
                R$ {{ number_format(abs($net), 2, ',', '.') }}
            </p>
        </div>
    </div>

    @forelse($members as $member)
        <div class="bg-[#141618] border border-white/[0.07] rounded-xl overflow-hidden mb-4 hover:border-white/[0.12] transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.07]">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-xs font-semibold text-emerald-400">
                        {{ strtoupper(substr($member->name, 0, 1)) }}
                    </div>
                    <span class="font-medium text-sm">{{ $member->name }}</span>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full
                {{ $member->balance >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $member->balance >= 0 ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                {{ $member->balance >= 0 ? 'a receber' : 'a pagar' }}
                R$ {{ number_format(abs($member->balance), 2, ',', '.') }}
            </span>
            </div>

            @if($member->debts->isNotEmpty())
                <div class="px-6 py-3 border-b border-white/[0.04]">
                    <p class="text-[10px] font-semibold text-white/20 uppercase tracking-widest mb-2">Deve para</p>
                    @foreach($member->debts as $debt)
                        <div class="flex justify-between items-center py-2 border-b border-white/[0.04] last:border-0">
                            <span class="text-sm text-white/60">{{ $debt->creditor->name }}</span>
                            <span class="text-sm font-mono text-red-400">R$ {{ number_format($debt->amount, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($member->credits->isNotEmpty())
                <div class="px-6 py-3">
                    <p class="text-[10px] font-semibold text-white/20 uppercase tracking-widest mb-2">Tem a receber</p>
                    @foreach($member->credits as $credit)
                        <div class="flex justify-between items-center py-2 border-b border-white/[0.04] last:border-0">
                            <span class="text-sm text-white/60">{{ $credit->debtor->name }}</span>
                            <span class="text-sm font-mono text-emerald-400">R$ {{ number_format($credit->amount, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="bg-[#141618] border border-white/[0.07] rounded-xl p-16 text-center">
            <div class="text-3xl mb-3">◈</div>
            <div class="text-sm text-white/30">Nenhum saldo registrado ainda</div>
        </div>
    @endforelse
</div>
