<?php

use App\Models\Group;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component
{
    public $transactions;

    public function mount(): void
    {
        $group = Group::where('active', true)->first();

        $this->transactions = Transaction::with('member')
            ->where('group_id', $group?->id)
            ->where('reference_month', now()->startOfMonth())
            ->latest()
            ->get();
    }
};
?>

<div>
    <div class="mb-7">
        <h1 class="text-xl font-semibold tracking-tight">Transações</h1>
        <p class="text-sm text-white/40 mt-1">{{ now()->translatedFormat('F \d\e Y') }}</p>
    </div>

    <div class="bg-[#141618] border border-white/[0.07] rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-white/[0.07] flex items-center justify-between">
            <p class="text-[11px] font-semibold text-white/30 uppercase tracking-widest">
                {{ $transactions->count() }} registros
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                <tr>
                    @foreach(['Descrição','Tipo','Quem pagou','Total','Casa','Status','Data'] as $h)
                        <th class="text-left text-[11px] font-medium text-white/30 uppercase tracking-wider px-6 py-3 border-b border-white/[0.07]">{{ $h }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse($transactions as $t)
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-6 py-3.5 text-sm border-b border-white/[0.04]">{{ $t->description }}</td>
                        <td class="px-6 py-3.5 border-b border-white/[0.04]">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-white/5 text-white/50">
                                {{ $t->type === 'receipt' ? '🧾 Nota' : '📄 Conta' }}
                            </span>
                        </td>
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
                        <td colspan="7" class="px-6 py-16 text-center">
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
