<?php

use App\Models\Group;
use App\Models\Member;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component
{
    public $group;
    public $transactions;
    public string $typeFilter = 'all';
    public string $memberFilter = 'all';
    public $members;

    public float $totalHouse = 0;
    public float $totalPaid = 0;
    public float $myShare = 0;
    public float $diff = 0;

    public function mount(): void
    {
        $this->group = Group::where('active', true)->first();
        $this->members = $this->group?->members()->where('active', true)->get() ?? collect();
        $this->loadTransactions();
    }

    public function updatedTypeFilter(): void
    {
        $this->loadTransactions();
    }

    public function updatedMemberFilter(): void
    {
        $this->loadTransactions();
    }

    private function loadTransactions(): void
    {
        $query = Transaction::with('member')
            ->where('group_id', $this->group?->id)
            ->where('reference_month', now()->startOfMonth())
            ->latest();

        if ($this->memberFilter !== 'all') {
            $query->whereHas('member', fn($q) => $q->where('name', $this->memberFilter));
        }

        if ($this->typeFilter === 'receipt') {
            $query->where('type', 'receipt');
        } elseif ($this->typeFilter === 'bill') {
            $query->where('type', 'bill');
        }

        $all = Transaction::with('member')
            ->where('group_id', $this->group?->id)
            ->where('reference_month', now()->startOfMonth())
            ->get();

        $this->transactions = $query->get();
        $this->totalHouse = (float) $all->sum('house_amount');
        $memberCount = max(1, $this->members->count());
        $this->myShare = $this->totalHouse / $memberCount;

        $authMember = $this->members->first();
        if ($authMember) {
            $this->totalPaid = (float) $all->where('member_id', $authMember->id)->sum('house_amount');
        }
        $this->diff = $this->totalPaid - $this->myShare;
    }
};
?>

<div class="hb-page">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="hb-title">Transações</h1>
            <p class="hb-subtitle">
                {{ now()->locale('pt_BR')->isoFormat('MMMM YYYY') }} · {{ $transactions->count() }} registros
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="flex rounded-lg border border-[#1d2028] bg-[#131517] p-1">
                @foreach([['all','Todos'],['receipt','Nota'],['bill','Conta']] as [$val, $lbl])
                <button wire:click="$set('typeFilter', '{{ $val }}')"
                        class="rounded-md px-3.5 py-1.5 text-xs transition-all {{ $typeFilter === $val ? 'bg-[#1a1c21] font-medium text-[#eef0f5]' : 'text-[#737a8a] hover:text-[#eef0f5]' }}">
                    {{ $lbl }}
                </button>
                @endforeach
            </div>

            <div class="flex flex-wrap rounded-lg border border-[#1d2028] bg-[#131517] p-1">
                <button wire:click="$set('memberFilter', 'all')"
                        class="rounded-md px-3.5 py-1.5 text-xs transition-all {{ $memberFilter === 'all' ? 'bg-[#1a1c21] font-medium text-[#eef0f5]' : 'text-[#737a8a] hover:text-[#eef0f5]' }}">
                    Todos
                </button>
                @foreach($members as $m)
                <button wire:click="$set('memberFilter', '{{ $m->name }}')"
                        class="rounded-md px-3.5 py-1.5 text-xs transition-all {{ $memberFilter === $m->name ? 'bg-[#1a1c21] font-medium text-[#eef0f5]' : 'text-[#737a8a] hover:text-[#eef0f5]' }}">
                    {{ $m->name }}
                </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="hb-card mb-4 overflow-hidden">
        @forelse($transactions as $tx)
            <div class="border-b border-[#1d2028] last:border-b-0">
                <div class="hidden grid-cols-[minmax(0,2fr)_110px_130px_100px_110px] items-center gap-3 px-6 py-3.5 lg:grid">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#1a1c21] text-[15px]">{{ $tx->type === 'receipt' ? '🛒' : '📄' }}</div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium">{{ \Illuminate\Support\Str::limit($tx->description, 35) }}</div>
                            <div class="text-[11px] text-[#414858]">{{ $tx->created_at->format('d M') }}</div>
                        </div>
                    </div>
                    <div class="font-mono text-sm">R$ {{ number_format($tx->house_amount, 2, ',', '.') }}</div>
                    <div class="flex items-center gap-2">
                        <div class="flex h-[22px] w-[22px] items-center justify-center rounded-full border border-[rgba(31,204,138,0.27)] bg-[rgba(31,204,138,0.13)] text-[10px] font-semibold text-[#1fcc8a]">
                            {{ strtoupper(substr($tx->member?->name ?? 'U', 0, 1)) }}
                        </div>
                        <span class="truncate text-xs text-[#737a8a]">{{ $tx->member?->name }}</span>
                    </div>
                    <span class="hb-badge-neutral">{{ $tx->type === 'receipt' ? 'Nota' : 'Conta' }}</span>
                    <div>
                        @if($tx->status === 'confirmed')
                            <span class="hb-badge-success"><span class="h-1 w-1 rounded-full bg-[#1fcc8a]"></span>Quitado</span>
                        @elseif($tx->status === 'pending')
                            <span class="hb-badge-warning"><span class="h-1 w-1 rounded-full bg-[#f5a100]"></span>Pendente</span>
                        @else
                            <span class="hb-badge-neutral"><span class="h-1 w-1 rounded-full bg-[#414858]"></span>{{ ucfirst($tx->status) }}</span>
                        @endif
                    </div>
                </div>

                <div class="space-y-3 px-4 py-4 lg:hidden">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#1a1c21] text-[15px]">{{ $tx->type === 'receipt' ? '🛒' : '📄' }}</div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">{{ \Illuminate\Support\Str::limit($tx->description, 32) }}</div>
                                <div class="text-[11px] text-[#414858]">{{ $tx->created_at->format('d M') }}</div>
                            </div>
                        </div>
                        <div class="font-mono text-sm">R$ {{ number_format($tx->house_amount, 2, ',', '.') }}</div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-[#737a8a]">{{ $tx->member?->name }}</div>
                        @if($tx->status === 'confirmed')
                            <span class="hb-badge-success">Quitado</span>
                        @elseif($tx->status === 'pending')
                            <span class="hb-badge-warning">Pendente</span>
                        @else
                            <span class="hb-badge-neutral">{{ ucfirst($tx->status) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
        <div class="px-6 py-12 text-center">
            <div class="w-12 h-12 rounded-xl bg-[#1a1c21] flex items-center justify-center mx-auto mb-3.5">
                @include('partials.icon', ['name' => 'receipt', 'size' => 22, 'color' => '#414858'])
            </div>
            <div class="text-[14px] font-medium text-[#eef0f5] mb-1.5">Nenhuma transação</div>
            <div class="text-[12px] text-[#414858]">Tente mudar os filtros acima</div>
        </div>
        @endforelse
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="hb-card hb-card-body">
            <div class="hb-label mb-1.5">Total casa</div>
            <div class="font-mono text-lg font-medium">R$ {{ number_format($totalHouse, 2, ',', '.') }}</div>
        </div>
        <div class="hb-card hb-card-body">
            <div class="hb-label mb-1.5">Sua parte (50%)</div>
            <div class="font-mono text-lg font-medium text-[#737a8a]">R$ {{ number_format($myShare, 2, ',', '.') }}</div>
        </div>
        <div class="hb-card-soft hb-card-body">
            <div class="hb-label mb-1.5">Você pagou</div>
            <div class="font-mono text-lg font-medium text-[#1fcc8a]">R$ {{ number_format($totalPaid, 2, ',', '.') }}</div>
        </div>
        <div class="hb-card hb-card-body">
            <div class="hb-label mb-1.5">{{ $diff >= 0 ? 'A receber' : 'A pagar' }}</div>
            <div class="font-mono text-lg font-medium {{ $diff >= 0 ? 'text-[#1fcc8a]' : 'text-[#f04040]' }}">
                R$ {{ number_format(abs($diff), 2, ',', '.') }}
            </div>
        </div>
    </div>

</div>
