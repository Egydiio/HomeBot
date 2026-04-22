<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Raiz: landing para guests, redireciona para dashboard se autenticado
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('landing');
})->name('home');

// Autenticação (apenas guests)
Volt::route('/login',    'login')->name('login')->middleware('guest');
Volt::route('/register', 'register')->name('register')->middleware('guest');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('home');
})->name('logout')->middleware('auth');

// Painel (protegido)
Route::middleware('auth')->group(function () {
    Volt::route('/dashboard',      'dashboard')->name('dashboard');
    Volt::route('/transactions',   'transaction-list')->name('transactions');
    Volt::route('/balance',        'balance-summary')->name('balance');
    Volt::route('/monthly-report', 'monthly-report')->name('monthly-report');
    Volt::route('/settings',       'group-settings')->name('settings');
});
