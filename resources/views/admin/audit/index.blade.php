@extends('admin.layout')

@section('title', 'Visor de Auditoría')

@section('content')
<h1 class="header-title">📋 Registro Inmutable de Auditoría (Read-Only)</h1>

<div style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-muted);">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">Fecha/Hora</th>
                <th style="padding: 10px;">Acción</th>
                <th style="padding: 10px;">Módulo</th>
                <th style="padding: 10px;">User ID</th>
                <th style="padding: 10px;">Tenant ID</th>
                <th style="padding: 10px;">IP Address</th>
                <th style="padding: 10px;">Correlation ID</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($auditLogs as $log)
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 10px; font-weight: 700;">#{{ $log->id }}</td>
                <td style="padding: 10px; color: var(--text-muted);">{{ $log->created_at }}</td>
                <td style="padding: 10px;"><span class="badge badge-success">{{ $log->action }}</span></td>
                <td style="padding: 10px; color: #818cf8;">{{ $log->module }}</td>
                <td style="padding: 10px;">{{ $log->user_id ?? 'N/A' }}</td>
                <td style="padding: 10px;">Tenant #{{ $log->tenant_id ?? 'N/A' }}</td>
                <td style="padding: 10px; color: var(--text-muted);">{{ $log->ip_address }}</td>
                <td style="padding: 10px; font-family: monospace; color: var(--text-muted);">{{ substr($log->correlation_id, 0, 18) }}...</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
