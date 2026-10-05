<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleDiscoveryServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios de los módulos habilitados.
     */
    public function register(): void
    {
        $modulesPath = app_path('Modules');
        if (!is_dir($modulesPath)) {
            return;
        }

        $modules = array_filter(glob($modulesPath . '/*'), 'is_dir');

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            $isEnabled = config("modules.modules.{$moduleName}.enabled", true);

            if (!$isEnabled) {
                continue;
            }

            // Carga de Service Providers específicos por módulo si existen
            $providerClass = "App\\Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    /**
     * Carga rutas y migraciones de los módulos habilitados.
     */
    public function boot(): void
    {
        $modulesPath = app_path('Modules');
        if (!is_dir($modulesPath)) {
            return;
        }

        $modules = array_filter(glob($modulesPath . '/*'), 'is_dir');

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            $isEnabled = config("modules.modules.{$moduleName}.enabled", true);

            if (!$isEnabled) {
                continue;
            }

            // Carga de Migraciones del módulo
            $migrationsPath = $modulePath . '/Database/Migrations';
            if (is_dir($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }

            // Carga de Rutas API del módulo con prefijo /api/v1/
            $apiRoutesPath = $modulePath . '/Routes/api.php';
            if (file_exists($apiRoutesPath)) {
                Route::middleware(['api'])
                    ->prefix('api/v1')
                    ->group($apiRoutesPath);
            }
        }
    }
}
