<?php
/**
 * Validate live MCP bridge routes from Laravel's registered metadata.
 * route:list JSON may resolve role aliases into class names, so validate
 * the declared middleware instead. Must fail closed.
 */
declare(strict_types=1);

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$definitions = [
    'admin.system-settings.mcp.sessions.issue' => [
        'method' => 'POST',
        'path' => 'dashboard/system-settings/mcp/sessions',
        'controller' => \App\Http\Controllers\Workspace\McpAdminSessionController::class.'@issue',
        'middleware' => ['auth', 'role:admin', 'password.confirm'],
    ],
    'admin.system-settings.mcp.sessions.revoke' => [
        'method' => 'DELETE',
        'path' => 'dashboard/system-settings/mcp/sessions/{token}',
        'controller' => \App\Http\Controllers\Workspace\McpAdminSessionController::class.'@revoke',
        'middleware' => ['auth', 'role:admin'],
    ],
    'admin.system-settings.mcp.approvals.show' => [
        'method' => 'GET',
        'path' => 'dashboard/system-settings/mcp/approvals/{approval}',
        'controller' => \App\Http\Controllers\Workspace\McpBrowserApprovalController::class.'@show',
        'middleware' => ['auth', 'role:admin', 'password.confirm'],
    ],
    'admin.system-settings.mcp.approvals.decide' => [
        'method' => 'POST',
        'path' => 'dashboard/system-settings/mcp/approvals/{approval}',
        'controller' => \App\Http\Controllers\Workspace\McpBrowserApprovalController::class.'@decide',
        'middleware' => ['auth', 'role:admin', 'password.confirm'],
    ],
    'mcp.rpc' => [
        'method' => 'POST',
        'path' => 'api/mcp',
        'controller' => \App\Http\Controllers\Api\McpBridgeController::class.'@handle',
        'middleware' => ['auth:sanctum'],
    ],
    'mcp.media.upload' => [
        'method' => 'POST',
        'path' => 'api/mcp/media',
        'controller' => \App\Http\Controllers\Api\McpBridgeController::class.'@upload',
        'middleware' => ['auth:sanctum'],
    ],
];

$routes = app('router')->getRoutes();
foreach ($definitions as $name => $expected) {
    $matches = [];
    foreach ($routes as $route) {
        if ($route->getName() === $name) {
            $matches[] = $route;
        }
    }
    if (count($matches) !== 1) {
        fwrite(STDERR, "MCP_BRIDGE_ROUTE_COUNT_INVALID:{$name}\n");
        exit(2);
    }
    $route = $matches[0];
    if ($route->uri() !== $expected['path']
        || !in_array($expected['method'], $route->methods(), true)
        || ltrim($route->getActionName(), '\\') !== $expected['controller']) {
        fwrite(STDERR, "MCP_BRIDGE_ROUTE_TARGET_INVALID:{$name}\n");
        exit(2);
    }
    $middleware = $route->gatherMiddleware();
    if (!is_array($middleware)) {
        fwrite(STDERR, "MCP_BRIDGE_MIDDLEWARE_UNAVAILABLE:{$name}\n");
        exit(2);
    }
    foreach ($expected['middleware'] as $need) {
        $allowed = match ($need) {
            'auth' => ['auth', 'auth:web'],
            'role:admin' => ['role:admin', \App\Http\Middleware\EnsureRole::class.':admin'],
            default => [$need],
        };
        if (!array_intersect($allowed, $middleware)) {
            fwrite(STDERR, "MCP_BRIDGE_AUTH_MISSING:{$name}:{$need}\n");
            exit(2);
        }
    }
}
echo "MCP_ADMIN_ROUTE_AUTH=PASS\n";
echo "MCP_BRIDGE_CONTROLLER_TARGETS=PASS\n";
echo "MCP_BRIDGE_SANCTUM_AUTH=PASS\n";
