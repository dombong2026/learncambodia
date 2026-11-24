<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EditorMediaController;
use App\Models\McpActionAudit;
use App\Support\McpActionConsent;
use App\Support\McpCrudEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class McpBridgeController extends Controller
{
    private function requireSession(Request $request): void
    {
        abort_unless(config('mcp.gateway_enabled', false), 404,
            'MCP remote transport is disabled by configuration.');
        $actor=$request->user();
        $token=$actor?->currentAccessToken();
        abort_unless($actor?->isAdministrator()
            && $token instanceof PersonalAccessToken
            && str_starts_with($token->name,'LearnCambodia MCP: ')
            && $token->expires_at?->isFuture()
            && $token->can('mcp:use'), 403, 'Active browser-authorized Website Admin MCP session required.');
        $origin=$request->header('Origin');
        abort_if($origin!==null && !in_array($origin, [
            'https://learncambodia.com','https://www.learncambodia.com',
        ],true),403,'Unrecognized browser origin.');
    }

    public function authGuide(): JsonResponse
    {
        return response()->json([
            'status'=>'browser_authentication_required',
            'admin_login_url'=>url('/login'),
            'admin_management_url'=>url('/dashboard/system-settings/mcp?lang=en'),
            'note'=>'Sign in on the LearnCambodia website, complete 2FA if enabled and confirm permissions there. Automatic OAuth callback requires a future audited OAuth provider integration.',
        ])->header('Cache-Control','no-store');
    }

    public function upload(Request $request, EditorMediaController $media): JsonResponse
    {
        $this->requireSession($request);
        abort_unless(config('mcp.remote_writes_enabled',false),403);
        abort_unless($request->user()->tokenCan('mcp:upload'),403);
        abort_unless($request->user()->hasEnabledTwoFactorAuthentication(),403);
        $context=(string)$request->input('context','library');
        abort_unless(in_array($context,['library','course','lesson','exam'],true),422,
            'Unsupported MCP media context.');
        $request->headers->set('Accept','application/json');
        $request->merge(['context'=>$context]);
        $response=$request->input('context')==='library'
            ? $media->uploadToLibrary($request)
            : $media->store($request);
        abort_unless($response instanceof JsonResponse,500);
        $body=$response->getData(true);
        $this->audit($request,'media.upload','UPLOAD','media',(string)($body['id']??''),'success',201,'Uploaded verified media through existing library validation.');

        return $response->header('Cache-Control','no-store');
    }

    public function handle(Request $request): JsonResponse
    {
        $this->requireSession($request);
        abort_unless($request->isJson(),415,'MCP requests must use application/json.');
        $rpc=$request->json()->all();
        if(!is_array($rpc) || array_is_list($rpc) || ($rpc['jsonrpc']??'')!=='2.0'){
            return response()->json(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32600,'message'=>'Invalid JSON-RPC 2.0 request.']],400);
        }
        $id=$rpc['id']??null;
        $method=$rpc['method']??'';
        if(!is_string($method)){
            return $this->rpcError($id,-32600,'Invalid method.');
        }
        if($method==='notifications/initialized'){
            return response()->json([],202)->header('Cache-Control','no-store');
        }
        try{
            $result=match($method){
                'initialize'=>[
                    'protocolVersion'=>'2025-11-25',
                    'capabilities'=>['tools'=>['listChanged'=>false],'prompts'=>['listChanged'=>false]],
                    'serverInfo'=>['name'=>'learncambodia-admin-bridge','version'=>'0.1.0'],
                    'instructions'=>'Direct browser-issued bearer bridge only. OAuth 2.1 automatic callback is not enabled. Every mutation requires an explicit website-admin scope.',
                ],
                'ping'=>(object)[],
                'tools/list'=>['tools'=>self::tools()],
                'prompts/list'=>['prompts'=>self::prompts()],
                'prompts/get'=>$this->prompt($rpc['params']??[]),
                'tools/call'=>$this->call($request,$rpc['params']??[]),
                default=>null,
            };
            if($result===null)return $this->rpcError($id,-32601,'Method not found.');
            return response()->json(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result])
                ->header('Cache-Control','no-store');
        }catch(Throwable $error){
            if($method==='tools/call'){
                $params=$rpc['params']??[];
                $arguments=is_array($params)?($params['arguments']??[]):[];
                $operation=is_array($arguments)?(string)($arguments['operation']??'UNKNOWN'):'UNKNOWN';
                $resource=is_array($arguments)?(string)($arguments['resource']??''):'';
                $status=$error instanceof HttpExceptionInterface?$error->getStatusCode():422;
                $receipt=$this->audit($request,(string)($params['name']??'tools/call'),$operation,$resource,
                    isset($arguments['payload']['id'])?(string)$arguments['payload']['id']:'','failed',$status,
                    'Operation rejected. See validation or permission errors.');
                $message=$error instanceof \Illuminate\Validation\ValidationException
                    ? 'Invalid payload: '.implode('; ',array_slice(array_merge(...array_values($error->errors())),0,3))
                    : ($error instanceof HttpExceptionInterface
                        ? Str::limit($error->getMessage(),250)
                        : 'Operation rejected. Contact the website administrator with the receipt ID.');
                return response()->json(['jsonrpc'=>'2.0','id'=>$id,'result'=>[
                    'content'=>[['type'=>'text','text'=>$message]],
                    'isError'=>true,
                    'structuredContent'=>['status'=>'rejected','http_status'=>$status,'receipt_id'=>$receipt],
                ]])->header('Cache-Control','no-store');
            }
            return $this->rpcError($id,-32602,'Invalid request arguments.');
        }
    }

    private static function tools(): array
    {
        return [
            [
                'name'=>'auth_browser_admin',
                'description'=>'Return the verified LearnCambodia Admin browser-login and consent-page URLs. This cannot silently log in or grant new scopes.',
                'inputSchema'=>['type'=>'object','properties'=>[
                    'admin_portal_url'=>['type'=>'string'],
                    'scopes'=>['type'=>'string'],
                ],'additionalProperties'=>false],
            ],
            McpCrudEngine::toolSchema(),
            [
                'name'=>'confirm_destructive_action',
                'description'=>'Request a separate website-admin confirmation for one exact DELETE operation. No mutation occurs until a matching approved request is executed.',
                'inputSchema'=>[
                    'type'=>'object',
                    'properties'=>[
                        'resource_type'=>['type'=>'string','enum'=>['courses','posts','pages','policies','help_articles']],
                        'resource_id'=>['type'=>'string'],
                    ],
                    'required'=>['resource_type','resource_id'],
                    'additionalProperties'=>false,
                ],
            ],
        ];
    }

    private static function prompts(): array
    {
        return [
            ['name'=>'auth_browser_admin','description'=>'Website Admin login, 2FA and permission confirmation','arguments'=>[]],
            ['name'=>'crud_manage_platform','description'=>'Scoped LearnCambodia read, draft and approved CRUD operations',
                'arguments'=>[['name'=>'operation','required'=>true],['name'=>'resource','required'=>true]]],
            ['name'=>'confirm_destructive_action','description'=>'Browser-confirm a specific destructive operation',
                'arguments'=>[['name'=>'resource_type','required'=>true],['name'=>'resource_id','required'=>true]]],
        ];
    }

    private function prompt(array $params): array
    {
        $name=$params['name']??'';
        $msg=match($name){
            'auth_browser_admin'=>'Open '.url('/login').' in the browser, sign in using a Website Admin account, complete any 2FA challenge and visit '.url('/dashboard/system-settings/mcp').'. Grant scopes there; passwords never enter the MCP message.',
            'crud_manage_platform'=>'Specify READ, CREATE, UPDATE or DELETE with a resource and validated payload. Publishing and code deployment require the separate website administrator workflow.',
            'confirm_destructive_action'=>'First obtain a one-time approval URL from confirm_destructive_action. Open it in the website browser and type CONFIRM; only the matching session may subsequently use the approval.',
            default=>null,
        };
        abort_if($msg===null,404);
        return ['description'=>$name,'messages'=>[['role'=>'user','content'=>['type'=>'text','text'=>$msg]]]];
    }

    private function call(Request $request,array $params): array
    {
        $name=$params['name']??null;
        $args=$params['arguments']??[];
        abort_unless(is_string($name) && is_array($args),422);
        if($name==='auth_browser_admin'){
            $data=['status'=>'browser_login_required','login_url'=>url('/login'),
                'consent_url'=>url('/dashboard/system-settings/mcp?lang=en'),
                'message'=>'Complete website-admin login and 2FA, then issue the desired scopes from the browser. This is not an automatic OAuth callback.'];
            $data['receipt_id']=$this->audit($request,$name,'AUTH','mcp_session','','success',200,
                'Website login and permission-consent URLs returned.');
            $data['http_status']=200;
            return self::content($data);
        }
        if($name==='confirm_destructive_action'){
            abort_unless(config('mcp.remote_writes_enabled',false)
                && $request->user()->tokenCan('mcp:delete'),403);
            $resource=(string)($args['resource_type']??'');
            $id=(string)($args['resource_id']??'');
            $approval=McpActionConsent::request($request,'DELETE',$resource,$id);
            $data=['status'=>'awaiting_browser_approval','approval_id'=>$approval->id,
                'approval_url'=>route('admin.system-settings.mcp.approvals.show',['approval'=>$approval->id]),
                'expires_at'=>$approval->expires_at->toIso8601String(),
                'message'=>'No resource was deleted. Open the website-admin confirmation form, then submit DELETE with the approval_id.'];
            $data['receipt_id']=$this->audit($request,$name,'CONFIRM',$resource,$id,'pending',202,'Awaiting website browser approval.');
            return self::content($data);
        }
        abort_unless($name==='crud_manage_platform',422,'Unknown MCP tool.');
        // The operation, approval consumption and receipt must commit together.
        $result=DB::transaction(function()use($request,$name,$args){
            $out=McpCrudEngine::execute($request,$args);
            $out['status']=$out['status']??'read';
            $out['resource']=$out['resource']??(string)$args['resource'];
            $out['http_status']=$out['status']==='created'?201:200;
            $out['receipt_id']=$this->audit($request,$name,(string)$args['operation'],
                (string)$args['resource'],(string)($out['resource_id']??''),'success',
                $out['http_status'],'Authorized operation completed.');
            return $out;
        },3);
        return self::content($result);
    }

    private static function content(array $result): array
    {
        return [
            'content'=>[['type'=>'text','text'=>json_encode($result,JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]],
            'structuredContent'=>$result,
            'isError'=>false,
        ];
    }

    private function rpcError($id,int $code,string $message): JsonResponse
    {
        return response()->json(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]],200)
            ->header('Cache-Control','no-store');
    }

    private function audit(Request $request,string $tool,string $operation,string $resource,
        string $id,string $result,int $httpStatus,string $summary): string
    {
        $receipt=(string)Str::uuid();
        McpActionAudit::create([
            'id'=>$receipt,
            'user_id'=>$request->user()->id,
            'token_id'=>$request->user()->currentAccessToken()?->id,
            'tool'=>Str::limit($tool,75,''),
            'operation'=>Str::limit($operation,20,''),
            'resource'=>Str::limit($resource,70,''),
            'resource_id'=>$id!==''?Str::limit($id,80,''):null,
            'result'=>$result,'http_status'=>$httpStatus,
            'summary'=>Str::limit($summary,250,''),
        ]);
        return $receipt;
    }
}
