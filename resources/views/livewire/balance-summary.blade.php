<?php

use App\Models\Balance;
use App\Models\Transaction;
use App\Services\CurrentHouseholdService;
use Livewire\Component;

new class extends Component {

    public $members;
    public float $totalCredit = 0;
    public float $totalDebt   = 0;
    public float $net         = 0;
    public array $memberStats = [];
    public ?string $creditName     = null;
    public ?string $debtName       = null;
    public float   $transferAmount = 0;
    public ?string $pixKey         = null;
    public bool    $showPixModal   = false;

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $group = app(CurrentHouseholdService::class)->groupForUser(auth()->user());
        $month = now()->format('Y-m-01');

        $this->totalCredit = 0;
        $this->totalDebt   = 0;
        $this->memberStats = [];

        if (!$group) return;

        $this->members = $group->members()->where('active', true)->get()->map(function ($member) use ($month, $group) {
            $debts   = Balance::with('creditor')->where('debtor_id',  $member->id)->where('reference_month', $month)->get();
            $credits = Balance::with('debtor')->where('creditor_id',  $member->id)->where('reference_month', $month)->get();

            $member->debts   = $debts;
            $member->credits = $credits;
            $member->balance = $credits->sum('amount') - $debts->sum('amount');

            $paid = Transaction::where('group_id', $group->id)
                ->where('member_id', $member->id)
                ->where('reference_month', now()->startOfMonth())
                ->sum('house_amount');

            $member->paid = (float) $paid;

            $this->totalCredit += $credits->sum('amount');
            $this->totalDebt   += $debts->sum('amount');

            return $member;
        });

        $this->net = $this->totalCredit - $this->totalDebt;

        $allPaid = $this->members->sum('paid');
        $share   = $allPaid / max(1, $this->members->count());

        foreach ($this->members as $member) {
            $this->memberStats[$member->id] = [
                'name'     => $member->name,
                'initials' => strtoupper(substr($member->name, 0, 1)),
                'paid'     => $member->paid,
                'share'    => $share,
                'diff'     => $member->paid - $share,
                'pix_key'  => $member->pix_key,
            ];
        }

        $sorted = collect($this->memberStats)->sortByDesc('diff');
        $credit = $sorted->first();
        $debt   = $sorted->last();

        if ($credit && $debt && $credit['name'] !== $debt['name']) {
            $this->creditName     = $credit['name'];
            $this->debtName       = $debt['name'];
            $this->transferAmount = abs($debt['diff']);
            $this->pixKey         = $credit['pix_key'];
        }
    }

    public function generatePix(): void
    {
        $this->showPixModal = true;
    }

    public function closePixModal(): void
    {
        $this->showPixModal = false;
    }
};
?>

<div class="hb-page max-w-[1100px]">

    {{-- Pix Modal --}}
    @if($showPixModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closePixModal"></div>
        <div class="relative z-10 w-full max-w-md rounded-xl border border-[#1d2028] bg-[#131517] p-6 shadow-[0_24px_64px_rgba(0,0,0,0.6)]">
            <div class="mb-5 flex items-center justify-between">
                <div class="text-[15px] font-semibold text-[#eef0f5]">Cobrança Pix</div>
                <button wire:click="closePixModal" type="button" class="hb-icon-button" aria-label="Fechar">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15"/>
                    </svg>
                </button>
            </div>

            <div class="hb-surface-accent mb-4 rounded-lg p-4 text-center">
                <div class="mb-1 text-[11px] text-[#414858]">{{ $debtName }} deve para {{ $creditName }}</div>
                <div class="font-mono text-4xl font-medium tracking-tight text-[#1fcc8a]">
                    R$ {{ number_format($transferAmount, 2, ',', '.') }}
                </div>
            </div>

            @if($pixKey)
            <div class="mb-4">
                <div class="text-[11px] text-[#414858] uppercase tracking-wider mb-2">Chave Pix de {{ $creditName }}</div>
                <div class="flex gap-2 items-center">
                    <div class="flex-1 rounded-lg border border-[#1d2028] bg-[#1a1c21] px-3 py-2.5 font-mono text-[13px] text-[#eef0f5] truncate">
                        {{ $pixKey }}
                    </div>
                    <button
                            type="button"
                            x-data
                            @click="navigator.clipboard.writeText('{{ $pixKey }}').then(()=>{ $el.textContent='✓'; setTimeout(()=>$el.textContent='Copiar',2000) })"
                            class="hb-button-secondary shrink-0 px-3 py-2.5 text-xs"
                    >
                        Copiar
                    </button>
                </div>
            </div>
            @else
            <div class="mb-4 text-[12px] text-[#737a8a] rounded-lg bg-[#1a1c21] px-3 py-2.5">
                Chave Pix não cadastrada para {{ $creditName }}.
                <a href="{{ route('settings') }}" class="hb-link ml-1">Configurar →</a>
            </div>
            @endif

            <div class="flex gap-2 mt-4">
                <button wire:click="closePixModal" class="hb-button-secondary flex-1">
                    Fechar
                </button>
                @if($pixKey)
                <button x-data
                        @click="navigator.clipboard.writeText('{{ $pixKey }}').then(()=>{ $el.textContent='✓ Copiado!'; setTimeout(()=>$el.textContent='Copiar chave Pix',2000) })"
                        class="hb-button-primary flex-1">
                    Copiar chave Pix
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="hb-title">Saldo entre membros</h1>
        <p class="hb-subtitle">
            {{ now()->locale('pt_BR')->isoFormat('MMMM YYYY') }} · Atualizado {{ now()->diffForHumans() }}
        </p>
    </div>

    {{-- Settlement hero --}}
    @if($creditName && $debtName)
    <div class="hb-card-soft mb-4 px-5 py-6 sm:px-8 sm:py-7">
        <div class="text-center mb-2">
            <div class="hb-label">Resultado atual</div>
        </div>

        <div class="grid items-center gap-6 py-6 md:grid-cols-3">
            @php $creditStat = collect($this->memberStats)->firstWhere('name', $creditName); @endphp
            <div class="order-2 text-center md:order-1">
                <div class="hb-avatar hb-avatar-accent mx-auto h-[52px] w-[52px] text-lg">
                    {{ strtoupper(substr($creditName,0,1)) }}
                </div>
                <div class="text-[14px] font-semibold text-[#eef0f5] mt-2.5">{{ $creditName }}</div>
                <div class="font-mono text-[20px] text-[#1fcc8a] mt-1">R$ {{ number_format($creditStat['paid']??0,2,',','.') }}</div>
                <div class="text-[11px] text-[#414858] mt-0.5">pagou</div>
            </div>

            <div class="order-1 text-center md:order-2">
                <div class="mb-2 text-[11px] text-[#414858]">{{ $debtName }} deve para {{ $creditName }}</div>
                <div class="rounded-xl border border-[rgba(31,204,138,0.22)] bg-[rgba(31,204,138,0.1)] px-7 py-3">
                    <div class="font-mono text-3xl font-medium tracking-tight text-[#1fcc8a]">
                        R$ {{ number_format($transferAmount,2,',','.') }}
                    </div>
                </div>
                <div class="flex items-center justify-center gap-1.5 mt-2.5">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="#1fcc8a"><path d="M10 2L2 10l8 8 8-8-8-8zm0 3.4L15.6 11 10 16.6 4.4 11 10 5.4z"/></svg>
                    <span class="text-[12px] text-[#737a8a]">via Pix</span>
                </div>
            </div>

            @php $debtStat = collect($this->memberStats)->firstWhere('name', $debtName); @endphp
            <div class="order-3 text-center">
                <div class="hb-avatar hb-avatar-info mx-auto h-[52px] w-[52px] text-lg">
                    {{ strtoupper(substr($debtName,0,1)) }}
                </div>
                <div class="text-[14px] font-semibold text-[#eef0f5] mt-2.5">{{ $debtName }}</div>
                <div class="font-mono text-[20px] text-[#4880f5] mt-1">R$ {{ number_format($debtStat['paid']??0,2,',','.') }}</div>
                <div class="text-[11px] text-[#414858] mt-0.5">pagou</div>
            </div>
        </div>

        <div class="h-px bg-[#1d2028] mb-4"></div>
        <div class="flex flex-wrap justify-center gap-2">
            <button wire:click="generatePix" class="hb-button-primary">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="#000"><path d="M10 2L2 10l8 8 8-8-8-8zm0 3.4L15.6 11 10 16.6 4.4 11 10 5.4z"/></svg>
                Gerar Pix para {{ $debtName }}
            </button>
            <a href="{{ route('monthly-report') }}">
                <button class="hb-button-secondary">
                    Ver histórico
                </button>
            </a>
        </div>
    </div>
    @else
    <div class="hb-card mb-4 px-6 py-10 text-center">
        <div class="text-[14px] font-medium text-[#eef0f5] mb-1.5">Saldos equilibrados</div>
        <div class="text-[12px] text-[#414858] mb-4">Nenhuma transferência necessária este mês.</div>
        <a href="{{ route('monthly-report') }}" class="hb-button-secondary inline-flex text-xs">Ver histórico de fechamentos</a>
    </div>
    @endif

    {{-- Member cards --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @forelse($members as $member)
        @php $stat = $this->memberStats[$member->id] ?? null; @endphp
        <div class="rounded-xl border border-[#1d2028] bg-[#131517] p-5">
            <div class="flex items-center gap-2.5 mb-4">
                <div class="hb-avatar hb-avatar-accent h-9 w-9 shrink-0 text-sm">
                    {{ strtoupper(substr($member->name,0,1)) }}
                </div>
                <div>
                    <div class="text-[14px] font-semibold text-[#eef0f5]">{{ $member->name }}</div>
                    @if($stat)
                    <span @class(['mt-0.5 inline-flex items-center gap-1 rounded-full px-2 py-px text-[11px] font-medium', 'hb-badge-success' => $stat['diff'] >= 0, 'hb-badge-danger' => $stat['diff'] < 0])>
                        <span @class(['h-1 w-1 rounded-full', 'bg-[#1fcc8a]' => $stat['diff'] >= 0, 'bg-[#f04040]' => $stat['diff'] < 0]) aria-hidden="true"></span>
                        {{ $stat['diff']>=0 ? 'A receber' : 'Deve' }}
                    </span>
                    @endif
                </div>
            </div>

            @if($stat)
            <div class="flex flex-col gap-2.5">
                <div class="flex justify-between"><span class="text-[12px] text-[#737a8a]">Total pago</span><span class="font-mono text-[13px] text-[#eef0f5]">R$ {{ number_format($stat['paid'],2,',','.') }}</span></div>
                <div class="flex justify-between"><span class="text-[12px] text-[#737a8a]">Parte proporcional</span><span class="font-mono text-[13px] text-[#737a8a]">R$ {{ number_format($stat['share'],2,',','.') }}</span></div>
                <div class="flex justify-between"><span class="text-[12px] text-[#737a8a]">Diferença</span>
                    <span class="font-mono text-[13px] {{ $stat['diff']>=0?'text-[#1fcc8a]':'text-[#f04040]' }}">{{ $stat['diff']>=0?'+':'−' }}R$ {{ number_format(abs($stat['diff']),2,',','.') }}</span>
                </div>
            </div>
            @endif

            @if($member->debts->isNotEmpty()||$member->credits->isNotEmpty())
            <div class="h-px bg-[#1d2028] my-3"></div>
            @endif

            @foreach($member->debts as $debt)
            <div class="flex justify-between items-center py-1.5 {{ !$loop->last?'border-b border-[#1d2028]':'' }}">
                <span class="text-[12px] text-[#737a8a]">{{ $debt->creditor?->name }}</span>
                <span class="text-[12px] font-mono text-[#f04040]">R$ {{ number_format($debt->amount,2,',','.') }}</span>
            </div>
            @endforeach

            @foreach($member->credits as $credit)
            <div class="flex justify-between items-center py-1.5 {{ !$loop->last?'border-b border-[#1d2028]':'' }}">
                <span class="text-[12px] text-[#737a8a]">{{ $credit->debtor?->name }}</span>
                <span class="text-[12px] font-mono text-[#1fcc8a]">R$ {{ number_format($credit->amount,2,',','.') }}</span>
            </div>
            @endforeach
        </div>
        @empty
        <div class="col-span-2 rounded-xl border border-[#1d2028] bg-[#131517] px-6 py-16 text-center">
            <div class="text-[14px] font-medium text-[#eef0f5] mb-1.5">Nenhum saldo registrado</div>
            <div class="text-[12px] text-[#414858]">Os saldos aparecem após transações confirmadas</div>
        </div>
        @endforelse
    </div>
</div>
