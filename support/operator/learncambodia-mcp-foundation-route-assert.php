<?php
/**
 * Resolve route metadata as Laravel registered it. Do not parse 'artisan
 * route:list --json' for authorization: that command may expand aliases
 * (e.g. role:admin -> App\\Http\\Middleware\\EnsureRole:admin).
 *
 * This validator MUST fail closed on a missing controller or middleware.
 * Invoked from the site's root by the pinned release script.
 */
declare(strict_types=1);

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$routes = app('router')->getRoutes();
$matches = [];
foreach ($routes as $route) {
    if ($route->getName() === 'admin.system-settings.mcp') {
        $matches[] = $route;
    }
}
if (count($matches) !== 1) {
    fwrite(STDERR, "MCP_ADMIN_ROUTE_COUNT_INVALID=".count($matches)."\n");
    exit(2);
}
$route = $matches[0];
if (!in_array('GET', $route->methods(), true)
    || $route->uri() !== 'dashboard/system-settings/mcp') {
    fwrite(STDERR, "MCP_ADMIN_ROUTE_TARGET_INVALID\n");
    exit(2);
}
$action = ltrim($route->getActionName(), '\\');
$expected = \App\Http\Controllers\Workspace\AdminMcpManagementController::class.'@index';
if ($action !== $expected) {
    fwrite(STDERR, "MCP_ADMIN_CONTROLLER_MISMATCH\n");
    exit(2);
}

/* Gather raw, declared middleware; this preserves role:admin even when
 * route:list expands the alias into the EnsureRole class name. */
$middleware = $route->gatherMiddleware();
if (!is_array($middleware)) {
    fwrite(STDERR, "MCP_ADMIN_MIDDLEWARE_UNAVAILABLE\n");
    exit(2);
}
$auth = in_array('auth', $middleware, true)
    || in_array('auth:web', $middleware, true);
$role = in_array('role:admin', $middleware, true)
    || in_array(\App\Http\Middleware\EnsureRole::class.':admin', $middleware, true);
if (!$auth || !$role) {
    // Only safe middleware labels; never print tokens, request context, etc.
    fwrite(STDERR, "MCP_ADMIN_AUTH_MISSING (auth=".($auth?'yes':'no')
        .", role_admin=".($role?'yes':'no').")\n");
    exit(2);
}
echo "MCP_ADMIN_ROUTE_AUTH=PASS\n";
echo "MCP_ADMIN_ROUTE_CONTROLLER=PASS\n";
echo "MCP_ADMIN_ROUTE_TARGET=PASS\n";
