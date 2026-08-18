<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            if (str_starts_with($ability, 'permission.')) {
                return $user->hasPermission(str_replace('permission.', '', $ability));
            }

            return null;
        });

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $notifications = $user
                ? Notification::where('user_id', $user->id)->latest()->take(5)->get()
                : collect();

            $view->with('topNotifications', $notifications);
            $view->with('topUnreadCount', $user ? Notification::where('user_id', $user->id)->whereNull('read_at')->count() : 0);
        });
    }
}