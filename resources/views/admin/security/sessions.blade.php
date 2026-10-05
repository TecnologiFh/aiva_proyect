@extends('admin.layout')

@section('title', 'Monitor de Sesiones')

@section('content')
<h1 class="header-title">🛡️ Monitor & Revocación Server-Side de Sesiones</h1>

@if (session('status'))
    <div style="padding: 12px 16px; background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.4); color: #86efac; border-radius: 8px; margin-bottom: 24px;">
        {{ session('status') }}
    </div>
@endif

<div style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-muted);">
                <th style="padding: 12px;">Usuario</th>
                <th style="padding: 12px;">Email</th>
                <th style="padding: 12px;">Tenant</th>
                <th style="padding: 12px;">Estado</th>
                <th style="padding: 12px;">Última Actividad</th>
                <th style="padding: 12px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activeSessions as $s)
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 14px 12px; font-weight: 600; color: white;">{{ $s->name }}</td>
                <td style="padding: 14px 12px; color: #818cf8;">{{ $s->email }}</td>
                <td style="padding: 14px 12px; color: var(--text-muted);">Tenant #{{ $s->tenant_id }}</td>
                <td style="padding: 14px 12px;">
                    <span class="badge {{ $s->status === 'ACTIVE' ? 'badge-success' : 'badge-danger' }}">{{ $s->status }}</span>
                </td>
                <td style="padding: 14px 12px; color: var(--text-muted);">{{ $s->updated_at }}</td>
                <td style="padding: 14px 12px;">
                    <form action="{{ url('/admin/security/sessions/' . $s->id . '/revoke') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">Revocar Sesión Server-Side</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
