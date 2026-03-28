<?php

use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use function Livewire\Volt\{state, action, layout};

layout('layouts.auth');

state(['name' => '', 'email' => '', 'phone' => '', 'password' => '', 'password_confirmation' => '']);

$register = action(function () {
    $this->validate([
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'phone'    => 'required|string|unique:members,phone',
        'password' => 'required|min:8|confirmed',
    ], [
        'name.required'         => 'O nome é obrigatório.',
        'email.required'        => 'O e-mail é obrigatório.',
        'email.email'           => 'Informe um e-mail válido.',
        'email.unique'          => 'Este e-mail já está cadastrado.',
        'phone.required'        => 'O telefone é obrigatório.',
        'phone.unique'          => 'Este telefone já está cadastrado.',
        'password.required'     => 'A senha é obrigatória.',
        'password.min'          => 'A senha deve ter no mínimo 8 caracteres.',
        'password.confirmed'    => 'A confirmação de senha não confere.',
    ]);

    $user = User::create([
        'name'     => $this->name,
        'email'    => $this->email,
        'password' => Hash::make($this->password),
    ]);

    $group = Group::create([
        'name'   => 'Casa de ' . $this->name,
        'slug'   => Str::slug('casa-de-' . $this->name . '-' . uniqid()),
        'active' => true,
    ]);

    Member::create([
        'group_id'      => $group->id,
        'name'          => $this->name,
        'phone'         => $this->phone,
        'split_percent' => 50,
        'active'        => true,
    ]);

    Auth::login($user);
    session()->regenerate();

    return redirect('/');
});

?>

<div class="w-full max-w-sm">

    {{-- Logo --}}
    <div class="flex items-center gap-3 justify-center mb-8">
        <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-lg">
            🏠
        </div>
        <span class="text-lg font-semibold tracking-tight">HomeBot</span>
    </div>

    {{-- Card --}}
    <div class="bg-[#141618] border border-white/[0.07] rounded-2xl px-8 py-8">

        <h1 class="text-base font-semibold mb-1">Criar conta</h1>
        <p class="text-[13px] text-white/40 mb-6">Preencha os dados para começar</p>

        <form wire:submit="register" class="flex flex-col gap-4">

            {{-- Nome --}}
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-[12px] font-medium text-white/50 uppercase tracking-wider">Nome</label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    autocomplete="name"
                    placeholder="Seu nome"
                    class="bg-[#0e0f11] border border-white/[0.08] rounded-lg px-3.5 py-2.5 text-[13.5px] text-white/90 placeholder-white/20
                           focus:outline-none focus:border-[#1fcc8a]/50 focus:ring-1 focus:ring-[#1fcc8a]/30 transition-all
                           @error('name') border-red-500/50 @enderror"
                >
                @error('name')
                    <span class="text-[12px] text-red-400">{{ $message }}</span>
                @enderror
            </div>

            {{-- Email --}}
            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-[12px] font-medium text-white/50 uppercase tracking-wider">E-mail</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autocomplete="email"
                    placeholder="seu@email.com"
                    class="bg-[#0e0f11] border border-white/[0.08] rounded-lg px-3.5 py-2.5 text-[13.5px] text-white/90 placeholder-white/20
                           focus:outline-none focus:border-[#1fcc8a]/50 focus:ring-1 focus:ring-[#1fcc8a]/30 transition-all
                           @error('email') border-red-500/50 @enderror"
                >
                @error('email')
                    <span class="text-[12px] text-red-400">{{ $message }}</span>
                @enderror
            </div>

            {{-- Telefone --}}
            <div class="flex flex-col gap-1.5">
                <label for="phone" class="text-[12px] font-medium text-white/50 uppercase tracking-wider">Telefone (WhatsApp)</label>
                <input
                    id="phone"
                    type="tel"
                    wire:model="phone"
                    autocomplete="tel"
                    placeholder="5531999990000"
                    class="bg-[#0e0f11] border border-white/[0.08] rounded-lg px-3.5 py-2.5 text-[13.5px] text-white/90 placeholder-white/20
                           focus:outline-none focus:border-[#1fcc8a]/50 focus:ring-1 focus:ring-[#1fcc8a]/30 transition-all
                           @error('phone') border-red-500/50 @enderror"
                >
                <span class="text-[11px] text-white/25">Formato: código do país + DDD + número (ex: 5531999990000)</span>
                @error('phone')
                    <span class="text-[12px] text-red-400">{{ $message }}</span>
                @enderror
            </div>

            {{-- Senha --}}
            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-[12px] font-medium text-white/50 uppercase tracking-wider">Senha</label>
                <input
                    id="password"
                    type="password"
                    wire:model="password"
                    autocomplete="new-password"
                    placeholder="Mínimo 8 caracteres"
                    class="bg-[#0e0f11] border border-white/[0.08] rounded-lg px-3.5 py-2.5 text-[13.5px] text-white/90 placeholder-white/20
                           focus:outline-none focus:border-[#1fcc8a]/50 focus:ring-1 focus:ring-[#1fcc8a]/30 transition-all
                           @error('password') border-red-500/50 @enderror"
                >
                @error('password')
                    <span class="text-[12px] text-red-400">{{ $message }}</span>
                @enderror
            </div>

            {{-- Confirmação de senha --}}
            <div class="flex flex-col gap-1.5">
                <label for="password_confirmation" class="text-[12px] font-medium text-white/50 uppercase tracking-wider">Confirmar senha</label>
                <input
                    id="password_confirmation"
                    type="password"
                    wire:model="password_confirmation"
                    autocomplete="new-password"
                    placeholder="Repita a senha"
                    class="bg-[#0e0f11] border border-white/[0.08] rounded-lg px-3.5 py-2.5 text-[13.5px] text-white/90 placeholder-white/20
                           focus:outline-none focus:border-[#1fcc8a]/50 focus:ring-1 focus:ring-[#1fcc8a]/30 transition-all"
                >
            </div>

            {{-- Submit --}}
            <button
                type="submit"
                class="mt-1 w-full bg-[#1fcc8a] hover:bg-[#1ab87c] text-[#0e0f11] font-semibold text-[13.5px] rounded-lg py-2.5 transition-all
                       flex items-center justify-center gap-2"
            >
                <span wire:loading.remove wire:target="register">Criar conta</span>
                <span wire:loading wire:target="register" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    Criando…
                </span>
            </button>

        </form>
    </div>

    {{-- Link login --}}
    <p class="text-center text-[12.5px] text-white/30 mt-5">
        Já tem uma conta?
        <a href="{{ route('login') }}" class="text-[#1fcc8a] hover:text-[#1ab87c] transition-colors">Entrar</a>
    </p>

    <p class="text-center text-[11px] text-white/20 mt-4">HomeBot v1.0</p>
</div>
