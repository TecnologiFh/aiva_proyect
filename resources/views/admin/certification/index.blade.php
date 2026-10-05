@extends('admin.layout')

@section('title', 'Certificación SaaS')

@section('content')
<h1 class="header-title">🧪 Consola de Certificación de Producción AIVA v21.1</h1>

<div style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px;">
    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
        Esta consola ejecuta la auditoría completa de los 5 pilares de certificación: PR0–PR14, Business Health, Cross-Tenant Isolation, Queue Reliability e Identity Security Gates (A1–A23).
    </p>

    <a href="{{ url('/api/v1/commerce-certification/run') }}" target="_blank" style="display:inline-block; padding: 12px 20px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">▶️ Ejecutar Suite Completa de Certificación (JSON API)</a>
</div>
@endsection
