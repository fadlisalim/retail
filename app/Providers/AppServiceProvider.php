<?php

namespace App\Providers;

use App\Models\User;
use App\Services\PaymentManager;
use App\Services\SettingService;
use App\Support\Rbac;
use App\View\Composers\AdminMenuComposer;
use App\View\Composers\StorefrontComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SettingService caches within the instance, so it must be a singleton.
        $this->app->singleton(SettingService::class);
        $this->app->singleton(PaymentManager::class);
    }

    public function boot(): void
    {
        // Super admins bypass every check; each granular permission is a Gate.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        foreach (array_keys(Rbac::PERMISSIONS) as $slug) {
            Gate::define($slug, fn (User $user) => $user->hasPermission($slug));
        }

        // Shared storefront chrome data (nav categories, cart/wishlist counts, WA).
        // Registered on the layout (for its included partials) AND on the content
        // views, because a child's @section body captures its own scope — not the
        // layout's — so views that reference these vars directly need them locally.
        View::composer(['layouts.storefront', 'storefront.*', 'account.*'], StorefrontComposer::class);

        // "Needs attention" counts for the admin sidebar badges.
        View::composer('layouts.admin', AdminMenuComposer::class);
    }
}
