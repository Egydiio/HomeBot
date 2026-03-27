<?php

use Livewire\Volt\Volt;

Volt::route('/',               'dashboard')->name('dashboard');
Volt::route('/transactions',   'transaction-list')->name('transactions');
Volt::route('/balance',        'balance-summary')->name('balance');
Volt::route('/monthly-report', 'monthly-report')->name('monthly-report');
Volt::route('/settings',       'group-settings')->name('settings');
