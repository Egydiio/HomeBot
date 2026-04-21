<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Autenticação
Volt::route('/login',    'login')->name('login')->middleware('guest');
Volt::route('/register', 'register')->name('register')->middleware('guest');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

// Painel (protegido)
Route::middleware('auth')->group(function () {
    Volt::route('/',               'dashboard')->name('dashboard');
    Volt::route('/transactions',   'transaction-list')->name('transactions');
    Volt::route('/balance',        'balance-summary')->name('balance');
    Volt::route('/monthly-report', 'monthly-report')->name('monthly-report');
    Volt::route('/settings',       'group-settings')->name('settings');
});
