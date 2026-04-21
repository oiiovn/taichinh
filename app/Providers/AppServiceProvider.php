<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

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
        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            $user = $request->user();
            if ($user instanceof User && ! $user->is_admin && $user->canManageFoodThongKeBuff()) {
                return route('food.thong-ke-buff');
            }
            if ($user instanceof User && $user->isFoodThongKeBuffOnlyUser()) {
                return route('food.thong-ke-buff');
            }
            if ($user instanceof User && $user->isFoodBuffOrderOnlyUser()) {
                return route('food.dat-don');
            }

            return route('dashboard');
        });
    }
}
