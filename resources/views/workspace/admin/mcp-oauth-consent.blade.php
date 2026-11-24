<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Authorize ChatGPT — LearnCambodia MCP</title>
    <style>
        :root{color-scheme:light dark;font-family:Inter,ui-sans-serif,system-ui,sans-serif}
        body{margin:0;padding:32px 16px;min-height:100vh;background:light-dark(#f3f6fa,#10151c);color:light-dark(#18202d,#e9edf4);display:grid;place-items:center;box-sizing:border-box}
        main{width:100%;max-width:540px;padding:30px;box-sizing:border-box;border:1px solid light-dark(#dce3ed,#394653);border-radius:18px;background:light-dark(#fff,#1d2834);box-shadow:0 12px 40px #0001}
        h1{font-size:1.45rem;margin:.4rem 0 1rem}p,li{line-height:1.55}small{opacity:.72}
        .app{font-weight:700;font-size:.82rem;letter-spacing:.06em;text-transform:uppercase}
        .permissions{padding:14px 18px;border:1px solid light-dark(#e0e5ec,#45525f);border-radius:10px;margin:20px 0}
        .actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        button{width:100%;min-height:44px;cursor:pointer;font:inherit;font-weight:600;padding:11px 14px;border-radius:9px}
        .approve{background:#1456cf;color:white;border:1px solid #1456cf}
        .deny{background:transparent;color:inherit;border:1px solid light-dark(#b4becc,#617083)}
        @media(max-width:380px){.actions{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main>
    <div class="app">LearnCambodia · MCP</div>
    <h1>Authorize access to your account?</h1>
    <p><strong>{{ $client->name }}</strong> requests permission to read limited LearnCambodia information through ChatGPT.</p>
    <div class="permissions">
        <strong>Requested permissions</strong>
        <ul>
        @foreach($scopes as $scope)
            <li>{{ $scope->description }}</li>
        @endforeach
        </ul>
        <small>No course changes, publishing, deletions, file uploads or administrator management permissions are granted.</small>
    </div>
    <p><small>Signed in as {{ $user->email }}. Only authorize applications you trust. You can revoke access in your MCP management settings.</small></p>
    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button class="deny" type="submit">Deny access</button>
        </form>
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button class="approve" type="submit">Authorize READ access</button>
        </form>
    </div>
</main>
</body>
</html>
