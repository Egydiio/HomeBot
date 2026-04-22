<?php

use App\Models\Balance;
use App\Models\Member;
use App\Services\CurrentHouseholdService;
use Livewire\Component;

new class extends Component
{
    public $group;
    public $members;
    public $currentMember;
    public string $splitRule = '50/50';

    public bool $showInviteModal = false;
    public string $inviteName    = '';
    public string $invitePhone   = '';
    public string $invitePixKey  = '';

    public ?int $editingPixMemberId = null;
    public string $editingPixValue  = '';

    public function mount(): void
    {
        $service = app(CurrentHouseholdService::class);
        $this->group = $service->groupForUser(auth()->user());
        $this->currentMember = $service->memberForUser(auth()->user());
        $this->members = $this->group?->members()->get() ?? collect();
    }

    public function setSplitRule(string $rule): void
    {
        $this->splitRule = $rule;
    }

    public function openInviteModal(): void
    {
        $this->inviteName   = '';
        $this->invitePhone  = '';
        $this->invitePixKey = '';
        $this->showInviteModal = true;
    }

    public function closeInviteModal(): void
    {
        $this->showInviteModal = false;
    }

    public function inviteMember(): void
    {
        $this->validate([
            'inviteName'  => 'required|string|max:255',
            'invitePhone' => 'required|string|max:20|unique:members,phone',
        ], [
            'inviteName.required'  => 'O nome é obrigatório.',
            'invitePhone.required' => 'O telefone é obrigatório.',
            'invitePhone.unique'   => 'Este telefone já está cadastrado.',
        ]);

        Member::create([
            'group_id'      => $this->group->id,
            'name'          => $this->inviteName,
            'phone'         => $this->invitePhone,
            'pix_key'       => $this->invitePixKey ?: null,
            'split_percent' => 50,
            'active'        => true,
        ]);

        $this->members = $this->group->members()->get();
        $this->showInviteModal = false;
    }

    public function startEditPix(int $memberId): void
    {
        $this->editingPixMemberId = $memberId;
        $this->editingPixValue    = Member::find($memberId)?->pix_key ?? '';
    }

    public function cancelEditPix(): void
    {
        $this->editingPixMemberId = null;
        $this->editingPixValue    = '';
    }

    public function savePix(): void
    {
        $this->validate([
            'editingPixValue' => 'required|string|max:255',
        ], [
            'editingPixValue.required' => 'A chave Pix é obrigatória.',
        ]);

        Member::where('id', $this->editingPixMemberId)->update(['pix_key' => $this->editingPixValue]);
        $this->members = $this->group->members()->get();
        $this->editingPixMemberId = null;
        $this->editingPixValue    = '';
    }

    public function resetBalances(): void
    {
        Balance::where('group_id', $this->group->id)->delete();
    }

    public function deleteGroup(): void
    {
        $this->group->update(['active' => false]);
        redirect()->route('home');
    }
};
?>

<div class="hb-page max-w-[980px]"
     x-data="{ confirmReset: false, confirmDelete: false }">

    <div class="mb-6">
        <h1 class="hb-title">Configurações</h1>
        <p class="hb-subtitle">{{ $group?->name }}</p>
    </div>

    <div class="flex flex-col gap-4">

        {{-- Members --}}
        <div class="hb-card p-5 pb-6">
            <div class="flex justify-between items-center mb-4">
                <div class="hb-label">Membros da casa</div>
                <button wire:click="openInviteModal" class="hb-button-secondary py-1.5 text-xs">
                    + Convidar
                </button>
            </div>
            <div class="flex flex-col gap-3">
                @forelse($members as $member)
                <div class="flex items-center gap-3">
                    <div class="hb-avatar hb-avatar-accent h-[38px] w-[38px] shrink-0 text-sm">
                        {{ strtoupper(substr($member->name, 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <div class="text-[13px] font-medium text-[#eef0f5]">{{ $member->name }}</div>
                        <div class="text-[11px] text-[#414858] font-mono">{{ $member->phone ?? $member->pix_key ?? 'Sem contato' }}</div>
                    </div>
                    @if($loop->first)
                        <span class="hb-badge-success">Admin</span>
                    @else
                        <span class="hb-badge-neutral">Membro</span>
                    @endif
                </div>
                @empty
                <div class="text-center py-8 text-[12px] text-[#414858]">Nenhum membro cadastrado</div>
                @endforelse
            </div>
        </div>

        {{-- Pix key --}}
        <div class="hb-card p-5">
            <div class="hb-label mb-3.5">Chave Pix</div>
            @php $pixMember = $currentMember ?: $members->first(); @endphp
            @if($editingPixMemberId === $pixMember?->id)
            <div class="flex flex-col gap-2.5 sm:flex-row">
                <input wire:model="editingPixValue"
                       type="text"
                       class="hb-input flex-1 font-mono"
                       placeholder="CPF, e-mail, telefone ou chave aleatória">
                <button wire:click="savePix" class="hb-button-primary px-4 text-xs">
                    Salvar
                </button>
                <button wire:click="cancelEditPix" class="hb-button-secondary px-4 text-xs">
                    Cancelar
                </button>
            </div>
            @error('editingPixValue')
                <span class="text-[12px] text-red-400 mt-1 block">{{ $message }}</span>
            @enderror
            @else
            <div class="flex flex-col gap-2.5 sm:flex-row">
                <div class="flex-1 rounded-lg border border-[#1d2028] bg-[#1a1c21] px-3.5 py-2.5 font-mono text-[13px] text-[#eef0f5]">
                    {{ $pixMember?->pix_key ?? 'Não configurada' }}
                </div>
                <button wire:click="startEditPix({{ $pixMember?->id ?? 0 }})"
                        class="hb-button-secondary px-4 text-xs">
                    Alterar
                </button>
            </div>
            @endif
            <div class="text-[11px] text-[#414858] mt-2">
                Chave usada para receber cobranças do fechamento mensal
            </div>
        </div>

        {{-- Split rules --}}
        <div class="hb-card p-5">
            <div class="hb-label mb-3.5">Regra de divisão padrão</div>
            <div class="flex flex-wrap gap-2">
                @foreach(['50/50', 'Proporcional', 'Personalizado'] as $rule)
                <button
                    type="button"
                    wire:click="setSplitRule('{{ $rule }}')"
                    @class([
                        'rounded-lg border px-4 py-2 text-[13px] transition-colors',
                        'border-[rgba(31,204,138,0.22)] bg-[rgba(31,204,138,0.1)] font-medium text-[#1fcc8a]' => $splitRule === $rule,
                        'border-[#1d2028] bg-transparent font-normal text-[#737a8a] hover:text-[#eef0f5]' => $splitRule !== $rule,
                    ])
                >
                    {{ $rule }}
                </button>
                @endforeach
            </div>
            @if($splitRule === 'Proporcional')
            <div class="mt-3 rounded-md px-3.5 py-2.5 text-[12px] text-[#414858] bg-[#1a1c21]">
                A divisão será calculada com base na renda de cada membro. Configure os percentuais abaixo.
            </div>
            @endif
        </div>

        {{-- WhatsApp --}}
        <div class="hb-card-soft p-5">
            <div class="flex justify-between items-center">
                <div class="flex gap-3 items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-[10px] border border-[rgba(31,204,138,0.22)] bg-[rgba(31,204,138,0.1)]">
                        @include('partials.icon', ['name' => 'whatsapp', 'size' => 20, 'color' => '#1fcc8a'])
                    </div>
                    <div>
                        <div class="text-[13px] font-medium text-[#eef0f5]">WhatsApp conectado</div>
                        <div class="text-[11px] text-[#414858] mt-0.5">
                            {{ $pixMember?->phone ?? '+55 11 00000-0000' }} · Ativo
                        </div>
                    </div>
                </div>
                <span class="hb-badge-success"><span class="h-1 w-1 rounded-full bg-[#1fcc8a]" aria-hidden="true"></span>Conectado</span>
            </div>
            <div class="h-px bg-[#1d2028] my-3.5"></div>
            <div class="text-[12px] text-[#737a8a]">
                Envie fotos de notas fiscais direto no WhatsApp e o bot categoriza automaticamente.
            </div>
        </div>

        {{-- Danger zone --}}
        <div class="rounded-xl border border-[rgba(240,64,64,0.2)] bg-[#131517] p-5">
            <div class="mb-3 text-[11px] font-medium uppercase tracking-[0.07em] text-[#f04040]">Zona de perigo</div>
            <div class="flex flex-wrap gap-2.5">
                <button type="button" x-on:click="confirmReset = true" class="hb-button-danger px-4 py-2 text-xs">
                    Resetar saldos
                </button>
                <button type="button" x-on:click="confirmDelete = true" class="hb-button-danger px-4 py-2 text-xs">
                    Excluir casa
                </button>
            </div>
        </div>

    </div>

    {{-- Invite modal --}}
    @if($showInviteModal)
    <div class="hb-modal-overlay" wire:click.self="closeInviteModal">
        <div class="hb-modal-panel max-w-sm">
            <div class="mb-5 flex items-center justify-between">
                <div class="text-[15px] font-semibold text-[#eef0f5]">Convidar membro</div>
                <button type="button" wire:click="closeInviteModal" class="hb-icon-button" aria-label="Fechar">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="flex flex-col gap-3.5">
                <div class="flex flex-col gap-1.5">
                    <label class="hb-form-label">Nome</label>
                    <input wire:model="inviteName"
                           type="text"
                           placeholder="Nome do morador"
                           class="hb-input @error('inviteName') hb-input-error @enderror">
                    @error('inviteName')
                        <span class="hb-form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="hb-form-label">Telefone (WhatsApp)</label>
                    <input wire:model="invitePhone"
                           type="tel"
                           placeholder="5531999990000"
                           class="hb-input @error('invitePhone') hb-input-error @enderror">
                    @error('invitePhone')
                        <span class="hb-form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="hb-form-label">Chave Pix <span class="font-normal normal-case text-[#414858]">(opcional)</span></label>
                    <input wire:model="invitePixKey"
                           type="text"
                           placeholder="CPF, e-mail ou telefone"
                           class="hb-input">
                </div>
            </div>
            <div class="mt-5 flex gap-2.5">
                <button type="button" wire:click="inviteMember" class="hb-button-primary flex-1">
                    Convidar
                </button>
                <button type="button" wire:click="closeInviteModal" class="hb-button-secondary px-5">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Confirm reset modal --}}
    <div x-show="confirmReset"
         x-cloak
         class="hb-modal-overlay"
         @click.self="confirmReset = false">
        <div class="hb-modal-panel max-w-sm text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-[rgba(240,64,64,0.3)] bg-[rgba(240,64,64,0.1)]">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <path d="M11 8v5M11 15.5v.5M3 11a8 8 0 1016 0A8 8 0 003 11z" stroke="#f04040" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="text-[15px] font-semibold text-[#eef0f5] mb-2">Resetar saldos?</div>
            <div class="text-[13px] text-[#737a8a] mb-5">
                Todos os registros de saldo do grupo serão apagados permanentemente. Esta ação não pode ser desfeita.
            </div>
            <div class="flex gap-2.5">
                <button type="button" wire:click="resetBalances" x-on:click="confirmReset = false" class="hb-button-danger flex-1 py-2.5 text-sm">
                    Resetar
                </button>
                <button type="button" x-on:click="confirmReset = false" class="hb-button-secondary flex-1 py-2.5 text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    {{-- Confirm delete modal --}}
    <div x-show="confirmDelete"
         x-cloak
         class="hb-modal-overlay"
         @click.self="confirmDelete = false">
        <div class="hb-modal-panel max-w-sm text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-[rgba(240,64,64,0.3)] bg-[rgba(240,64,64,0.1)]">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <path d="M6 6l10 10M16 6L6 16" stroke="#f04040" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="text-[15px] font-semibold text-[#eef0f5] mb-2">Excluir casa?</div>
            <div class="text-[13px] text-[#737a8a] mb-5">
                O grupo e todos os dados associados serão desativados. Esta ação não pode ser desfeita.
            </div>
            <div class="flex gap-2.5">
                <button type="button" wire:click="deleteGroup" class="hb-button-danger flex-1 py-2.5 text-sm">
                    Excluir
                </button>
                <button type="button" x-on:click="confirmDelete = false" class="hb-button-secondary flex-1 py-2.5 text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

</div>
