<?php

use App\Models\Group;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {

    public int $totalHouseMonth = 0;
    public int $totalTransactions = 0;
    public int $membersCount = 0;
    public int $pendingCount = 0;
    public $recentTransactions;

    public function mount(): void
    {
        $group = Group::where('active', true)->first();

        $transactions = Transaction::with('member')
            ->where('group_id', $group?->id)
            ->where('reference_month', now()->startOfMonth())
            ->latest()
            ->take(10)
            ->get();

        $this->recentTransactions = $transactions;
        $this->totalHouseMonth = $transactions->sum('house_amount');
        $this->totalTransactions = $transactions->count();
        $this->membersCount = $group?->members()->where('active', true)->count() ?? 0;
        $this->pendingCount = $transactions->where('status', 'pending')->count();
    }
};
?>


<div>
    {{-- Header --}}
    <div class="mb-7">
        <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
        <p class="text-sm text-white/40 mt-1">{{ now()->translatedFormat('F \d\e Y') }}</p>
    </div>

    {{-- Metrics --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        @php
            $metrics = [
                ['label' => 'Total da casa',  'value' => 'R$ ' . number_format($totalHouseMonth, 2, ',', '.'), 'color' => 'text-emerald-400'],
                ['label' => 'Transações',     'value' => $totalTransactions, 'color' => 'text-white'],
                ['label' => 'Membros',        'value' => $membersCount, 'color' => 'text-white'],
                ['label' => 'Pendentes',      'value' => $pendingCount, 'color' => $pendingCount > 0 ? 'text-red-400' : 'text-white'],
            ];
        @endphp

        @foreach($metrics as $m)
            <div class="bg-[#141618] border border-white/[0.07] rounded-xl p-5 hover:border-white/[0.12] transition-all hover:-translate-y-0.5">
                <p class="text-[10px] font-semibold text-white/30 uppercase tracking-widest mb-2">{{ $m['label'] }}</p>
                <p class="text-2xl font-semibold {{ $m['color'] }} tracking-tight" style="font-family:'DM Mono',monospace">{{ $m['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Tabela --}}
    <div class="bg-[#141618] border border-white/[0.07] rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-white/[0.07]">
            <p class="text-[11px] font-semibold text-white/30 uppercase tracking-widest">Últimas transações</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                <tr>
                    @foreach(['Descrição','Quem pagou','Total','Casa','Status','Data'] as $h)
                        <th class="text-left text-[11px] font-medium text-white/30 uppercase tracking-wider px-6 py-3 border-b border-white/[0.07]">{{ $h }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse($recentTransactions as $t)
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-6 py-3.5 text-sm border-b border-white/[0.04]">{{ $t->description }}</td>
                        <td class="px-6 py-3.5 text-sm text-white/60 border-b border-white/[0.04]">{{ $t->member->name }}</td>
                        <td class="px-6 py-3.5 text-sm border-b border-white/[0.04] font-mono">R$ {{ number_format($t->total_amount, 2, ',', '.') }}</td>
                        <td class="px-6 py-3.5 text-sm border-b border-white/[0.04] font-mono text-emerald-400">R$ {{ number_format($t->house_amount, 2, ',', '.') }}</td>
                        <td class="px-6 py-3.5 border-b border-white/[0.04]">
                            @if($t->status === 'confirmed')
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Confirmado
                                </span>
                            @elseif($t->status === 'processed')
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Processando
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-white/5 text-white/40">
                                    <span class="w-1.5 h-1.5 rounded-full bg-white/20"></span> Pendente
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-sm text-white/30 border-b border-white/[0.04]">{{ $t->created_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="text-3xl mb-3">🧾</div>
                            <div class="text-sm text-white/30">Nenhuma transação ainda</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
