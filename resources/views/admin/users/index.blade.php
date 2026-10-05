@extends('admin.layout')

@section('title', 'Usuarios Globales')

@section('content')
<h1 class="header-title">👥 Visor Global de Usuarios SaaS</h1>

@if (session('status'))
    <div style="padding: 12px 16px; background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.4); color: #86efac; border-radius: 8px; margin-bottom: 24px;">
        {{ session('status') }}
    </div>
@endif

<div style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 32px;">
    <h2 style="font-size: 15px; font-weight: 700; margin-bottom: 16px; color: #818cf8;">Platform Administrators (Control Plane)</h2>
    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-muted);">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">Nombre</th>
                <th style="padding: 10px;">Email</th>
                <th style="padding: 10px;">Rol Platform</th>
                <th style="padding: 10px;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($platformUsers as $pu)
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 12px 10px;">#{{ $pu->id }}</td>
                <td style="padding: 12px 10px; font-weight: 600;">{{ $pu->name }}</td>
                <td style="padding: 12px 10px; color: #818cf8;">{{ $pu->email }}</td>
                <td style="padding: 12px 10px;"><span class="badge badge-success">{{ $pu->role }}</span></td>
                <td style="padding: 12px 10px;"><span class="badge badge-success">{{ $pu->status }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
    <h2 style="font-size: 15px; font-weight: 700; margin-bottom: 16px; color: white;">Usuarios de Tenants (Tenant Plane)</h2>
    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-muted);">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">Nombre</th>
                <th style="padding: 10px;">Email</th>
                <th style="padding: 10px;">Tenant</th>
                <th style="padding: 10px;">Estado</th>
                <th style="padding: 10px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $u)
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 12px 10px;">#{{ $u->id }}</td>
                <td style="padding: 12px 10px; font-weight: 600;">{{ $u->name }}</td>
                <td style="padding: 12px 10px; color: #818cf8;">{{ $u->email }}</td>
                <td style="padding: 12px 10px; color: var(--text-muted);">{{ $u->tenant_name }} (#{{ $u->tenant_id }})</td>
                <td style="padding: 12px 10px;">
                    <span class="badge {{ $u->status === 'ACTIVE' ? 'badge-success' : 'badge-danger' }}">
                        {{ $u->status }}
                    </span>
                </td>
                <td style="padding: 12px 10px;">
                    <form action="{{ url('/admin/users/' . $u->id . '/status') }}" method="POST" style="display:inline;">
                        @csrf
                        @if ($u->status === 'ACTIVE')
                            <input type="hidden" name="status" value="SUSPENDED">
                            <button type="submit" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 11px;">Suspender</button>
                        @else
                            <input type="hidden" name="status" value="ACTIVE">
                            <button type="submit" style="background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 11px;">Activar</button>
                        @endif
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
