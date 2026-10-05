@extends('admin.layout')

@section('title', 'Gestión de Tenants')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <h1 class="header-title" style="margin-bottom:0;">🏢 Gestión Global de Tenants</h1>
    <a href="{{ url('/admin/tenants/create') }}" style="padding: 10px 18px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 13px;">+ Crear Nuevo Tenant</a>
</div>

@if (session('status'))
    <div style="padding: 12px 16px; background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.4); color: #86efac; border-radius: 8px; margin-bottom: 24px;">
        {{ session('status') }}
    </div>
@endif

<div style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-muted);">
                <th style="padding: 12px;">ID</th>
                <th style="padding: 12px;">Empresa</th>
                <th style="padding: 12px;">Slug</th>
                <th style="padding: 12px;">Rubro</th>
                <th style="padding: 12px;">Usuarios</th>
                <th style="padding: 12px;">Estado</th>
                <th style="padding: 12px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tenants as $tenant)
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 14px 12px; font-weight: 700;">#{{ $tenant->id }}</td>
                <td style="padding: 14px 12px; font-weight: 600; color: white;">{{ $tenant->name }}</td>
                <td style="padding: 14px 12px; color: #818cf8;">{{ $tenant->slug }}</td>
                <td style="padding: 14px 12px; color: var(--text-muted);">{{ $tenant->business_type }}</td>
                <td style="padding: 14px 12px;">{{ $tenant->users_count }} usuarios</td>
                <td style="padding: 14px 12px;">
                    <span class="badge {{ $tenant->status === 'ACTIVE' ? 'badge-success' : 'badge-danger' }}">
                        {{ $tenant->status }}
                    </span>
                </td>
                <td style="padding: 14px 12px;">
                    <form action="{{ url('/admin/tenants/' . $tenant->id . '/status') }}" method="POST" style="display:inline;">
                        @csrf
                        @if ($tenant->status === 'ACTIVE')
                            <input type="hidden" name="status" value="SUSPENDED">
                            <button type="submit" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">Suspender</button>
                        @else
                            <input type="hidden" name="status" value="ACTIVE">
                            <button type="submit" style="background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">Activar</button>
                        @endif
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
