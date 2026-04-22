<?php

use App\Models\Group;
use App\Models\MonthlyClose;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component
{
    public $closes;
    public $group;
    public ?string $flash = null;

    public function mount(): void
    {
        $this->group = Group::where('active', true)->first();

        $this->closes = MonthlyClose::with(['debtor', 'creditor'])
            ->where('group_id', $this->group?->id)
            ->latest()
            ->get();
    }

    public function printReport(): void
    {
        // Dispara window.print() via evento Alpine/JS
        $this->dispatch('print-report');
    }

    public function shareReport(int $closeId): void
    {
        $this->dispatch('copy-link', url: url()->current() . '#close-' . $closeId);
    }
};
?>

<div class="hb-page max-w-[1000px]"
     x-data
     @print-report.window="window.print()"
     @copy-link.window="navigator.clipboard.writeText($event.detail.url).then(() => { $dispatch('toast', { msg: 'Link copiado!' }) })">

    <div class="mb-6">
        <h1 class="hb-title">Fechamento mensal</h1>
        <p class="hb-subtitle">Histórico de cobranças e pagamentos</p>
    </div>

    @forelse($closes as $close)
    @php
        $month = \Carbon\Carbon::parse($close->reference_month);

        $debtorPaid = Transaction::where('member_id', $close->debtor_id)
            ->where('group_id', $this->group?->id)
            ->whereYear('reference_month', $month->year)
            ->whereMonth('reference_month', $month->month)
            ->sum('house_amount');

        $creditorPaid = Transaction::where('member_id', $close->creditor_id)
            ->where('group_id', $this->group?->id)
            ->whereYear('reference_month', $month->year)
            ->whereMonth('reference_month', $month->month)
            ->sum('house_amount');

        $members = [
            ['name' => $close->debtor->name,   'paid' => (float)$debtorPaid, 'share' => $close->amount, 'initials' => strtoupper(substr($close->debtor->name, 0, 1)),   'color' => '#1fcc8a'],
            ['name' => $close->creditor->name, 'paid' => (float)$creditorPaid,  'share' => $close->amount, 'initials' => strtoupper(substr($close->creditor->name, 0, 1)), 'color' => '#4880f5'],
        ];

        $totalMonth = $debtorPaid + $creditorPaid;

        // Category breakdown
        $catBreakdown = \App\Models\TransactionItem::whereHas('transaction', fn($q) =>
            $q->where('group_id', $this->group?->id)
              ->whereYear('reference_month', $month->year)
              ->whereMonth('reference_month', $month->month)
        )->get()->groupBy('category')->map->sum('value')->sortDesc()->take(7);
    @endphp

    <div class="mb-3.5 rounded-xl border border-[rgba(31,204,138,0.22)] bg-[#131517] p-5 sm:p-8">

        {{-- Invoice header --}}
        <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="mb-2 flex items-center gap-2.5">
                    <x-hb.logo-mark />
                    <div class="text-base font-bold text-[#eef0f5]">HomeBot</div>
                </div>
                <div class="text-[11px] text-[#414858]">{{ $this->group?->name }}</div>
            </div>
            <div class="text-right">
                <div class="text-[12px] text-[#414858]">Competência</div>
                <div class="text-[15px] font-semibold text-[#eef0f5]">
                    {{ $month->locale('pt_BR')->isoFormat('MMMM YYYY') }}
                </div>
                <div class="mt-1.5">
                    @if($close->status === 'paid')
                        <span class="hb-badge-success"><span class="h-1 w-1 rounded-full bg-[#1fcc8a]" aria-hidden="true"></span>Pix Pago ✓</span>
                    @elseif($close->status === 'charged')
                        <span class="hb-badge-warning"><span class="h-1 w-1 rounded-full bg-[#f5a100]" aria-hidden="true"></span>Aguardando</span>
                    @else
                        <span class="hb-badge-neutral"><span class="h-1 w-1 rounded-full bg-[#414858]" aria-hidden="true"></span>Pendente</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="h-px bg-[#1d2028] mb-6"></div>

        {{-- Member breakdown --}}
        <div class="mb-6 grid grid-cols-1 gap-5 md:grid-cols-2">
            @foreach($members as $m)
            <div class="rounded-lg p-4 bg-[#1a1c21]">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold flex-shrink-0"
                         style="background: {{ $m['color'] }}22; border: 1.5px solid {{ $m['color'] }}44; color: {{ $m['color'] }}">
                        {{ $m['initials'] }}
                    </div>
                    <span class="text-[13px] font-medium text-[#eef0f5]">{{ $m['name'] }}</span>
                </div>
                <div class="flex flex-col gap-2">
                    <div class="flex justify-between">
                        <span class="text-[12px] text-[#737a8a]">Total pago</span>
                        <span class="font-mono text-[12px] text-[#eef0f5]">R$ {{ number_format($m['paid'], 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[12px] text-[#737a8a]">Cota (50%)</span>
                        <span class="font-mono text-[12px] text-[#eef0f5]">R$ {{ number_format($m['share'] / 2, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Line items --}}
        <div class="text-[11px] font-medium tracking-[0.07em] uppercase text-[#414858] mb-3">Composição das despesas</div>

        @if($catBreakdown->isNotEmpty())
            @foreach($catBreakdown as $catName => $catVal)
            <div>
                <div class="flex justify-between py-2.5">
                    <span class="text-[13px] text-[#737a8a]">{{ $catName ?: 'Outros' }}</span>
                    <span class="font-mono text-[13px] text-[#eef0f5]">R$ {{ number_format($catVal, 2, ',', '.') }}</span>
                </div>
                @if(!$loop->last)<div class="h-px bg-[#1d2028]"></div>@endif
            </div>
            @endforeach
        @else
            <div class="text-[12px] text-[#414858] py-4">Nenhum item detalhado disponível</div>
        @endif

        <div class="h-px bg-[#1d2028] my-2"></div>
        <div class="flex justify-between items-center mb-5">
            <span class="text-[14px] font-semibold text-[#eef0f5]">Total do mês</span>
            <span class="font-mono text-[20px] font-medium text-[#eef0f5]">
                R$ {{ number_format($close->amount * 2, 2, ',', '.') }}
            </span>
        </div>

        {{-- Transfer box --}}
        <div class="rounded-lg border border-[rgba(31,204,138,0.22)] bg-[rgba(31,204,138,0.07)] px-5 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-[12px] text-[#737a8a] mb-1">Transferência gerada</div>
                    <div class="text-[14px] text-[#eef0f5]">
                        <strong>{{ $close->debtor->name }}</strong>
                        <span class="text-[#414858] mx-1">→</span>
                        <strong>{{ $close->creditor->name }}</strong>
                    </div>
                    @if($close->paid_at)
                    <div class="text-[11px] text-[#414858] mt-1">
                        Pago via Pix · {{ $close->paid_at->format('d M Y · H:i') }}
                    </div>
                    @elseif($close->charged_at)
                    <div class="text-[11px] text-[#414858] mt-1">
                        Cobrança enviada · {{ $close->charged_at->format('d M Y · H:i') }}
                    </div>
                    @endif
                </div>
                <div class="text-right">
                    <div class="font-mono text-[22px] font-medium text-[#1fcc8a]">
                        R$ {{ number_format($close->amount, 2, ',', '.') }}
                    </div>
                    @if($close->status === 'paid')
                    <div class="mt-1.5">
                        <span class="hb-badge-success"><span class="h-1 w-1 rounded-full bg-[#1fcc8a]" aria-hidden="true"></span>Confirmado</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-2.5">
        <button wire:click="printReport"
                class="hb-button-primary">
            Baixar PDF
        </button>
        <button wire:click="shareReport({{ $close->id }})"
                class="hb-button-secondary">
            Compartilhar
        </button>
    </div>

    @empty
    <div class="hb-card px-6 py-16 text-center">
        <div class="w-12 h-12 rounded-xl bg-[#1a1c21] flex items-center justify-center mx-auto mb-3.5">
            @include('partials.icon', ['name' => 'calendar', 'size' => 22, 'color' => '#414858'])
        </div>
        <div class="text-[14px] font-medium text-[#eef0f5] mb-1.5">Nenhum fechamento ainda</div>
        <div class="text-[12px] text-[#414858]">Acontece automaticamente no 5º dia útil de cada mês</div>
    </div>
    @endforelse

</div>
