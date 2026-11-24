@extends('layouts.workspace')
@section('title', 'MCP Management')
@section('heading', 'MCP Management')
@section('description', 'Prepare secure, role-scoped AI management for LearnCambodia. Remote access is disabled until OAuth and tool-level authorization are implemented and verified.')

@push('workspace_styles')
<link rel="stylesheet" href="{{ asset('css/mcp-management.css') }}?v=foundation-1">
<script defer src="{{ asset('js/mcp-management.js') }}?v=foundation-1"></script>
@endpush

@section('content')
@php
    $gatewayReady = $flagEnabled && $oauthReady && $transportReady;
    $readCount = collect($tools)->where('mode', 'Read')->count();
    $writeCount = count($tools) - $readCount;
@endphp
<div class="mcp-manager" data-mcp-manager>
    <section class="mcp-hero" aria-label="MCP management overview">
        <div>
            <div class="mcp-eyebrow">AI OPERATIONS · FOUNDATION</div>
            <h2>LearnCambodia MCP Control Center</h2>
            <p>Review proposed tools, their permissions and security requirements before activating a remote connection. This page does not issue credentials or enable remote writes.</p>
        </div>
        <div class="mcp-hero-status" role="status">
            <span class="mcp-status-dot" aria-hidden="true"></span>
            {{ $gatewayReady ? 'Ready for secure review' : 'Remote connection disabled' }}
        </div>
    </section>

    <section class="mcp-status-grid" aria-label="Connection readiness">
        <div class="mcp-status-card">
            <span class="mcp-stat-label">Gateway configuration</span>
            <strong>{{ $flagEnabled ? 'Flag enabled' : 'Disabled' }}</strong>
            <p>Feature flag only; it cannot expose an MCP endpoint by itself.</p>
        </div>
        <div class="mcp-status-card">
            <span class="mcp-stat-label">OAuth 2.1 authorization</span>
            <strong>{{ $oauthReady ? 'Configured' : 'Not configured' }}</strong>
            <p>Consent, token rotation and revocation are required before connection.</p>
        </div>
        <div class="mcp-status-card">
            <span class="mcp-stat-label">Remote write operations</span>
            <strong>{{ ($gatewayReady && $writeFlagEnabled) ? 'Flag enabled' : 'Locked' }}</strong>
            <p>Publishing and configuration changes must require explicit approval.</p>
        </div>
        <div class="mcp-status-card">
            <span class="mcp-stat-label">Proposed tools</span>
            <strong>{{ count($tools) }}</strong>
            <p>{{ $readCount }} read-only · {{ $writeCount }} write/publish proposals · none registered.</p>
        </div>
    </section>

    <div class="mcp-main-grid">
        <section class="mcp-panel" aria-labelledby="mcp-integration-heading">
            <div class="mcp-panel-header">
                <div>
                    <span class="mcp-panel-kicker">CONNECTION</span>
                    <h3 id="mcp-integration-heading">Integration readiness</h3>
                </div>
                <span class="mcp-neutral-chip">Local setup</span>
            </div>
            <ol class="mcp-steps">
                <li><span class="mcp-step-count">1</span><div><strong>Admin-only management panel</strong><p>Inventory, scope and status review are available within the secured dashboard.</p></div><span class="mcp-complete">Complete</span></li>
                <li><span class="mcp-step-count">2</span><div><strong>OAuth 2.1 and authorization</strong><p>Register approved clients with consent, refresh-token rotation, revocation and strict redirect URI validation.</p></div><span class="mcp-pending">Planned</span></li>
                <li><span class="mcp-step-count">3</span><div><strong>Read-only MCP transport</strong><p>Implement the authenticated Streamable HTTP endpoint and first four read tools.</p></div><span class="mcp-pending">Planned</span></li>
                <li><span class="mcp-step-count">4</span><div><strong>Audited, approval-gated writes</strong><p>Introduce draft-first changes, per-tool scopes and an explicit approval workflow before publishing.</p></div><span class="mcp-pending">Planned</span></li>
            </ol>
        </section>

        <section class="mcp-panel" aria-labelledby="mcp-capability-heading">
            <div class="mcp-panel-header">
                <div><span class="mcp-panel-kicker">COVERAGE</span><h3 id="mcp-capability-heading">Website management domains</h3></div>
            </div>
            <div class="mcp-capability-list">
                @foreach($capabilities as $name => $description)
                    <div><strong>{{ $name }}</strong><p>{{ $description }}</p></div>
                @endforeach
            </div>
            <a class="mcp-regular-link" href="{{ route('admin.appearance.campaign.edit', ['lang' => 'en']) }}">Open Campaign Builder <span aria-hidden="true">↗</span></a>
        </section>
    </div>

    <section class="mcp-panel" aria-labelledby="mcp-tools-heading">
        <div class="mcp-panel-header">
            <div><span class="mcp-panel-kicker">PERMISSIONS</span><h3 id="mcp-tools-heading">Tool catalog</h3><p>Capabilities are proposed, not connected. Each tool will require an individual permission grant.</p></div>
            <span class="mcp-neutral-chip">{{ count($tools) }} planned</span>
        </div>
        <div class="mcp-table-scroll">
            <table class="mcp-table">
                <thead><tr><th scope="col">Tool</th><th scope="col">Domain</th><th scope="col">Mode</th><th scope="col">Scope</th><th scope="col">Risk</th><th scope="col">Status</th><th scope="col"><span class="mcp-visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @foreach($tools as $tool)
                        <tr>
                            <td><strong>{{ $tool['label'] }}</strong><small>{{ $tool['name'] }}</small></td>
                            <td>{{ $tool['domain'] }}</td>
                            <td><span class="mcp-mode">{{ $tool['mode'] }}</span></td>
                            <td><code>{{ $tool['scope'] }}</code></td>
                            <td><span class="mcp-risk" data-risk="{{ strtolower($tool['risk']) }}">{{ $tool['risk'] }}</span></td>
                            <td><span class="mcp-muted-state">Not registered</span></td>
                            <td><button type="button" class="mcp-icon-action" data-mcp-tool-details
                                data-name="{{ $tool['name'] }}" data-title="{{ $tool['label'] }}"
                                data-scope="{{ $tool['scope'] }}" data-risk="{{ $tool['risk'] }}"
                                data-summary="{{ $tool['summary'] }}" aria-label="Details for {{ $tool['label'] }}" title="Tool details">
                                <span aria-hidden="true">⋮</span></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <p class="mcp-disclaimer"><strong>Security:</strong> No API keys, OAuth secrets or student records are displayed on this page. The developer catalog-read token cannot be used to administer the website. No public MCP endpoint is registered in this foundation.</p>

    <dialog class="mcp-detail-dialog" id="mcp-tool-dialog" aria-labelledby="mcp-dialog-title">
        <div class="mcp-dialog-header">
            <div><span class="mcp-panel-kicker">TOOL DETAILS</span><h3 id="mcp-dialog-title">Tool details</h3></div>
            <button type="button" class="mcp-close" data-mcp-dialog-close aria-label="Close dialog">✕</button>
        </div>
        <dl class="mcp-detail-list">
            <div><dt>Identifier</dt><dd id="mcp-detail-id"></dd></div>
            <div><dt>Required scope</dt><dd id="mcp-detail-scope"></dd></div>
            <div><dt>Risk</dt><dd id="mcp-detail-risk"></dd></div>
            <div><dt>Availability</dt><dd>Planned — no remote tool is active</dd></div>
        </dl>
        <p id="mcp-detail-summary"></p>
        <div class="mcp-dialog-footer"><button type="button" class="mcp-primary-close" data-mcp-dialog-close>Close</button></div>
    </dialog>
</div>
@endsection
