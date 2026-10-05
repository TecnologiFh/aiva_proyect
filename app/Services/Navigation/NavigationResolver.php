<?php

namespace App\Services\Navigation;

use App\Services\Authorization\AccessResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NavigationResolver
{
    /**
     * Generar los ítems del Sidebar para el Tenant Plane de forma dinámica
     */
    public static function getTenantNavigation(?string $simulatedRole = null): array
    {
        $tenantId = session('active_tenant_id') ?? Auth::user()->tenant_id ?? 4;

        // Si se trata de Role Preview Sandbox (RG28), simular el menú del rol especificado
        if ($simulatedRole !== null) {
            return self::buildSimulatedNavigation($simulatedRole, $tenantId);
        }

        $items = [
            'main' => [],
            'business' => [],
            'config' => [],
        ];

        // 1. SECCIÓN PRINCIPAL / OPERACIÓN
        if (AccessResolver::can('tenant.conversations.read', 'dashboard_inbox')) {
            $items['main'][] = [
                'title' => 'Dashboard & Inbox',
                'url' => '/dashboard',
                'icon' => 'dashboard',
                'active' => request()->is('dashboard'),
            ];
        }

        if (AccessResolver::can('tenant.orders.view', 'operations_center')) {
            $items['main'][] = [
                'title' => 'Operations Center',
                'url' => '/operations',
                'icon' => 'operations',
                'active' => request()->is('operations'),
            ];
        }

        if (AccessResolver::can('tenant.customers.view', 'customer_360')) {
            $items['main'][] = [
                'title' => 'Clientes & Customer 360',
                'url' => '/customers',
                'icon' => 'customers',
                'active' => request()->is('customers'),
            ];
        }

        // 2. SECCIÓN MI NEGOCIO
        if (AccessResolver::can('tenant.catalog.view', 'catalog_inventory')) {
            $items['business'][] = [
                'title' => 'Catálogo & Inventario',
                'url' => '/catalog',
                'icon' => 'catalog',
                'active' => request()->is('catalog'),
            ];
        }

        $items['business'][] = [
            'title' => '🧠 Knowledge Hub & IA',
            'url' => '/knowledge',
            'icon' => 'knowledge',
            'active' => request()->is('knowledge') || request()->is('settings/knowledge'),
        ];

        if (AccessResolver::can('tenant.business_health.view', 'business_health')) {
            $items['business'][] = [
                'title' => 'Salud Comercial',
                'url' => '/business-health',
                'icon' => 'health',
                'active' => request()->is('business-health'),
            ];
        }

        // 3. SECCIÓN CONFIGURACIÓN
        // Onboarding solo si el tenant no ha completado el onboarding
        $profile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
        $onboardingPending = !$profile || empty($profile->commercial_name) || empty($profile->phone);

        if ($onboardingPending && AccessResolver::can('tenant.settings.view', 'business_settings')) {
            $items['config'][] = [
                'title' => '🚀 Onboarding Wizard',
                'url' => '/onboarding',
                'icon' => 'wizard',
                'active' => request()->is('onboarding'),
            ];
        }

        if (AccessResolver::can('tenant.settings.view', 'business_settings')) {
            $items['config'][] = [
                'title' => '⚙️ Configuración del Negocio',
                'url' => '/settings',
                'icon' => 'settings',
                'active' => request()->is('settings'),
            ];
        }

        if (AccessResolver::can('tenant.users.view', 'business_settings')) {
            $items['config'][] = [
                'title' => '👥 Usuarios & Equipo',
                'url' => '/settings/team',
                'icon' => 'team',
                'active' => request()->is('settings/team'),
            ];
        }

        if (AccessResolver::can('tenant.users.manage', 'business_settings')) {
            $items['config'][] = [
                'title' => '🔑 Roles & Permisos',
                'url' => '/settings/roles',
                'icon' => 'roles',
                'active' => request()->is('settings/roles'),
            ];
        }

        if (AccessResolver::can('tenant.payments.view', 'business_settings')) {
            $items['config'][] = [
                'title' => '💳 Métodos de Pago',
                'url' => '/settings/payments',
                'icon' => 'payments',
                'active' => request()->is('settings/payments'),
            ];
        }

        if (AccessResolver::can('tenant.knowledge.view', 'knowledge_hub')) {
            $items['config'][] = [
                'title' => '🧠 Knowledge Hub & IA',
                'url' => '/knowledge',
                'icon' => 'knowledge',
                'active' => request()->is('knowledge'),
            ];
        }

        if (AccessResolver::can('tenant.ai.playground.view', 'ai_playground')) {
            $items['config'][] = [
                'title' => '🤖 AI Playground',
                'url' => '/ai-playground',
                'icon' => 'ai',
                'active' => request()->is('ai-playground'),
            ];
        }

        // Salvaguarda: Si $items['main'] resulta vacío para un usuario autenticado, popular Dashboard & Inbox
        if (empty($items['main']) && Auth::check()) {
            $items['main'][] = [
                'title' => 'Dashboard & Inbox',
                'url' => '/dashboard',
                'icon' => 'dashboard',
                'active' => request()->is('dashboard'),
            ];
            $items['main'][] = [
                'title' => 'Operations Center',
                'url' => '/operations',
                'icon' => 'operations',
                'active' => request()->is('operations'),
            ];
            $items['main'][] = [
                'title' => 'Clientes & Customer 360',
                'url' => '/customers',
                'icon' => 'customers',
                'active' => request()->is('customers'),
            ];
            $items['business'][] = [
                'title' => 'Catálogo & Inventario',
                'url' => '/catalog',
                'icon' => 'catalog',
                'active' => request()->is('catalog'),
            ];
            $items['business'][] = [
                'title' => 'Salud Comercial',
                'url' => '/business-health',
                'icon' => 'health',
                'active' => request()->is('business-health'),
            ];
            $items['config'][] = [
                'title' => '⚙️ Configuración del Negocio',
                'url' => '/settings',
                'icon' => 'settings',
                'active' => request()->is('settings'),
            ];
        }

        return $items;
    }

    /**
     * Simulación aislada de menú para Role Preview Sandbox (RG28)
     */
    private static function buildSimulatedNavigation(string $role, int $tenantId): array
    {
        $role = strtoupper($role);

        $items = [
            'main' => [
                ['title' => 'Dashboard & Inbox', 'url' => '/dashboard', 'active' => false],
            ],
            'business' => [],
            'config' => [],
        ];

        if (in_array($role, ['OWNER', 'ADMIN', 'MANAGER', 'OPERATOR'])) {
            $items['main'][] = ['title' => 'Operations Center', 'url' => '/operations', 'active' => false];
            $items['main'][] = ['title' => 'Clientes & Customer 360', 'url' => '/customers', 'active' => false];
            $items['business'][] = ['title' => 'Catálogo & Inventario', 'url' => '/catalog', 'active' => false];
            $items['business'][] = ['title' => '🧠 Knowledge Hub & IA', 'url' => '/knowledge', 'active' => false];
            $items['business'][] = ['title' => 'Salud Comercial', 'url' => '/business-health', 'active' => false];
        } elseif ($role === 'AGENT') {
            $items['main'][] = ['title' => 'Mis Pedidos Asignados', 'url' => '/operations', 'active' => false];
            $items['main'][] = ['title' => 'Clientes 360', 'url' => '/customers', 'active' => false];
        }

        if ($role === 'OWNER') {
            $items['config'][] = ['title' => '⚙️ Configuración del Negocio', 'url' => '/settings', 'active' => false];
            $items['config'][] = ['title' => '👥 Usuarios & Equipo', 'url' => '/settings/team', 'active' => false];
            $items['config'][] = ['title' => '🔑 Roles & Permisos', 'url' => '/settings/roles', 'active' => false];
            $items['config'][] = ['title' => '💳 Métodos de Pago', 'url' => '/settings/payments', 'active' => false];
            $items['config'][] = ['title' => '🧠 Knowledge Hub & IA', 'url' => '/knowledge', 'active' => false];
        } elseif ($role === 'ADMIN') {
            $items['config'][] = ['title' => '⚙️ Configuración del Negocio', 'url' => '/settings', 'active' => false];
            $items['config'][] = ['title' => '🧠 Knowledge Hub & IA', 'url' => '/knowledge', 'active' => false];
        }

        return $items;
    }
}
