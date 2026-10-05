<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Control Plane') — AIVA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
        }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }

        /* Dark / Light Theme CSS Custom Variables */
        html.dark {
            --bg-body: #0b0f19;
            --sidebar-bg: #0f172a;
            --panel-bg: #111827;
            --card-bg: #1f2937;
            --border-color: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --user-panel-bg: #090d16;
        }
        html:not(.dark) {
            --bg-body: #f8fafc;
            --sidebar-bg: #ffffff;
            --panel-bg: #ffffff;
            --card-bg: #f1f5f9;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --user-panel-bg: #f1f5f9;
        }
        
        body { background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; transition: background-color 0.2s ease, color 0.2s ease; }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; bottom: 0; left: 0;
            z-index: 50;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .sidebar-brand {
            padding: 24px 20px;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(to right, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .sidebar-brand span { font-size: 10px; text-transform: uppercase; color: var(--text-muted); display: block; margin-top: 4px; font-weight: 700; -webkit-text-fill-color: var(--text-muted); letter-spacing: 1px; }
        .sidebar-menu { list-style: none; padding: 16px 12px; flex: 1; overflow-y: auto; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.15s ease;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(99, 102, 241, 0.15);
            color: #6366f1;
            font-weight: 600;
        }
        
        .user-panel {
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--user-panel-bg);
        }
        .user-info { font-size: 12px; }
        .user-name { font-weight: 600; color: var(--text-main); }
        .user-role { color: #6366f1; font-size: 10px; font-weight: 700; }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .top-header {
            height: 70px;
            padding: 0 32px;
            background: var(--panel-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .main-content {
            flex: 1;
            padding: 32px;
            background: var(--bg-body);
        }
        .header-title { font-size: 24px; font-weight: 700; margin-bottom: 24px; color: var(--text-main); }

        /* Badges & Tables */
        .badge { display: inline-block; padding: 4px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .badge-success { background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .dark .badge-success { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .dark .badge-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); }
        .dark .badge-warning { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .metric-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .metric-label { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
        .metric-value { font-size: 28px; font-weight: 800; margin-top: 8px; color: var(--text-main); }
        .metric-sub { font-size: 12px; color: #6366f1; margin-top: 4px; font-weight: 600; }
    </style>
    <script>
        // SCRIPT DE INICIALIZACIÓN DE TEMA AL CARGAR LA PÁGINA (LIGHT / DARK)
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body>
    <!-- SIDEBAR DE CONTROL PLANE -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            AIVA COMMERCE
            <span>Control Plane Admin</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="{{ url('/admin') }}" class="{{ request()->is('admin') ? 'active' : '' }}">📊 Dashboard Global</a></li>
            <li><a href="{{ url('/admin/tenants') }}" class="{{ request()->is('admin/tenants*') ? 'active' : '' }}">🏢 Gestión de Tenants</a></li>
            <li><a href="{{ url('/admin/users') }}" class="{{ request()->is('admin/users*') ? 'active' : '' }}">👥 Usuarios Globales</a></li>
            <li><a href="{{ url('/admin/roles') }}" class="{{ request()->is('admin/roles*') ? 'active' : '' }}">🔐 Roles y Permisos</a></li>
            <li><a href="{{ url('/admin/security/sessions') }}" class="{{ request()->is('admin/security*') ? 'active' : '' }}">🛡️ Monitor de Sesiones</a></li>
            <li><a href="{{ url('/admin/audit') }}" class="{{ request()->is('admin/audit*') ? 'active' : '' }}">📋 Auditoría Inmutable</a></li>
            <li><a href="{{ url('/admin/certification') }}" class="{{ request()->is('admin/certification*') ? 'active' : '' }}">🧪 Certificación SaaS</a></li>
        </ul>
        <div class="user-panel">
            <div class="user-info">
                <div class="user-name">{{ auth('platform')->user()->name ?? 'Super Admin' }}</div>
                <div class="user-role">SUPERADMIN</div>
            </div>
            <form action="{{ url('/logout') }}" method="POST">
                @csrf
                <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:12px; font-weight:700;">Salir</button>
            </form>
        </div>
    </aside>

    <!-- WRAPPER PRINCIPAL Y TOP BAR DE CONTROL DE TEMA -->
    <div class="main-wrapper">
        <header class="top-header">
            <div class="flex items-center space-x-2">
                <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2.5 py-1 rounded-lg border border-indigo-200 dark:border-indigo-800">
                    SUPERADMIN CONSOLE
                </span>
            </div>

            <!-- BOTÓN CAMBIADOR DE TEMA (LIGHT / DARK) EN LA CABECERA -->
            <div class="flex items-center space-x-3">
                <button onclick="toggleTheme()" class="flex items-center space-x-2 px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition border border-slate-200 dark:border-slate-700 shadow-sm" title="Cambiar Tema (Modo Claro / Modo Oscuro)">
                    <!-- ICONO SOL (VISIBLE EN MODO OSCURO PARA CAMBIAR A CLARO) -->
                    <svg id="theme-icon-sun" class="w-4 h-4 text-amber-400 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <!-- ICONO LUNA (VISIBLE EN MODO CLARO PARA CAMBIAR A OSCURO) -->
                    <svg id="theme-icon-moon" class="w-4 h-4 text-indigo-600 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <span class="hidden sm:inline" id="theme-label-text">Tema</span>
                </button>
            </div>
        </header>

        <main class="main-content">
            @yield('content')
        </main>
    </div>

    <!-- JAVASCRIPT DE ALTERNANCIA DE TEMA -->
    <script>
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }
    </script>
</body>
</html>
