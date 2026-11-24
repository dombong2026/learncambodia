<?php
/* MCP sidebar + password reauthentication + direct bearer activation validator.
 * Run from LearnCambodia site root after refreshing Laravel config/route caches.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$required=[
    'admin.system-settings.mcp.confirm-password'=>[
        'POST','dashboard/system-settings/mcp/confirm-password',
        \App\Http\Controllers\Workspace\McpAdminSessionController::class.'@confirmWebsitePassword',
        ['auth','role:admin'],
    ],
    'admin.system-settings.mcp.sessions.issue'=>[
        'POST','dashboard/system-settings/mcp/sessions',
        \App\Http\Controllers\Workspace\McpAdminSessionController::class.'@issue',
        ['auth','role:admin','password.confirm'],
    ],
    'mcp.rpc'=>[
        'POST','api/mcp',
        \App\Http\Controllers\Api\McpBridgeController::class.'@handle',
        ['auth:sanctum'],
    ],
];
$routes=app('router')->getRoutes();
foreach($required as $name=>$expect){
    $found=[];
    foreach($routes as $route)if($route->getName()===$name)$found[]=$route;
    if(count($found)!==1){
        fwrite(STDERR,"MCP_ROUTE_COUNT_INVALID:{$name}\n");exit(2);
    }
    $route=$found[0];
    if(!in_array($expect[0],$route->methods(),true)
        ||$route->uri()!==$expect[1]
        ||ltrim($route->getActionName(),'\\')!==$expect[2]){
        fwrite(STDERR,"MCP_ROUTE_TARGET_INVALID:{$name}\n");exit(2);
    }
    $middleware=$route->gatherMiddleware();
    foreach($expect[3] as $key){
        $valid=match($key){
            'auth'=>['auth','auth:web'],
            'role:admin'=>['role:admin',\App\Http\Middleware\EnsureRole::class.':admin'],
            default=>[$key],
        };
        if(!array_intersect($valid,$middleware)){
            fwrite(STDERR,"MCP_ROUTE_GUARD_MISSING:{$name}:{$key}\n");exit(2);
        }
    }
}
$settings=null;
foreach(\App\Support\WorkspaceMenu::groups('admin') as $group){
    if(($group['key']??null)==='system-settings')$settings=$group;
}
if(!is_array($settings)){
    fwrite(STDERR,"MCP_SIDEBAR_SYSTEM_SETTINGS_MISSING\n");exit(2);
}
$menu=array_values(array_filter($settings['items'],fn($item)=>
    ($item['route']??null)==='admin.system-settings.mcp'
));
if(count($menu)!==1 || $menu[0]['label']!=='MCP Management'){
    fwrite(STDERR,"MCP_SIDEBAR_ITEM_COUNT_INVALID=".count($menu)."\n");exit(2);
}
if(!config('mcp.gateway_enabled') || !config('mcp.remote_writes_enabled')){
    fwrite(STDERR,"MCP_DIRECT_CONFIG_NOT_ENABLED\n");exit(2);
}
if(config('mcp.oauth_ready')){
    fwrite(STDERR,"MCP_OAUTH_STATUS_INCORRECTLY_ENABLED\n");exit(2);
}
echo "MCP_DIRECT_ADMIN_ROUTES=PASS\n";
echo "MCP_DIRECT_PASSWORD_GUARDS=PASS\n";
echo "MCP_DIRECT_SANCTUM_GUARDS=PASS\n";
echo "MCP_SIDEBAR_LINK=PASS\n";
echo "MCP_DIRECT_FEATURE_FLAGS=PASS\n";
