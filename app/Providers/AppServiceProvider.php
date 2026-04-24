<?php

namespace App\Providers;

use App\Models\MonthlyClose;
use App\Models\Transaction;
use App\Services\CurrentHouseholdService;
use App\Services\WhatsApp\LocalWhatsAppWebClient;
use App\Services\WhatsApp\WhatsAppClientInterface;
use App\Services\WhatsApp\ZApiWhatsAppClient;
use Illuminate\Support\Facades\Auth;
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
        $this->app->bind(WhatsAppClientInterface::class, function ($app) {
            return match (config('whatsapp.default', 'webjs')) {
                'zapi' => $app->make(ZApiWhatsAppClient::class),
                default => $app->make(LocalWhatsAppWebClient::class),
            };
        });
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
            $group = app(CurrentHouseholdService::class)->groupForUser(Auth::user());

            $view->with([
                'layoutGroupName' => $group?->name ?? 'Minha Casa',
                'layoutPendingClose' => MonthlyClose::query()
                    ->when($group, fn ($query) => $query->where('group_id', $group->id), fn ($query) => $query->whereRaw('1 = 0'))
                    ->where('status', 'charged')
                    ->count(),
                'layoutPendingPix' => Transaction::where('status', 'pending')
                    ->when($group, fn ($query) => $query->where('group_id', $group->id), fn ($query) => $query->whereRaw('1 = 0'))
                    ->whereMonth('created_at', now()->month)
                    ->count(),
                'layoutRecentTx' => Transaction::with('member')
                    ->when($group, fn ($query) => $query->where('group_id', $group->id), fn ($query) => $query->whereRaw('1 = 0'))
                    ->latest()
                    ->take(3)
                    ->get(),
            ]);
        });
    }
}
