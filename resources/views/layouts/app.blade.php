<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AIVA — Business Operating System')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        },
                        darkbg: '#0B0F17',
                        darkcard: '#111827',
                        darkborder: 'rgba(255, 255, 255, 0.08)',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; transition: background-color 0.3s, color 0.3s; }
        #main-sidebar.is-collapsed {
            width: 5rem !important;
        }
        #main-sidebar.is-collapsed .sidebar-text {
            display: none !important;
        }
        #main-sidebar.is-collapsed .sidebar-padding {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
            justify-content: center !important;
        }
        #main-sidebar.is-collapsed .sidebar-hide-on-collapse {
            display: none !important;
        }
        select, select:focus {
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            outline: none !important;
            box-shadow: none !important;
            border: none !important;
        }
    </style>
    @yield('styles')
</head>
<body class="bg-slate-50 dark:bg-[#0B0F17] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col md:flex-row antialiased selection:bg-blue-600 selection:text-white">

    <!-- BACKDROP OVERLAY PARA MÓVIL -->
    <div id="mobile-backdrop" onclick="toggleMobileMenu()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

    <!-- SIDEBAR LATERAL NAVEGACIÓN (RESPONSIVO EN MÓVIL COMO DRAWER DESLIZANTE) -->
    <aside id="main-sidebar" class="sidebar-width fixed md:static inset-y-0 left-0 z-50 w-72 md:w-64 bg-white dark:bg-[#111827] border-r border-slate-200 dark:border-slate-800/80 flex flex-col justify-between transition-all duration-300 transform -translate-x-full md:translate-x-0 shrink-0 shadow-2xl md:shadow-none">
        <div>
            <!-- LOGO & TOGGLE BUTTON -->
            <div class="h-20 px-6 flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80">
                <a href="/dashboard" class="flex items-center space-x-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-extrabold text-xl shadow-md shadow-blue-500/20 shrink-0">
                        A
                    </div>
                    <div class="sidebar-text">
                        <span class="font-extrabold text-lg text-slate-900 dark:text-white tracking-tight">AIVA</span>
                        <span class="text-[10px] text-blue-600 dark:text-blue-400 font-bold block uppercase tracking-wider">Enterprise BOS</span>
                    </div>
                </a>
                <!-- BOTÓN COLAPSAR EN DESKTOP -->
                <button onclick="toggleSidebar()" class="hidden md:flex text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Colapsar Menú">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                </button>
                <!-- BOTÓN CERRAR EN MÓVIL -->
                <button onclick="toggleMobileMenu()" class="flex md:hidden text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Cerrar Menú">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- NAVEGACIÓN DE MENÚ PRINCIPAL DINÁMICA POR ROL Y FEATURE ENTITLEMENTS -->
            <nav class="p-4 space-y-1.5 overflow-y-auto max-h-[calc(100vh-160px)]">
                @php
                    $nav = \App\Services\Navigation\NavigationResolver::getTenantNavigation();
                @endphp

                <!-- SECCIÓN OPERACIÓN -->
                @if(!empty($nav['main']))
                    <div class="pt-1 pb-1">
                        <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-4 sidebar-text">Operación</span>
                    </div>
                    @foreach($nav['main'] as $item)
                        <a href="{{ $item['url'] }}" onclick="closeMobileMenuOnNav()" class="flex items-center space-x-3.5 px-4 py-2.5 rounded-xl font-medium text-xs transition {{ $item['active'] ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <span class="sidebar-text">{{ $item['title'] }}</span>
                        </a>
                    @endforeach
                @endif

                <!-- SECCIÓN MI NEGOCIO -->
                @if(!empty($nav['business']))
                    <div class="pt-3 pb-1">
                        <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-4 sidebar-text">Mi Negocio</span>
                    </div>
                    @foreach($nav['business'] as $item)
                        <a href="{{ $item['url'] }}" onclick="closeMobileMenuOnNav()" class="flex items-center space-x-3.5 px-4 py-2.5 rounded-xl font-medium text-xs transition {{ $item['active'] ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <span class="sidebar-text">{{ $item['title'] }}</span>
                        </a>
                    @endforeach
                @endif

                <!-- SECCIÓN CONFIGURACIÓN -->
                @if(!empty($nav['config']))
                    <div class="pt-3 pb-1">
                        <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-4 sidebar-text">Configuración</span>
                    </div>
                    @foreach($nav['config'] as $item)
                        <a href="{{ $item['url'] }}" onclick="closeMobileMenuOnNav()" class="flex items-center space-x-3.5 px-4 py-2.5 rounded-xl font-medium text-xs transition {{ $item['active'] ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <span class="sidebar-text">{{ $item['title'] }}</span>
                        </a>
                    @endforeach
                @endif
            </nav>
        </div>

        <!-- FOOTER USER / LOGOUT -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800/80">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center font-bold text-white text-xs shrink-0 uppercase">
                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                    </div>
                    <div class="sidebar-text overflow-hidden">
                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ Auth::user()->name ?? 'Usuario AIVA' }}</p>
                        <p class="text-[10px] text-slate-500 truncate">{{ Auth::user()->email ?? 'usuario@empresa.com' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ url('/logout') }}" class="sidebar-text shrink-0">
                    @csrf
                    <button type="submit" class="p-2 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition flex items-center space-x-1" title="Cerrar Sesión">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- TOP HEADER BAR -->
        <header class="h-20 px-4 md:px-8 bg-white dark:bg-[#111827] border-b border-slate-200 dark:border-slate-800/80 flex items-center justify-between gap-3 sticky top-0 z-20">
            
            <div class="flex items-center space-x-3 flex-1 max-w-md">
                <!-- BOTÓN HAMBURGUESA MÓVIL (SÓLO VISIBLE EN PANTALLAS PEQUEÑAS) -->
                <button onclick="toggleMobileMenu()" class="flex md:hidden p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:text-blue-600 hover:bg-slate-200 dark:hover:bg-slate-700 transition shrink-0" title="Abrir Menú de Navegación">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <!-- BUSCADOR PRINCIPAL -->
                <div class="relative w-full">
                    <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" placeholder="Buscar pedidos, clientes, productos..." class="w-full pl-10 pr-3 py-2 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- CONTROLES DERECHA (THEME TOGGLE + NOTIFICACIONES + TENANT SELECTOR) -->
            <div class="flex items-center space-x-2.5 md:space-x-4">
                <!-- THEME TOGGLE (LIGHT / DARK) -->
                <button onclick="toggleTheme()" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-blue-600 transition shrink-0" title="Cambiar Tema (Light / Dark)">
                    <svg id="theme-icon-sun" class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <svg id="theme-icon-moon" class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </button>

                <!-- CAMPANITA DE NOTIFICACIONES EN TIEMPO REAL -->
                <div class="relative shrink-0">
                    <button onclick="toggleNotificationMenu()" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-blue-600 transition relative" title="Notificaciones del Sistema">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span id="notif-badge" class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white font-extrabold text-[9px] rounded-full flex items-center justify-center animate-pulse hidden">0</span>
                    </button>

                    <!-- POPOVER DROPDOWN DE NOTIFICACIONES -->
                    <div id="notif-dropdown" class="absolute right-0 mt-3 w-80 sm:w-96 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl z-50 hidden overflow-hidden">
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <h4 class="text-xs font-extrabold text-slate-900 dark:text-white flex items-center space-x-2">
                                <span>🔔 Notificaciones del Sistema</span>
                            </h4>
                            <button onclick="markAllNotifsRead()" class="text-[10px] font-bold text-blue-600 dark:text-blue-400 hover:underline">
                                Marcar leídas
                            </button>
                        </div>

                        <div id="notif-list-container" class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <p class="text-slate-400 text-center py-6 text-xs">Cargando notificaciones...</p>
                        </div>

                        <div class="p-2.5 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 text-center">
                            <a href="/operations" class="text-[11px] font-extrabold text-blue-600 dark:text-blue-400 hover:underline">Ver Centro de Operaciones</a>
                        </div>
                    </div>
                </div>

                <!-- TENANT BADGE / SELECTOR RBAC -->
                @php
                    $userTenantService = new \App\Modules\Tenant\Services\UserTenantAccessService();
                    $activeTenantId = $userTenantService->getActiveTenantId();
                    $allowedTenants = $userTenantService->getAllowedTenants();
                    $isSuperAdminUser = $userTenantService->isSuperAdmin();
                @endphp

                @if($isSuperAdminUser && $allowedTenants->count() > 1)
                    <!-- SELECTOR DESPLEGABLE INTERACTIVO SÓLO PARA SUPERADMIN DE PLATAFORMA -->
                    <div class="relative flex items-center bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 md:px-3 py-2 space-x-2 transition hover:border-slate-300 dark:hover:border-slate-700 shadow-sm max-w-[170px] md:max-w-none">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                        <select id="global-tenant-select" onchange="changeTenant(this.value)" class="bg-transparent text-xs font-semibold text-slate-800 dark:text-slate-200 outline-none border-none focus:outline-none focus:ring-0 focus:border-none cursor-pointer pr-5 appearance-none shadow-none truncate">
                            @foreach($allowedTenants as $t)
                                @php
                                    $prof = \Illuminate\Support\Facades\DB::table('business_profiles')->where('tenant_id', $t->id)->first();
                                    $tName = $prof->commercial_name ?? $t->name ?? "Tenant {$t->id}";
                                @endphp
                                <option value="{{ $t->id }}" {{ (int)$t->id === (int)$activeTenantId ? 'selected' : '' }} class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                                    {{ $tName }} (Tenant {{ $t->id }})
                                </option>
                            @endforeach
                        </select>
                        <svg class="w-3.5 h-3.5 text-slate-400 pointer-events-none absolute right-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                @else
                    <!-- BADGE FIJO ESTÁTICO SIN FLECHA DESPLEGABLE PARA EL DUEÑO DE NEGOCIO -->
                    @php
                        $activeProf = \Illuminate\Support\Facades\DB::table('business_profiles')->where('tenant_id', $activeTenantId)->first();
                        $activeName = $activeProf->commercial_name ?? \Illuminate\Support\Facades\DB::table('tenants')->where('id', $activeTenantId)->value('name') ?? "Tenant {$activeTenantId}";
                    @endphp
                    <div class="flex items-center bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 space-x-2 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">
                            {{ $activeName }} (Tenant {{ $activeTenantId }})
                        </span>
                    </div>
                @endif
            </div>
        </header>

        <!-- PAGE BODY CONTAINER -->
        <main class="flex-1 p-4 md:p-8 overflow-y-auto">
            @yield('content')
        </main>
    </div>

    <!-- JAVASCRIPT GLOBAL LAYOUT CONTROLS -->
    <script>
        window.activeTenantId = {{ $activeTenantId ?? 4 }};

        // PERSISTENCIA DE TEMA (LIGHT / DARK)
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }

        function toggleNotificationMenu() {
            const menu = document.getElementById('notif-dropdown');
            menu.classList.toggle('hidden');
            if (!menu.classList.contains('hidden')) {
                fetchNotifications();
            }
        }

        async function fetchNotifications() {
            try {
                const res = await fetch('/api/v1/dashboard/notifications');
                const data = await res.json();
                const container = document.getElementById('notif-list-container');
                const badge = document.getElementById('notif-badge');

                if (data.unread_count > 0) {
                    badge.innerText = data.unread_count;
                    badge.classList.remove('hidden');
                } else {
                    badge.innerText = '0';
                    badge.classList.add('hidden');
                }

                // Filtrar solo las notificaciones no leídas para mostrarlas en la lista
                const unreadItems = (data.notifications || []).filter(n => n.unread);

                if (unreadItems.length === 0) {
                    container.innerHTML = `<p class="text-slate-400 text-center py-6 text-xs">Sin notificaciones pendientes.</p>`;
                    return;
                }

                container.innerHTML = unreadItems.map(n => `
                    <a href="${n.link}" class="p-3 flex items-start space-x-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition block">
                        <div class="p-2 rounded-xl ${n.type === 'HANDOVER' ? 'bg-amber-500/10 text-amber-500' : 'bg-blue-500/10 text-blue-500'} shrink-0 mt-0.5">
                            ${n.type === 'HANDOVER' ? '🙋‍♂️' : '📦'}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-extrabold text-slate-800 dark:text-slate-200 text-xs truncate">${n.title}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug mt-0.5">${n.description}</p>
                            <span class="text-[9px] font-semibold text-slate-400 mt-1 block">${n.time}</span>
                        </div>
                    </a>
                `).join('');
            } catch (e) {
                console.error('Error fetching notifications:', e);
            }
        }

        async function markAllNotifsRead() {
            // 1. Cerrar el popover modal de inmediato
            const menu = document.getElementById('notif-dropdown');
            if (menu) menu.classList.add('hidden');

            // 2. Ocultar la insignia/badge rojo de la campanita
            const badge = document.getElementById('notif-badge');
            if (badge) {
                badge.innerText = '0';
                badge.classList.add('hidden');
            }

            // 3. Limpiar la lista del contenedor de notificaciones
            const container = document.getElementById('notif-list-container');
            if (container) {
                container.innerHTML = `<p class="text-slate-400 text-center py-6 text-xs">Sin notificaciones pendientes.</p>`;
            }

            // 4. Guardar en el backend que todas las notificaciones fueron leídas
            try {
                await fetch('/api/v1/dashboard/notifications/mark-read', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
            } catch (e) {
                console.error('Error marking notifications as read:', e);
            }
        }

        // Cerrar menú de notificaciones al hacer clic fuera del elemento
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notif-dropdown');
            const button = e.target.closest('button[onclick="toggleNotificationMenu()"]');
            if (dropdown && !dropdown.classList.contains('hidden') && !dropdown.contains(e.target) && !button) {
                dropdown.classList.add('hidden');
            }
        });

        setInterval(fetchNotifications, 20000);
        document.addEventListener('DOMContentLoaded', fetchNotifications);

        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.getElementById('main-sidebar')?.classList.add('is-collapsed');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('main-sidebar');
            sidebar.classList.toggle('is-collapsed');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('is-collapsed') ? 'true' : 'false');
        }

        // CONTROL DEL MENÚ HAMBURGUESA MÓVIL
        function toggleMobileMenu() {
            const sidebar = document.getElementById('main-sidebar');
            const backdrop = document.getElementById('mobile-backdrop');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        function closeMobileMenuOnNav() {
            if (window.innerWidth < 768) {
                const sidebar = document.getElementById('main-sidebar');
                const backdrop = document.getElementById('mobile-backdrop');
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        async function changeTenant(tenantId) {
            try {
                await fetch('/api/v1/dashboard/select-tenant', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tenant_id: parseInt(tenantId) })
                });
                window.location.reload();
            } catch (e) {
                console.error('Error al cambiar de tenant:', e);
            }
        }
    </script>
    @yield('scripts')
</body>
</html>
