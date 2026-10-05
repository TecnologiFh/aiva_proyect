<?php

namespace App\Providers;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Contracts\Repositories\AuditRepositoryInterface;
use App\Core\Contracts\Repositories\TenantRepositoryInterface;
use App\Core\Contracts\Repositories\UserRepositoryInterface;
use App\Core\EventBus\LaravelEventBus;
use App\Modules\Identity\Repositories\AuditRepository;
use App\Modules\Identity\Repositories\UserRepository;
use App\Modules\Tenant\Repositories\TenantRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Enlazar interfaces del Core con implementaciones concretas
        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuditRepositoryInterface::class, AuditRepository::class);
        $this->app->bind(EventBusInterface::class, LaravelEventBus::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
