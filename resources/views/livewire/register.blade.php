<?php

use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Livewire\Volt\{action, layout, state};

layout('layouts.auth');

state(['name' => '', 'email' => '', 'phone' => '', 'password' => '', 'password_confirmation' => '']);

$register = action(function () {
    $this->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone' => 'required|string|unique:members,phone',
        'password' => 'required|min:8|confirmed',
    ], [
        'name.required' => 'O nome é obrigatório.',
        'email.required' => 'O e-mail é obrigatório.',
        'email.email' => 'Informe um e-mail válido.',
        'email.unique' => 'Este e-mail já está cadastrado.',
        'phone.required' => 'O telefone é obrigatório.',
        'password.required' => 'A senha é obrigatória.',
        'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        'password.confirmed' => 'A confirmação de senha não confere.',
    ]);

    if (User::whereHas('currentMember', fn ($query) => $query->where('phone', $this->phone))->exists()) {
        $this->addError('phone', 'Este telefone já está vinculado a outro usuário.');
        return;
    }

    [$user, $group] = DB::transaction(function () {
        $member = Member::where('phone', $this->phone)->first();

        if ($member) {
            $member->update([
                'name' => $this->name,
                'active' => true,
            ]);

            $group = $member->group;
        } else {
            $group = Group::create([
                'name' => 'Casa de '.$this->name,
                'slug' => Str::slug('casa-de-'.$this->name.'-'.uniqid()),
                'active' => true,
            ]);

            $member = Member::create([
                'group_id' => $group->id,
                'name' => $this->name,
                'phone' => $this->phone,
                'split_percent' => 50,
                'active' => true,
            ]);
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'current_group_id' => $group->id,
            'current_member_id' => $member->id,
        ]);

        return [$user, $group];
    });

    Auth::login($user);
    session()->regenerate();

    return redirect()->route('dashboard');
});

?>

<div class="w-full max-w-sm">
    <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-3">
        <x-hb.logo-mark size="lg" />
        <span class="text-lg font-semibold tracking-tight text-[#eef0f5]">HomeBot</span>
    </a>

    <div class="hb-auth-card">
        <h1 class="text-base font-semibold text-[#eef0f5]">Criar conta</h1>
        <p class="hb-muted mt-1">Preencha os dados para começar</p>

        <form wire:submit="register" class="mt-6 flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="name" class="hb-form-label">Nome</label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    autocomplete="name"
                    placeholder="Seu nome"
                    class="hb-input @error('name') hb-input-error @enderror"
                >
                @error('name')
                    <span class="hb-form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="email" class="hb-form-label">E-mail</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autocomplete="email"
                    placeholder="seu@email.com"
                    class="hb-input @error('email') hb-input-error @enderror"
                >
                @error('email')
                    <span class="hb-form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="phone" class="hb-form-label">Telefone (WhatsApp)</label>
                <input
                    id="phone"
                    type="tel"
                    wire:model="phone"
                    autocomplete="tel"
                    placeholder="5531999990000"
                    class="hb-input @error('phone') hb-input-error @enderror"
                >
                <span class="hb-form-hint">Formato: código do país + DDD + número (ex: 5531999990000)</span>
                @error('phone')
                    <span class="hb-form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="hb-form-label">Senha</label>
                <input
                    id="password"
                    type="password"
                    wire:model="password"
                    autocomplete="new-password"
                    placeholder="Mínimo 8 caracteres"
                    class="hb-input @error('password') hb-input-error @enderror"
                >
                @error('password')
                    <span class="hb-form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password_confirmation" class="hb-form-label">Confirmar senha</label>
                <input
                    id="password_confirmation"
                    type="password"
                    wire:model="password_confirmation"
                    autocomplete="new-password"
                    placeholder="Repita a senha"
                    class="hb-input"
                >
            </div>

            <button type="submit" class="hb-button-primary mt-1 w-full">
                <span wire:loading.remove wire:target="register">Criar conta</span>
                <span wire:loading wire:target="register" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    Criando…
                </span>
            </button>
        </form>
    </div>

    <p class="mt-5 text-center text-[13px] text-[#414858]">
        Já tem uma conta?
        <a href="{{ route('login') }}" class="font-medium text-[#1fcc8a] transition hover:text-[#1ab87c]">Entrar</a>
    </p>

    <p class="mt-4 text-center text-[11px] text-[#414858]/80">HomeBot v1.0</p>
</div>
