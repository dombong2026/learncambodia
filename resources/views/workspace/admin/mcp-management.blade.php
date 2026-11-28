@extends('layouts.workspace')
@section('title', 'MCP Management')
@section('heading', 'MCP Management')
@section('description', 'Prepare secure, role-scoped AI management for LearnCambodia. Remote access is disabled until OAuth and tool-level authorization are implemented and verified.')

@push('workspace_styles')
{{-- Fingerprint each asset so browser/CDN caches cannot reuse an older consent script. --}}
<link rel="stylesheet" href="{{ asset('css/mcp-management.css') }}?v={{ substr(hash_file('sha256', public_path('css/mcp-management.css')), 0, 16) }}">
<script defer src="{{ asset('js/mcp-management.js') }}?v={{ substr(hash_file('sha256', public_path('js/mcp-management.js')), 0, 16) }}"></script>
@endpush

@section('content')
@php
    $gatewayReady = $flagEnabled && $oauthReady && $transportReady;
    $readCount = collect($tools)->where('mode', 'Read')->count();
    $writeCount = count($tools) - $readCount;
@endphp
<div class="mcp-manager" data-mcp-manager data-mcp-ui-version="password-reauth-v2">
    <section class="mcp-hero" aria-label="MCP management overview">
        <div>
            <div class="mcp-eyebrow">AI OPERATIONS · FOUNDATION</div>
            <h2>LearnCambodia MCP Control Center</h2>
            <p>Review proposed tools, their permissions and security requirements before activating a remote connection. This page does not issue credentials or enable remote writes.</p>
        </div>
        <div class="mcp-hero-status" role="status">
            <span class="mcp-status-dot" aria-hidden="true"></span>
            {{ $gatewayReady ? 'Ready for secure review' : ($flagEnabled ? 'Direct bearer bridge enabled · OAuth pending' : 'Remote connection disabled') }}
        </div>
    </section>

    <section class="mcp-status-grid" aria-label="Connection readiness">
        <div class="mcp-status-card">
            <span class="mcp-stat-label">Gateway configuration</span>
            <strong>{{ $flagEnabled ? 'Direct bridge flag enabled' : 'Disabled' }}</strong>
            <p>Feature flag only; it cannot expose an MCP endpoint by itself.</p>
        </div>
        <div class="mcp-status-card">
            <span class="mcp-stat-label">OAuth 2.1 authorization</span>
            <strong>{{ $oauthReady ? 'Configured' : 'Not configured' }}</strong>
            <p>Consent, token rotation and revocation are required before connection.</p>
        </div>
        <div class="mcp-status-card">
            <span class="mcp-stat-label">Remote write operations</span>
            <strong>{{ ($flagEnabled && $writeFlagEnabled) ? 'Scoped writes enabled' : 'Locked' }}</strong>
            <p>Publishing and configuration changes must require explicit approval.</p>
        </div>
        <div class="mcp-status-card">
            <span class="mcp-stat-label">Proposed tools</span>
            <strong>{{ count($tools) }}</strong>
            <p>{{ $readCount }} read-only · {{ $writeCount }} write/publish proposals · none registered.</p>
        </div>
    </section>

    <section class="mcp-panel mcp-browser-section" aria-labelledby="mcp-browser-heading"
        data-mcp-issue-url="{{ route('admin.system-settings.mcp.sessions.issue') }}"
        data-mcp-password-url="{{ route('admin.system-settings.mcp.confirm-password') }}"
        data-mcp-revoke-prefix="{{ url('/dashboard/system-settings/mcp/sessions') }}">
        <div class="mcp-panel-header">
            <div>
                <span class="mcp-panel-kicker">WEBSITE ADMIN AUTHENTICATION</span>
                <h3 id="mcp-browser-heading">Connect through LearnCambodia Admin</h3>
                <p>You're signed in through the website's admin session. A separate password confirmation and any configured 2FA protect short-lived MCP permissions.</p>
            </div>
            <span class="mcp-neutral-chip">Browser consent</span>
        </div>
        <div class="mcp-browser-body">
            <div class="mcp-browser-controls">
                <button type="button" class="mcp-connect-button" data-mcp-open-auth>Authenticate in Browser</button>
                <a class="mcp-regular-link" href="{{ route('login') }}" target="_blank" rel="noopener">Admin login portal</a>
            </div>
            <p class="mcp-bridge-warning">Direct short-lived bearer bridge: this is not yet the OAuth 2.1 automatic callback used by ChatGPT connectors. Remote operations remain blocked while the gateway is disabled.</p>
            <h4>Authorized browser sessions</h4>
            <div class="mcp-table-scroll">
                <table class="mcp-table mcp-session-table">
                    <thead><tr><th>Client</th><th>Scopes</th><th>Expires</th><th>Last used</th><th>Access</th></tr></thead>
                    <tbody data-mcp-sessions>
                    @forelse ($bridgeSessions as $session)
                        <tr data-mcp-session-id="{{ $session->id }}">
                            <td><strong>{{ $session->name }}</strong><small>ID {{ $session->id }}</small></td>
                            <td>{{ implode(', ', array_values(array_filter($session->abilities ?? [], fn($scope)=>$scope!=='mcp:use'))) }}</td>
                            <td>{{ $session->expires_at?->format('Y-m-d H:i') ?? 'Not set' }}</td>
                            <td>{{ $session->last_used_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                            <td><button type="button" class="mcp-revoke-button" data-mcp-revoke="{{ $session->id }}">Revoke</button></td>
                        </tr>
                    @empty
                        <tr data-mcp-empty><td colspan="5">No browser-authorized MCP sessions.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <dialog id="mcp-browser-auth-dialog" class="mcp-detail-dialog mcp-browser-dialog" aria-labelledby="mcp-browser-modal-title">
            <div class="mcp-dialog-header">
                <div><span class="mcp-panel-kicker">ADMIN PERMISSION CONSENT</span><h3 id="mcp-browser-modal-title">Connect to Learn Cambodia Admin</h3></div>
                <button class="mcp-close" data-mcp-auth-close type="button" aria-label="Close consent dialog">✕</button>
            </div>
            <form id="mcp-browser-auth-form">
                <p>Authorize a specific client to perform only the selected actions. This session never receives your website password or 2FA code.</p>
                <label class="mcp-field-label" for="mcp-client-name">Session / client name</label>
                <input id="mcp-client-name" name="client_name" maxlength="60" minlength="3" required value="LearnCambodia Admin MCP">
                <fieldset class="mcp-consent-scopes"><legend>Permissions requested</legend>
                    @foreach($bridgeScopes as $scope)
                        <label><input type="checkbox" name="scopes[]" value="{{ $scope }}" @checked($scope==='mcp:read')>
                            <span>{{ strtoupper(str_replace('mcp:', '', $scope)) }}</span></label>
                    @endforeach
                </fieldset>
                <p class="mcp-bridge-warning">WRITE, UPLOAD, DELETE, PUBLISH and MANAGE require confirmed 2FA. Deletion also requires a separate browser approval. Code install/push is not available through this credential.</p>
                <label class="mcp-field-label" for="mcp-expiry">Session expiry</label>
                <select id="mcp-expiry" name="expires_in_minutes">
                    <option value="15">15 minutes</option><option value="30" selected>30 minutes</option><option value="60">60 minutes</option>
                </select>
                <label class="mcp-consent-confirm"><input type="checkbox" id="mcp-consent-accept" required>
                    I understand the requested access and approve issuing a short-lived token.</label>
                <p class="mcp-password-help" role="note">
                    <strong>Website password confirmation is required.</strong>
                    After clicking Approve, this modal will show a secure password field.
                    If it does not appear, <a href="{{ route('password.confirm') }}" target="_blank" rel="noopener noreferrer">confirm your Website Admin password on LearnCambodia</a>,
                    then return here and approve the session again.
                </p>
                <div class="mcp-dialog-footer">
                    <button class="mcp-primary-close" type="submit" data-mcp-connect-submit>Approve browser session</button>
                </div>
            </form>
            <form id="mcp-browser-password-form" hidden autocomplete="off">
                <div class="mcp-password-step-heading"><span class="mcp-panel-kicker">SECURE REAUTHENTICATION</span>
                    <h4>Confirm your Website Admin password</h4>
                </div>
                <p>Enter the password for your LearnCambodia administrator account to approve this browser session. The password goes only to LearnCambodia over HTTPS and will never be included in the MCP token.</p>
                <label class="mcp-field-label" for="mcp-browser-password">Website Admin password</label>
                <input id="mcp-browser-password" name="password" type="password" autocomplete="current-password"
                    required minlength="1" aria-describedby="mcp-browser-feedback" placeholder="Enter your website password">
                <div class="mcp-dialog-footer">
                    <button type="button" class="mcp-close-text" data-mcp-password-back>Back</button>
                    <button type="submit" class="mcp-primary-close" data-mcp-password-submit>Confirm password and authorize</button>
                </div>
                <p class="mcp-password-alternative"><a href="{{ route('password.confirm') }}" target="_blank" rel="noopener">Open the website password confirmation page</a></p>
            </form>
            <div id="mcp-browser-issued" hidden>
                <strong>Browser session created — copy the token once</strong>
                <p>This token is shown only now. It is not a complete ChatGPT OAuth connection.</p>
                <textarea id="mcp-issued-token" rows="3" readonly spellcheck="false" aria-label="One-time MCP bridge token"></textarea>
                <div class="mcp-dialog-footer">
                    <button type="button" class="mcp-primary-close" data-mcp-copy-token>Copy token</button>
                    <button type="button" class="mcp-close-text" data-mcp-auth-close>Close</button>
                </div>
            </div>
            <p id="mcp-browser-feedback" role="status" aria-live="polite"></p>
        </dialog>
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
            <a class="mcp-regular-link" href="{{ url('/dashboard/appearance/home/campaign-builder') }}?lang=en">Open Campaign Builder <span aria-hidden="true">↗</span></a>
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

    <section class="mcp-panel" aria-labelledby="mcp-audit-heading">
        <div class="mcp-panel-header"><div><span class="mcp-panel-kicker">OPERATION RECEIPTS</span><h3 id="mcp-audit-heading">Recent MCP activity</h3></div></div>
        <div class="mcp-table-scroll"><table class="mcp-table">
        <thead><tr><th>Operation</th><th>Resource</th><th>Status</th><th>HTTP status</th><th>Receipt ID</th><th>Date</th></tr></thead>
        <tbody>
        @forelse($bridgeAudits as $receipt)
            <tr><td><strong>{{ $receipt->tool }}</strong><small>{{ $receipt->operation }}</small></td>
                <td>{{ $receipt->resource }} {{ $receipt->resource_id }}</td>
                <td>{{ $receipt->result }}</td><td>{{ $receipt->http_status }}</td>
                <td><code>{{ $receipt->id }}</code></td><td>{{ $receipt->created_at?->format('Y-m-d H:i') }}</td></tr>
        @empty
            <tr><td colspan="6">No MCP operation receipts yet.</td></tr>
        @endforelse
        </tbody></table></div>
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
