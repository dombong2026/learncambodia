<?php

use App\Console\Commands\AssignAccountRole;
use App\Console\Commands\RegisterMcpOAuthClient;
use App\Http\Middleware\EnsureDeveloperToken;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\EnsureMcpOAuthStagingEnabled;
use App\Http\Middleware\RequireMcpOAuthAdmin;
use App\Http\Middleware\RequireMcpOAuthRead;
use App\Http\Middleware\ValidateMcpOAuthParameters;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([AssignAccountRole::class, RegisterMcpOAuthClient::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'role' => EnsureRole::class,
            'developer.token' => EnsureDeveloperToken::class,
            'mcp.oauth.stage' => EnsureMcpOAuthStagingEnabled::class,
            'mcp.oauth.admin' => RequireMcpOAuthAdmin::class,
            'mcp.oauth.read' => RequireMcpOAuthRead::class,
            'mcp.oauth.params' => ValidateMcpOAuthParameters::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
