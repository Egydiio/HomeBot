<?php

use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, action, layout};

layout('layouts.auth');

state(['email' => '', 'password' => '']);

$login = action(function () {
    $this->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt(['email' => $this->email, 'password' => $this->password], false)) {
        session()->regenerate();
        return redirect()->intended('/');
    }

    $this->addError('email', 'E-mail ou senha incorretos.');
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

        <h1 class="text-base font-semibold mb-1">Entrar no painel</h1>
        <p class="text-[13px] text-white/40 mb-6">Digite suas credenciais para continuar</p>

        <form wire:submit="login" class="flex flex-col gap-4">

            {{-- Email --}}
            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-[12px] font-medium text-white/50 uppercase tracking-wider">E-mail</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autocomplete="email"
                    placeholder="admin@homebot.app"
                    class="bg-[#0e0f11] border border-white/[0.08] rounded-lg px-3.5 py-2.5 text-[13.5px] text-white/90 placeholder-white/20
                           focus:outline-none focus:border-[#1fcc8a]/50 focus:ring-1 focus:ring-[#1fcc8a]/30 transition-all
                           @error('email') border-red-500/50 @enderror"
                >
                @error('email')
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
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="bg-[#0e0f11] border border-white/[0.08] rounded-lg px-3.5 py-2.5 text-[13.5px] text-white/90 placeholder-white/20
                           focus:outline-none focus:border-[#1fcc8a]/50 focus:ring-1 focus:ring-[#1fcc8a]/30 transition-all
                           @error('password') border-red-500/50 @enderror"
                >
                @error('password')
                    <span class="text-[12px] text-red-400">{{ $message }}</span>
                @enderror
            </div>

            {{-- Submit --}}
            <button
                type="submit"
                class="mt-1 w-full bg-[#1fcc8a] hover:bg-[#1ab87c] text-[#0e0f11] font-semibold text-[13.5px] rounded-lg py-2.5 transition-all
                       flex items-center justify-center gap-2"
            >
                <span wire:loading.remove wire:target="login">Entrar</span>
                <span wire:loading wire:target="login" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    Entrando…
                </span>
            </button>

        </form>
    </div>

    {{-- Link registro --}}
    <p class="text-center text-[12.5px] text-white/30 mt-5">
        Não tem uma conta?
        <a href="{{ route('register') }}" class="text-[#1fcc8a] hover:text-[#1ab87c] transition-colors">Criar conta</a>
    </p>

    <p class="text-center text-[11px] text-white/20 mt-4">HomeBot v1.0</p>
</div>
