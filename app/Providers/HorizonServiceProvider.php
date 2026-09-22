<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (app()->environment('local')) {
                return true;
            }

            $allowedEmails = array_filter(explode(',', (string) env('HORIZON_ALLOWED_EMAILS', 'admin@smartpos.local')));
            if ($user && in_array($user->email ?? null, $allowedEmails, true)) {
                return true;
            }

            $request = request();
            $roles = $request?->attributes->get('jwt_roles', []) ?? [];
            $normalized = array_map('strtolower', (array) $roles);

            return in_array('admin', $normalized, true) || in_array('super_admin', $normalized, true);
        });
    }
}
