<?php

use App\Models\Group;
use Livewire\Component;

new class extends Component
{
    public $group;
    public $members;

    public function mount(): void
    {
        $this->group   = Group::where('active', true)->first();
        $this->members = $this->group?->members()->get() ?? collect();
    }
};
?>


<div>
    <div class="mb-7">
        <h1 class="text-xl font-semibold tracking-tight">Configurações</h1>
        <p class="text-sm text-white/40 mt-1">Membros e informações do grupo</p>
    </div>

    {{-- Info do grupo --}}
    <div class="bg-[#141618] border border-white/[0.07] rounded-xl p-6 mb-4">
        <p class="text-[11px] font-semibold text-white/30 uppercase tracking-widest mb-4">Grupo</p>
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-2xl">
                🏠
            </div>
            <div>
                <p class="font-semibold text-base">{{ $group?->name ?? 'Sem grupo' }}</p>
                <p class="text-sm text-white/40">{{ $members->count() }} membros ativos</p>
            </div>
            <span class="ml-auto inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Ativo
            </span>
        </div>
    </div>

    {{-- Membros --}}
    <div class="bg-[#141618] border border-white/[0.07] rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-white/[0.07]">
            <p class="text-[11px] font-semibold text-white/30 uppercase tracking-widest">Membros</p>
        </div>

        @forelse($members as $member)
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.04] last:border-0 hover:bg-white/[0.02] transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-sm font-semibold text-emerald-400">
                        {{ strtoupper(substr($member->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium">{{ $member->name }}</p>
                        <p class="text-xs text-white/30 font-mono">{{ $member->phone }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <div class="text-right">
                        <p class="text-[10px] text-white/20 uppercase tracking-widest mb-0.5">Chave Pix</p>
                        <p class="text-xs text-white/50 font-mono">{{ $member->pix_key ?? '—' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-white/20 uppercase tracking-widest mb-0.5">Split</p>
                        <p class="text-sm font-semibold text-emerald-400 font-mono">{{ $member->split_percent }}%</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full
                    {{ $member->active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/5 text-white/30' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $member->active ? 'bg-emerald-400' : 'bg-white/20' }}"></span>
                    {{ $member->active ? 'Ativo' : 'Inativo' }}
                </span>
                </div>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <div class="text-3xl mb-3">⊙</div>
                <div class="text-sm text-white/30">Nenhum membro cadastrado</div>
            </div>
        @endforelse
    </div>
</div>
