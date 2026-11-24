<?php

namespace App\Support;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseQuiz;
use App\Models\EditorMedia;
use App\Models\HomepageCampaignItem;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class McpCrudEngine
{
    private const EDITORIAL = [
        'posts'=>'post','pages'=>'page','policies'=>'policy','help_articles'=>'help_article',
    ];

    public static function toolSchema(): array
    {
        return [
            'name'=>'crud_manage_platform',
            'description'=>'Read selected admin resources and safely create/update drafts. Soft deletion requires separate browser confirmation. Arbitrary filesystem operations and deployments are not available.',
            'inputSchema'=>[
                'type'=>'object',
                'properties'=>[
                    'operation'=>['type'=>'string','enum'=>['READ','CREATE','UPDATE','UPLOAD','DELETE','PUBLISH','INSTALL','PUSH']],
                    'resource'=>['type'=>'string','enum'=>[
                        'courses','modules','lessons','quizzes','posts','pages','policies',
                        'help_articles','campaigns','media','users','support_tickets','categories','settings'
                    ]],
                    'payload'=>['type'=>'object'],
                ],
                'required'=>['operation','resource'],
                'additionalProperties'=>false,
            ],
        ];
    }

    private static function authorize(Request $request, string $operation, string $resource): void
    {
        $ability=match($operation){
            'READ'=>'mcp:read', 'CREATE','UPDATE'=>'mcp:write',
            'UPLOAD'=>'mcp:upload','DELETE'=>'mcp:delete',
            'PUBLISH'=>'mcp:publish','INSTALL','PUSH'=>'mcp:manage',
            default=>throw new HttpException(422,'Unsupported MCP operation.'),
        };
        abort_unless($request->user()?->tokenCan($ability),403,'MCP scope not granted.');
        if($resource==='users' || $resource==='settings'){
            abort_unless($request->user()->tokenCan('mcp:manage'),403,'Management scope required.');
        }
        if($operation!=='READ'){
            abort_unless(config('mcp.remote_writes_enabled',false),403,'MCP writes are disabled.');
            abort_unless($request->user()->hasEnabledTwoFactorAuthentication(),403,
                'A confirmed website-admin second factor is required for MCP mutations.');
        }
        if(in_array($resource,['users','settings'],true)){
            abort_unless($request->user()->hasEnabledTwoFactorAuthentication(),403,
                'A confirmed second factor is required for sensitive management reads.');
        }
    }

    public static function execute(Request $request, array $arguments): array
    {
        $data=Validator::make($arguments,[
            'operation'=>['required',Rule::in(['READ','CREATE','UPDATE','UPLOAD','DELETE','PUBLISH','INSTALL','PUSH'])],
            'resource'=>['required',Rule::in([
                'courses','modules','lessons','quizzes','posts','pages','policies',
                'help_articles','campaigns','media','users','support_tickets','categories','settings'
            ])],
            'payload'=>['sometimes','array'],
        ])->validate();
        $operation=$data['operation'];
        $resource=$data['resource'];
        $payload=$data['payload']??[];
        self::authorize($request,$operation,$resource);

        return match($operation){
            'READ'=>self::read($resource,$payload),
            'CREATE'=>self::create($request,$resource,$payload),
            'UPDATE'=>self::update($request,$resource,$payload),
            'DELETE'=>self::delete($request,$resource,$payload),
            'UPLOAD'=>throw new HttpException(422,'Use authenticated multipart POST /api/mcp/media. Arbitrary filesystem paths are not accepted.'),
            'PUBLISH'=>throw new HttpException(409,'Use the approved LearnCambodia review/publishing workflow; direct MCP publish is not yet enabled.'),
            'INSTALL','PUSH'=>throw new HttpException(403,'Remote installation and code deployment require a separately reviewed release and explicit human approval.'),
        };
    }

    private static function listing($query, ?int $id, array $columns): array
    {
        if($id !== null){
            $item=$query->findOrFail($id);
            return ['items'=>[self::fields($item,$columns)],'total'=>1];
        }
        $items=$query->orderByDesc('id')->limit(25)->get($columns);
        return ['items'=>$items->map(fn($record)=>self::fields($record,$columns))->all(),'limit'=>25];
    }

    private static function fields($record,array $columns): array
    {
        return collect($columns)->mapWithKeys(fn($field)=>[$field=>$record->{$field}])->all();
    }

    private static function read(string $resource,array $data): array
    {
        $filter=Validator::make($data,['id'=>['sometimes','integer','min:1']])->validate();
        $id=isset($filter['id'])?(int)$filter['id']:null;
        return match($resource){
            'courses'=>self::listing(Course::query(),$id,['id','title','slug','summary','status','category_id','content_language','updated_at']),
            'modules'=>self::listing(CourseModule::query(),$id,['id','course_id','title','position']),
            'lessons'=>self::listing(CourseLesson::query(),$id,['id','course_module_id','title','position','duration_minutes']),
            'quizzes'=>self::listing(CourseQuiz::query(),$id,['id','course_id','title','position','passing_percent']),
            'posts','pages','policies','help_articles'=>self::listing(
                ContentEntry::query()->where('type',self::EDITORIAL[$resource]),
                $id,['id','title','slug','type','summary','status','content_language','updated_at']),
            'campaigns'=>self::listing(HomepageCampaignItem::query(),$id,
                ['id','title','slug','kind','status','version','published_at']),
            'media'=>self::listing(EditorMedia::query()->where('context','library'),$id,
                ['id','original_name','mime','size','created_at']),
            'categories'=>self::listing(Category::query(),$id,['id','name','slug','group','is_published']),
            'support_tickets'=>self::listing(SupportTicket::query(),$id,
                ['id','kind','subject','priority','status','created_at']),
            'users'=>self::listing(User::query(),$id,
                ['id','name','role','email_verified_at','created_at']),
            'settings'=>['items'=>[
                'gateway_flag_enabled'=>(bool)config('mcp.gateway_enabled'),
                'write_flag_enabled'=>(bool)config('mcp.remote_writes_enabled'),
                'oauth_ready'=>(bool)config('mcp.oauth_ready'),
                'transport_ready'=>(bool)config('mcp.transport_ready'),
            ],'note'=>'Nonsecret MCP integration status only. Credentials and system secrets are never returned.'],
        };
    }

    private static function draftData(array $payload,bool $course=false): array
    {
        $rules=[
            'title'=>['required','string','max:180'],
            'slug'=>['nullable','string','max:180','regex:/^[a-z0-9-]+$/'],
            'summary'=>['nullable','string','max:1000'],
            'body'=>['nullable','string','max:25000'],
            'body_html'=>['nullable','string','max:60000'],
            'content_language'=>['nullable','string','max:100'],
        ];
        if($course){
            unset($rules['body'],$rules['body_html']);
            $rules['summary']=['required','string','max:1000'];
            $rules['description']=['nullable','string','max:25000'];
            $rules['category_id']=['required','integer','exists:categories,id'];
            $rules['delivery_mode']=['required',Rule::in(['online','onsite','hybrid'])];
        }
        $data=Validator::make($payload,$rules)->validate();
        $data['slug']=$data['slug']??Str::slug($data['title']);
        if(!$course && filled($data['body_html']??null)){
            $data['body_html']=EditorHtml::clean($data['body_html']);
            abort_if(EditorHtml::mediaIds($data['body_html'])!==[],422,
                'Attach verified editorial media through the existing website editor before publishing.');
            $data['body']=EditorHtml::text($data['body_html']);
        }
        return $data;
    }

    private static function create(Request $request,string $resource,array $payload): array
    {
        if(array_key_exists($resource,self::EDITORIAL)){
            $fields=self::draftData($payload);
            $fields['type']=self::EDITORIAL[$resource];
            $fields['status']='draft';
            $fields['created_by']=$request->user()->id;
            $fields['updated_by']=$request->user()->id;
            $fields['slug']=self::uniqueSlug(ContentEntry::class,$fields['slug']);
            $model=ContentEntry::create($fields);
        }elseif($resource==='courses'){
            $fields=self::draftData($payload,true);
            $fields['status']='draft';
            $fields['created_by']=$request->user()->id;
            $fields['slug']=self::uniqueSlug(Course::class,$fields['slug']);
            $model=Course::create($fields);
        }elseif($resource==='modules'){
            $fields=Validator::make($payload,[
                'course_id'=>['required','integer','exists:courses,id'],
                'title'=>['required','string','max:180'],
                'summary'=>['nullable','string','max:1000'],
            ])->validate();
            $course=Course::findOrFail($fields['course_id']);
            abort_unless($course->isMutable(),409,'This course is not in a mutable draft state.');
            $fields['position']=(int)$course->modules()->max('position')+1;
            $model=CourseModule::create($fields);
        }elseif($resource==='lessons'){
            $fields=Validator::make($payload,[
                'course_module_id'=>['required','integer','exists:course_modules,id'],
                'title'=>['required','string','max:180'],
                'content'=>['nullable','string','max:25000'],
                'content_html'=>['nullable','string','max:60000'],
                'duration_minutes'=>['sometimes','integer','between:0,1800'],
            ])->validate();
            $module=CourseModule::with('course')->findOrFail($fields['course_module_id']);
            abort_unless($module->course?->isMutable(),409,'Course is not in a mutable draft state.');
            if(filled($fields['content_html']??null)){
                $fields['content_html']=EditorHtml::clean($fields['content_html'],true);
                abort_if(EditorHtml::mediaIds($fields['content_html'])!==[],422,
                    'Course lesson media must be claimed through the website editor.');
                $fields['content']=EditorHtml::courseText($fields['content_html']);
            }
            $fields['position']=(int)$module->lessons()->max('position')+1;
            $model=CourseLesson::create($fields);
        }elseif($resource==='quizzes'){
            $fields=Validator::make($payload,[
                'course_id'=>['required','integer','exists:courses,id'],
                'title'=>['required','string','max:180'],
                'instructions'=>['nullable','string','max:2000'],
                'passing_percent'=>['nullable','integer','between:0,100'],
            ])->validate();
            $course=Course::findOrFail($fields['course_id']);
            abort_unless($course->isMutable(),409,'This course is not in a mutable draft state.');
            $fields['position']=(int)$course->quizzes()->max('position')+1;
            $model=CourseQuiz::create($fields);
        }else{
            throw new HttpException(422,'CREATE is not enabled for this resource. Use its existing administrator workflow.');
        }
        return ['status'=>'created','resource'=>$resource,'resource_id'=>$model->id,
            'admin_url'=>self::adminUrl($resource,$model),'reversible'=>true,
            'message'=>'Draft created. Publishing still requires the website approval workflow.'];
    }

    private static function update(Request $request,string $resource,array $payload): array
    {
        $id=Validator::make($payload,['id'=>['required','integer','min:1']])->validate()['id'];
        if(array_key_exists($resource,self::EDITORIAL)){
            $model=ContentEntry::query()->where('type',self::EDITORIAL[$resource])->findOrFail($id);
            abort_unless(in_array($model->status,['draft','rejected'],true),409,'Only draft or rejected editorial entries can be edited by MCP.');
            $fields=self::draftData($payload);
            if($fields['slug']!==$model->slug){
                $fields['slug']=self::uniqueSlug(ContentEntry::class,$fields['slug'],$model->id);
            }
            $fields['updated_by']=$request->user()->id;
        }elseif($resource==='courses'){
            $model=Course::findOrFail($id);
            abort_unless($model->isMutable(),409,'Only draft or rejected courses can be updated.');
            $fields=self::draftData($payload,true);
            if($fields['slug']!==$model->slug){
                $fields['slug']=self::uniqueSlug(Course::class,$fields['slug'],$model->id);
            }
        }elseif($resource==='modules'){
            $model=CourseModule::with('course')->findOrFail($id);
            abort_unless($model->course?->isMutable(),409,'Course is not in a mutable draft state.');
            $fields=Validator::make($payload,[
                'title'=>['required','string','max:180'],
                'summary'=>['nullable','string','max:1000'],
            ])->validate();
        }elseif($resource==='lessons'){
            $model=CourseLesson::with('module.course')->findOrFail($id);
            abort_unless($model->module?->course?->isMutable(),409,'Course is not in a mutable draft state.');
            $fields=Validator::make($payload,[
                'title'=>['required','string','max:180'],
                'content'=>['nullable','string','max:25000'],
                'content_html'=>['nullable','string','max:60000'],
                'duration_minutes'=>['sometimes','integer','between:0,1800'],
            ])->validate();
            if(filled($fields['content_html']??null)){
                $fields['content_html']=EditorHtml::clean($fields['content_html'],true);
                abort_if(EditorHtml::mediaIds($fields['content_html'])!==[],422,
                    'Course lesson media must be claimed through the website editor.');
                $fields['content']=EditorHtml::courseText($fields['content_html']);
            }
        }elseif($resource==='quizzes'){
            $model=CourseQuiz::with('course')->findOrFail($id);
            abort_unless($model->course?->isMutable(),409,'Course is not in a mutable draft state.');
            $fields=Validator::make($payload,[
                'title'=>['required','string','max:180'],
                'instructions'=>['nullable','string','max:2000'],
                'passing_percent'=>['nullable','integer','between:0,100'],
            ])->validate();
        }else{
            throw new HttpException(422,'UPDATE is not enabled for this resource.');
        }
        $model->fill($fields)->save();
        return ['status'=>'updated','resource'=>$resource,'resource_id'=>$model->id,
            'admin_url'=>self::adminUrl($resource,$model),
            'message'=>'Draft changes saved; publication/review rules remain in effect.'];
    }

    private static function uniqueSlug(string $model,string $raw,?int $except=null): string
    {
        $base=Str::slug($raw)?:'item';
        $base=Str::limit($base,150,'');
        $slug=$base;
        for($i=2;$i<1000;$i++){
            $exists=$model::withTrashed()->where('slug',$slug)
                ->when($except,fn($q)=>$q->whereKeyNot($except))->exists();
            if(!$exists)return $slug;
            $slug=$base.'-'.$i;
        }
        throw new HttpException(409,'Unable to allocate a unique slug.');
    }

    private static function delete(Request $request,string $resource,array $payload): array
    {
        abort_unless(array_key_exists($resource,self::EDITORIAL) || $resource==='courses',422,
            'This resource is not eligible for MCP soft deletion.');
        $data=Validator::make($payload,[
            'id'=>['required','integer','min:1'],
            'approval_id'=>['required','uuid'],
        ])->validate();
        $id=(string)$data['id'];
        return DB::transaction(function()use($request,$resource,$data,$id){
            if($resource==='courses'){
                $model=Course::query()->lockForUpdate()->findOrFail((int)$id);
                abort_if($model->enrollments()->exists(),409,'Enrolled courses cannot be removed by MCP.');
            }else{
                $model=ContentEntry::query()->where('type',self::EDITORIAL[$resource])
                    ->lockForUpdate()->findOrFail((int)$id);
            }
            McpActionConsent::consume($request,$data['approval_id'],'DELETE',$resource,$id);
            $model->delete();
            return ['status'=>'soft_deleted','resource'=>$resource,'resource_id'=>(int)$id,
                'admin_url'=>self::adminUrl($resource,$model),
                'reversible'=>true,'message'=>'Moved to soft-deleted state after website approval; permanent purge is not enabled.'];
        });
    }

    private static function adminUrl(string $resource,$model): string
    {
        if(array_key_exists($resource,self::EDITORIAL)){
            $routeKind=match($resource){'help_articles'=>'help-articles',default=>$resource};
            return url('/dashboard/editorial/'.$routeKind.'/'.$model->getRouteKey().'/edit');
        }
        return match($resource){
            'courses'=>url('/dashboard/course-builder/'.$model->getRouteKey().'/edit'),
            'modules','lessons','quizzes'=>url('/dashboard/courses'),
            default=>url('/dashboard'),
        };
    }
}
