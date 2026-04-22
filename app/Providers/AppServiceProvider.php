<?php

namespace App\Providers;

use App\Models\Group;
use App\Models\MonthlyClose;
use App\Models\Transaction;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Volt::mount([
            resource_path('views/livewire'),
        ]);

        View::composer(['layouts.app', 'layouts::app'], function ($view): void {
            $group = Group::where('active', true)->first();

            $view->with([
                'layoutGroupName'   => $group?->name ?? 'Minha Casa',
                'layoutPendingClose' => MonthlyClose::where('status', 'charged')->count(),
                'layoutPendingPix'  => Transaction::where('status', 'pending')
                    ->whereMonth('created_at', now()->month)
                    ->count(),
                'layoutRecentTx'    => Transaction::with('member')->latest()->take(3)->get(),
            ]);
        });
    }
}
