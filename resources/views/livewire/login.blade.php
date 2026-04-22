<?php

use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{action, layout, state};

layout('layouts.auth');

state(['email' => '', 'password' => '']);

$login = action(function () {
    $this->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt(['email' => $this->email, 'password' => $this->password], false)) {
        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    $this->addError('email', 'E-mail ou senha incorretos.');
});

?>

<div class="w-full max-w-sm">
    <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-3">
        <x-hb.logo-mark size="lg" />
        <span class="text-lg font-semibold tracking-tight text-[#eef0f5]">HomeBot</span>
    </a>

    <div class="hb-auth-card">
        <h1 class="text-base font-semibold text-[#eef0f5]">Entrar no painel</h1>
        <p class="hb-muted mt-1">Digite suas credenciais para continuar</p>

        <form wire:submit="login" class="mt-6 flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="email" class="hb-form-label">E-mail</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autocomplete="email"
                    placeholder="admin@homebot.app"
                    class="hb-input @error('email') hb-input-error @enderror"
                >
                @error('email')
                    <span class="hb-form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="hb-form-label">Senha</label>
                <input
                    id="password"
                    type="password"
                    wire:model="password"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="hb-input @error('password') hb-input-error @enderror"
                >
                @error('password')
                    <span class="hb-form-error">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="hb-button-primary mt-1 w-full">
                <span wire:loading.remove wire:target="login">Entrar</span>
                <span wire:loading wire:target="login" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    Entrando…
                </span>
            </button>
        </form>
    </div>

    <p class="mt-5 text-center text-[13px] text-[#414858]">
        Não tem uma conta?
        <a href="{{ route('register') }}" class="font-medium text-[#1fcc8a] transition hover:text-[#1ab87c]">Criar conta</a>
    </p>

    <p class="mt-4 text-center text-[11px] text-[#414858]/80">HomeBot v1.0</p>
</div>
