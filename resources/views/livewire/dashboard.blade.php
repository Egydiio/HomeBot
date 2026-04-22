<?php

use App\Services\CurrentHouseholdService;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Livewire\Component;

new class extends Component {

    public $group;
    public int $totalHouseMonth = 0;
    public int $totalTransactions = 0;
    public int $membersCount = 0;
    public int $pendingCount = 0;
    public int $processedCount = 0;
    public $recentTransactions;
    public $members;
    public array $barData = [];
    public array $categories = [];
    public array $memberBalances = [];
    public float $netBalance = 0;
    public $creditMember = null;
    public $debtMember = null;
    public $pendingTransaction = null;
    public $recentActivity;

    public function mount(): void
    {
        $this->group = app(CurrentHouseholdService::class)->groupForUser(auth()->user());

        $transactions = Transaction::with('member')
            ->where('group_id', $this->group?->id)
            ->where('reference_month', now()->startOfMonth())
            ->latest()
            ->get();

        $this->recentTransactions = $transactions->take(4);
        $this->totalHouseMonth = (int) $transactions->sum('house_amount');
        $this->totalTransactions = $transactions->count();
        $this->processedCount = $transactions->count();
        $this->membersCount = $this->group?->members()->where('active', true)->count() ?? 0;
        $this->pendingCount = $transactions->where('status', 'pending')->count();
        $this->pendingTransaction = $transactions->firstWhere('status', 'pending');

        $this->members = $this->group?->members()->where('active', true)->get() ?? collect();

        // Bar chart: last 6 months house spending
        $barData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $total = Transaction::where('group_id', $this->group?->id)
                ->whereYear('reference_month', $month->year)
                ->whereMonth('reference_month', $month->month)
                ->sum('house_amount');
            $barData[] = [
                'label'   => $month->locale('pt_BR')->isoFormat('MMM'),
                'value'   => (float) $total,
                'current' => $i === 0,
            ];
        }
        $this->barData = $barData;

        // Category breakdown from items
        $items = TransactionItem::whereHas('transaction', fn($q) =>
            $q->where('group_id', $this->group?->id)
              ->where('reference_month', now()->startOfMonth())
        )->get();

        $catColors = ['#1fcc8a','#4880f5','#8b5cf6','#f5a100','#414858'];
        $grouped = $items->groupBy('category')->map->sum('value')->sortDesc()->take(5);
        $total = max(1, $grouped->sum());
        $catIndex = 0;
        foreach ($grouped as $name => $value) {
            $this->categories[] = [
                'name'  => $name ?: 'Outros',
                'value' => (float) $value,
                'color' => $catColors[$catIndex % count($catColors)],
                'pct'   => (int) round($value / $total * 100),
            ];
            $catIndex++;
        }
        if (empty($this->categories) && $this->totalHouseMonth > 0) {
            $this->categories = [
                ['name'=>'Mercado','value'=>$this->totalHouseMonth,'color'=>'#1fcc8a','pct'=>100],
            ];
        }

        // Member balance: who paid what
        foreach ($this->members as $member) {
            $paid = Transaction::where('group_id', $this->group?->id)
                ->where('member_id', $member->id)
                ->where('reference_month', now()->startOfMonth())
                ->sum('house_amount');
            $this->memberBalances[$member->id] = [
                'name'    => $member->name,
                'initials'=> strtoupper(substr($member->name, 0, 1)),
                'paid'    => (float) $paid,
            ];
        }

        // Net balance
        $allPaid = array_sum(array_column($this->memberBalances, 'paid'));
        $count   = max(1, count($this->memberBalances));
        $share   = $allPaid / $count;
        foreach ($this->memberBalances as &$mb) {
            $mb['net'] = $mb['paid'] - $share;
        }
        unset($mb);

        $first  = reset($this->memberBalances) ?: null;
        $second = next($this->memberBalances) ?: null;
        if ($first && $second) {
            if ($first['net'] > 0) {
                $this->creditMember = $first;
                $this->debtMember   = $second;
                $this->netBalance   = abs($first['net']);
            } else {
                $this->creditMember = $second;
                $this->debtMember   = $first;
                $this->netBalance   = abs($second['net']);
            }
        }

        $this->recentActivity = Transaction::with('member')
            ->where('group_id', $this->group?->id)
            ->latest()
            ->take(4)
            ->get();
    }
};
?>

<div class="hb-page">
    <div class="mb-6">
        <h1 class="hb-title">Visão geral</h1>
        <p class="hb-subtitle">
            {{ now()->locale('pt_BR')->isoFormat('MMMM YYYY') }} ·
            Fecha em {{ now()->endOfMonth()->diffInDays(now()) }} dias
        </p>
    </div>

    <section class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="hb-card-soft hb-card-body">
            <div class="flex justify-between items-start mb-3">
                <div class="hb-label">
                    {{ ($this->netBalance > 0 && $this->creditMember) ? 'Saldo a receber' : 'Saldo do mês' }}
                </div>
                @include('partials.icon', ['name' => 'wallet', 'size' => 15, 'color' => '#414858'])
            </div>
            <div class="mb-1.5 font-mono text-2xl font-medium text-[#1fcc8a]">
                R$ {{ number_format($netBalance, 2, ',', '.') }}
            </div>
            <div class="text-xs text-[#1fcc8a]">
                @if($creditMember && $debtMember) {{ $debtMember['name'] }} te deve @else Em dia @endif
            </div>
        </article>

        <article class="hb-card hb-card-body">
            <div class="flex justify-between items-start mb-3">
                <div class="hb-label">Despesas do mês</div>
                @include('partials.icon', ['name' => 'chart', 'size' => 15, 'color' => '#414858'])
            </div>
            <div class="mb-1.5 font-mono text-2xl font-medium text-[#eef0f5]">
                R$ {{ number_format($totalHouseMonth, 2, ',', '.') }}
            </div>
            <div class="hb-muted">{{ $totalTransactions }} transações este mês</div>
        </article>

        <article class="hb-card hb-card-body">
            <div class="flex justify-between items-start mb-3">
                <div class="hb-label">Notas processadas</div>
                @include('partials.icon', ['name' => 'scan', 'size' => 15, 'color' => '#414858'])
            </div>
            <div class="mb-1.5 font-mono text-2xl font-medium text-[#eef0f5]">
                {{ $processedCount }}
            </div>
            <div class="hb-muted">Este mês via WhatsApp</div>
        </article>

        <article class="rounded-xl p-5 border {{ $pendingCount > 0 ? 'border-[rgba(245,161,0,0.22)] bg-[rgba(245,161,0,0.07)]' : 'border-[#1d2028] bg-[#131517]' }}">
            <div class="flex justify-between items-start mb-3">
                <div class="hb-label">Pix pendente</div>
                @include('partials.icon', ['name' => 'pix', 'size' => 15, 'color' => '#414858'])
            </div>
            <div class="mb-1.5 font-mono text-2xl font-medium {{ $pendingCount > 0 ? 'text-[#f5a100]' : 'text-[#eef0f5]' }}">
                @if($pendingTransaction) R$ {{ number_format($pendingTransaction->total_amount, 2, ',', '.') }} @else R$ 0,00 @endif
            </div>
            <div class="text-xs {{ $pendingCount > 0 ? 'text-[#f5a100]' : 'text-[#737a8a]' }}">
                @if($pendingCount > 0 && $pendingTransaction) {{ \Illuminate\Support\Str::limit($pendingTransaction->description, 20) }} @else Nenhum pendente @endif
            </div>
        </article>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-4">
            <article class="hb-card hb-card-body">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <div class="hb-label">Histórico de gastos</div>
                        <div class="hb-subtitle mt-0">Últimos 6 meses</div>
                    </div>
                    <span class="hb-badge-neutral">Casa</span>
                </div>
                @php $maxVal = max(1, max(array_column($this->barData, 'value'))); @endphp
                <div class="flex items-end gap-1.5 h-16">
                    @foreach($this->barData as $bar)
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <div class="w-full rounded-[3px] transition-all"
                             style="height: {{ max(4, (int)(($bar['value']/$maxVal)*52)) }}px; background: {{ $bar['current'] ? '#1fcc8a' : '#1d2028' }}; opacity: {{ $bar['current'] ? '1' : '0.6' }}">
                        </div>
                        <span class="text-[9px] font-mono {{ $bar['current'] ? 'text-[#737a8a]' : 'text-[#414858]' }}">
                            {{ $bar['label'] }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </article>

            <article class="hb-card overflow-hidden">
                <div class="px-6 py-[18px] flex justify-between items-center">
                    <div class="hb-label">Últimas transações</div>
                    <a href="{{ route('transactions') }}"
                       class="flex items-center gap-1 text-[12px] text-[#1fcc8a] hover:opacity-80 transition-opacity">
                        Ver todas @include('partials.icon', ['name' => 'arrow_right', 'size' => 12, 'color' => '#1fcc8a'])
                    </a>
                </div>
                <div class="h-px bg-[#1d2028]"></div>

                @forelse($recentTransactions as $tx)
                <div>
                    <div class="flex items-center gap-3 px-6 py-3">
                        <div class="w-[34px] h-[34px] rounded-lg bg-[#1a1c21] flex items-center justify-center text-base flex-shrink-0">
                            {{ $tx->type === 'receipt' ? '🛒' : '📄' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-medium text-[#eef0f5] truncate">{{ $tx->description }}</div>
                            <div class="text-[11px] text-[#414858] mt-0.5">{{ $tx->member?->name }} · {{ $tx->created_at->format('d M') }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-mono text-[13px] text-[#eef0f5]">R$ {{ number_format($tx->house_amount, 2, ',', '.') }}</div>
                            <div class="mt-0.5">
                                @if($tx->status === 'confirmed')
                                    <span class="hb-badge-success"><span class="h-1 w-1 rounded-full bg-[#1fcc8a]" aria-hidden="true"></span>Quitado</span>
                                @elseif($tx->status === 'pending')
                                    <span class="hb-badge-warning"><span class="h-1 w-1 rounded-full bg-[#f5a100]" aria-hidden="true"></span>Pendente</span>
                                @else
                                    <span class="hb-badge-neutral"><span class="h-1 w-1 rounded-full bg-[#414858]" aria-hidden="true"></span>{{ ucfirst($tx->status) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if(!$loop->last)<div class="h-px bg-[#1d2028] mx-0"></div>@endif
                </div>
                @empty
                <div class="px-6 py-12 text-center">
                    <div class="w-12 h-12 rounded-xl bg-[#1a1c21] flex items-center justify-center mx-auto mb-3.5">
                        @include('partials.icon', ['name' => 'receipt', 'size' => 22, 'color' => '#414858'])
                    </div>
                    <div class="text-[14px] font-medium text-[#eef0f5] mb-1.5">Nenhuma transação</div>
                    <div class="text-[12px] text-[#414858]">Envie uma foto de nota fiscal pelo WhatsApp</div>
                </div>
                @endforelse
            </article>
        </div>

        <div class="space-y-4">
            <article class="hb-card hb-card-body">
                <div class="hb-label mb-4">Categorias</div>
                <div class="flex justify-center mb-4">
                    @php
                        $donutR = 46; $donutCx = 70; $donutCy = 70;
                        $donutStroke = 10; $donutCirc = 2 * M_PI * $donutR;
                        $offset = 0;
                        $totalCat = max(1, array_sum(array_column($this->categories, 'value')));
                    @endphp
                    <svg width="140" height="140" style="overflow:visible">
                        <circle cx="{{ $donutCx }}" cy="{{ $donutCy }}" r="{{ $donutR }}"
                                fill="none" stroke="#1d2028" stroke-width="{{ $donutStroke }}" />
                        @foreach($this->categories as $cat)
                            @php
                                $pct = $cat['value'] / $totalCat;
                                $dasharray = $donutCirc;
                                $dashoffset = $donutCirc * (1 - $pct);
                                $rotateOffset = $offset * 360;
                                $offset += $pct;
                            @endphp
                            <circle cx="{{ $donutCx }}" cy="{{ $donutCy }}" r="{{ $donutR }}"
                                    fill="none" stroke="{{ $cat['color'] }}" stroke-width="{{ $donutStroke }}"
                                    stroke-dasharray="{{ $dasharray }}" stroke-dashoffset="{{ $dashoffset }}"
                                    stroke-linecap="butt"
                                    transform="rotate({{ $rotateOffset - 90 }} {{ $donutCx }} {{ $donutCy }})" />
                        @endforeach
                        <text x="{{ $donutCx }}" y="{{ $donutCy - 6 }}" text-anchor="middle"
                              fill="#eef0f5" style="font-family:'DM Mono',monospace; font-size:15px; font-weight:500">
                            R${{ number_format($totalHouseMonth/1000, 1, ',', '.') }}k
                        </text>
                        <text x="{{ $donutCx }}" y="{{ $donutCy + 12 }}" text-anchor="middle"
                              fill="#414858" style="font-family:'DM Sans',sans-serif; font-size:10px">
                            este mês
                        </text>
                    </svg>
                </div>
                <div class="flex flex-col gap-2 min-h-[96px]">
                    @foreach($this->categories as $cat)
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-[2px] flex-shrink-0" style="background: {{ $cat['color'] }}"></div>
                        <span class="flex-1 text-[12px] text-[#737a8a]">{{ $cat['name'] }}</span>
                        <span class="text-[12px] text-[#414858] font-mono">{{ $cat['pct'] }}%</span>
                        <span class="text-[12px] text-[#eef0f5] font-mono">R${{ number_format($cat['value'], 0, ',', '.') }}</span>
                    </div>
                    @endforeach
                </div>
            </article>

            <article class="hb-card-soft hb-card-body">
                <div class="hb-label mb-3">Saldo entre membros</div>
                <div class="flex flex-col gap-2.5">
                    @foreach($memberBalances as $mb)
                    <div class="flex items-center gap-2.5">
                        <div class="hb-avatar hb-avatar-accent h-7 w-7 text-xs">
                            {{ $mb['initials'] }}
                        </div>
                        <div class="flex-1">
                            <div class="text-[13px] text-[#eef0f5]">{{ $mb['name'] }}</div>
                            <div class="text-[11px] text-[#414858]">Pagou R$ {{ number_format($mb['paid'], 2, ',', '.') }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-[13px] {{ $mb['net'] >= 0 ? 'text-[#1fcc8a]' : 'text-[#f04040]' }}">
                                {{ $mb['net'] >= 0 ? '+' : '−' }}R$ {{ number_format(abs($mb['net']), 2, ',', '.') }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @if($creditMember && $debtMember)
                <div class="h-px bg-[#1d2028] my-3"></div>
                <div class="flex items-center justify-between">
                    <span class="text-[12px] text-[#737a8a]">{{ $debtMember['name'] }} deve</span>
                    <span class="font-mono text-[16px] font-medium text-[#1fcc8a]">
                        R$ {{ number_format($netBalance, 2, ',', '.') }}
                    </span>
                </div>
                @endif
            </article>

            <article class="hb-card hb-card-body">
                <div class="hb-label mb-3.5">Atividade recente</div>
                <div class="flex flex-col gap-3">
                    @forelse($recentActivity as $act)
                    <div class="flex gap-2.5 items-start">
                        <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[rgba(31,204,138,0.1)]">
                            <span class="text-xs">🧾</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[12px] font-medium text-[#eef0f5] truncate">{{ Str::limit($act->description, 28) }}</div>
                            <div class="text-[11px] text-[#414858] mt-0.5">{{ $act->member?->name }} · R$ {{ number_format($act->total_amount, 2, ',', '.') }}</div>
                        </div>
                        <div class="text-[10px] text-[#414858] font-mono flex-shrink-0">{{ $act->created_at->diffForHumans(null, true) }}</div>
                    </div>
                    @empty
                    <div class="text-[12px] text-[#414858] text-center py-4">Nenhuma atividade ainda</div>
                    @endforelse
                </div>
            </article>
        </div>
    </section>
</div>
