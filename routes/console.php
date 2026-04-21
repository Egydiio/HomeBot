<?php

use App\Jobs\SendPaymentReminder;
use App\Models\MonthlyClose;
use Illuminate\Support\Facades\Schedule;

Schedule::command('homebot:close-month')->dailyAt('08:00');

// Todo dia às 9h verifica cobranças não pagas e manda lembrete
Schedule::call(function () {
    MonthlyClose::where('status', 'charged')
        ->whereNull('paid_at')
        ->each(function ($close) {
            SendPaymentReminder::dispatch($close->id);
        });
})->dailyAt('09:00');
