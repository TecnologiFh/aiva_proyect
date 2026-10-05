@extends('admin.layout')

@section('title', 'Crear Tenant')

@section('content')
<h1 class="header-title">🏢 Alta de Nuevo Tenant Empresarial</h1>

<div style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 32px; max-width: 600px;">
    @if ($errors->any())
        <div style="padding: 12px 16px; background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; border-radius: 8px; margin-bottom: 24px; font-size: 13px;">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form action="{{ url('/admin/tenants') }}" method="POST">
        @csrf
        <div style="margin-bottom: 20px;">
            <label style="display:block; font-size:13px; color:var(--text-muted); margin-bottom:8px;">Nombre de la Empresa</label>
            <input type="text" name="name" required placeholder="Ej: Pizzería Don Italia" style="width:100%; padding:12px; background:rgba(15,23,42,0.6); border:1px solid var(--border-color); border-radius:8px; color:white; font-size:14px;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display:block; font-size:13px; color:var(--text-muted); margin-bottom:8px;">Rubro / Tipo de Negocio</label>
            <input type="text" name="business_type" required placeholder="Ej: Gastronomía / Restaurante" style="width:100%; padding:12px; background:rgba(15,23,42,0.6); border:1px solid var(--border-color); border-radius:8px; color:white; font-size:14px;">
        </div>

        <div style="border-top: 1px solid var(--border-color); margin: 24px 0; padding-top: 20px;">
            <h3 style="font-size:14px; color:#818cf8; margin-bottom:16px;">Datos del Propietario (Owner)</h3>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:13px; color:var(--text-muted); margin-bottom:8px;">Nombre Completo del Owner</label>
                <input type="text" name="owner_name" required placeholder="Ej: Mario Rossi" style="width:100%; padding:12px; background:rgba(15,23,42,0.6); border:1px solid var(--border-color); border-radius:8px; color:white; font-size:14px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:13px; color:var(--text-muted); margin-bottom:8px;">Correo Electrónico (Login)</label>
                <input type="email" name="owner_email" required placeholder="mario@donitalia.pe" style="width:100%; padding:12px; background:rgba(15,23,42,0.6); border:1px solid var(--border-color); border-radius:8px; color:white; font-size:14px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:13px; color:var(--text-muted); margin-bottom:8px;">Contraseña Inicial</label>
                <input type="password" name="owner_password" required placeholder="••••••••" style="width:100%; padding:12px; background:rgba(15,23,42,0.6); border:1px solid var(--border-color); border-radius:8px; color:white; font-size:14px;">
            </div>
        </div>

        <button type="submit" style="width:100%; padding:12px; background:var(--accent); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Crear Tenant & Asignar Owner</button>
    </form>
</div>
@endsection
