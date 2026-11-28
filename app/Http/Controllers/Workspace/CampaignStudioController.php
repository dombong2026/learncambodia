<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AppearanceRevision;
use App\Models\EditorMedia;
use App\Support\Appearance;
use App\Support\ManagedFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

final class CampaignStudioController extends Controller
{
    public function edit()
    {
        $state=DB::table('homepage_campaigns')->where('id',1)->first();
        return view('workspace.admin.campaign-studio', ['role'=>'admin','rows'=>json_decode($state->draft ?? $state->published,true),'version'=>$state->version,'hasDraft'=>$state->draft !== null,'publishedAt'=>$state->published_at]);
    }

    public function media(Request $request)
    {
        $request->validate(['file'=>['required_without:media_id','prohibits:media_id','file','max:51200','mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm'], 'media_id'=>['required_without:file','integer']]);
        $dir = storage_path('app/campaign-assets');
        if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) abort(500);
        $tmp = tempnam($dir,'stage-');
        try {
            if ($request->hasFile('file')) {
                if (!copy($request->file('file')->getRealPath(),$tmp)) abort(500);
            } else {
                $item = EditorMedia::where('context','library')->findOrFail($request->integer('media_id'));
                $stream = ManagedFiles::disk()->readStream($item->path);
                if (!$stream) abort(422,'Media library file unavailable.');
                $out = fopen($tmp,'wb');
                try { $size = stream_copy_to_stream($stream,$out,52428801); }
                finally { fclose($out); fclose($stream); }
                abort_if($size === false || $size > 52428800,422,'Maximum file size is 50 MB.');
            }
            $mime = mime_content_type($tmp);
            $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','video/webm'=>'webm'][$mime] ?? null;
            if (!$ext) throw ValidationException::withMessages(['file'=>'Choose JPEG, PNG, WebP, MP4 or WebM media.']);
            $image = str_starts_with($mime,'image/');
            if ($image) {
                $dimensions = getimagesize($tmp);
                $w=$dimensions[0] ?? 0; $h=$dimensions[1] ?? 0;
            } else {
                $probe = new Process(['/usr/bin/ffprobe','-v','error','-protocol_whitelist','file','-select_streams','v:0','-show_entries','stream=width,height:stream_side_data=rotation','-of','json',$tmp]);
                $probe->setTimeout(15)->run();
                $v=json_decode($probe->getOutput(),true)['streams'][0] ?? [];
                $w=$v['width'] ?? 0; $h=$v['height'] ?? 0;
                foreach ($v['side_data_list'] ?? [] as $meta) if (abs($meta['rotation'] ?? 0)%180===90) { [$w,$h]=[$h,$w]; break; }
            }
            if ($w!==1920 || $h!==1080) throw ValidationException::withMessages(['file'=>"Media must be 1920 × 1080. Detected {$w} × {$h}."]);
            $asset=hash_file('sha256',$tmp).'.'.$ext;
            if (!is_file($dir.'/'.$asset) && !rename($tmp,$dir.'/'.$asset)) abort(500);
            return response()->json(['asset'=>$asset,'type'=>$image?'image':'video','url'=>route('campaign.asset',$asset,false)]);
        } finally { if(is_file($tmp)) unlink($tmp); }
    }

    public function asset(Request $request,string $asset)
    {
        $public = json_decode(DB::table('homepage_campaigns')->where('id',1)->value('published'),true) ?: [];
        abort_unless(in_array($asset,array_column($public,'asset'),true) || $request->user()?->isAdministrator(),404);
        return response()->file(storage_path('app/campaign-assets/'.$asset),['Cache-Control'=>'private, no-cache','X-Content-Type-Options'=>'nosniff']);
    }

    public function save(Request $request)
    {
        abort_unless($request->user()?->isAdministrator(),403);
        $data=$request->validate([
            'action'=>'required|in:draft,publish','version'=>'required|integer','rows'=>'present|array|max:60',
            'rows.*'=>'array:course,lesson,type,url,asset,body,language,questions,feedback',
            'rows.*.course'=>'required|string|max:140','rows.*.lesson'=>'nullable|string|max:140',
            'rows.*.type'=>'required|in:text,html,emoji,code,youtube,image,video,quiz,exam',
            'rows.*.url'=>'nullable|string|max:2048','rows.*.asset'=>'nullable|string|max:75',
            'rows.*.body'=>'nullable|string|max:20000','rows.*.feedback'=>'nullable|string|max:1000',
            'rows.*.language'=>'nullable|string|max:40','rows.*.questions'=>'nullable|array|max:50',
            'rows.*.questions.*'=>'array:prompt,options,correct,feedback',
            'rows.*.questions.*.prompt'=>'nullable|string|max:1000','rows.*.questions.*.options'=>'array|min:2|max:6',
            'rows.*.questions.*.options.*'=>'nullable|string|max:500','rows.*.questions.*.correct'=>'nullable|integer|min:0|max:5',
            'rows.*.questions.*.feedback'=>'nullable|string|max:1000',
        ]);
        $publish=$data['action']==='publish';
        DB::transaction(function() use($data,$publish,$request) {
            $state=DB::table('homepage_campaigns')->where('id',1)->lockForUpdate()->first();
            if ((int)$state->version!==(int)$data['version']) throw ValidationException::withMessages(['version'=>'This campaign changed in another tab. Reload to edit its latest version.']);
            $legacy=array_merge(json_decode($state->draft ?? '[]',true),json_decode($state->published,true));
            $rows=[];
            foreach ($data['rows'] as $index=>$row) {
                $fail=static function($message) use($index) {throw ValidationException::withMessages(['rows'=>"Lesson ".($index+1).". ".$message]);};
                $type=$row['type']; $url=$row['url'] ?? ''; $asset=$row['asset'] ?? '';
                if ($publish && empty(trim($row['lesson'] ?? ''))) $fail('Enter a lesson title.');
                if (in_array($type,['quiz','exam'],true)) {
                    $row['body']=\App\Support\EditorHtml::clean($row['body'] ?? '', true) ?? '';
                    $qs=array_values($row['questions'] ?? []);
                    if ($publish && !$qs) $fail('Add at least one question.');
                    foreach ($qs as &$q) {
                        $q['options']=array_values($q['options'] ?? []);
                        if ($publish && (empty(trim($q['prompt'] ?? '')) || count($q['options'])<2 || in_array('',array_map(fn($v)=>trim((string)$v),$q['options']),true) || !isset($q['correct'],$q['options'][$q['correct']]))) $fail('Complete each question, its choices and the answer key.');
                    }
                    unset($q); $row['questions']=$qs;
                } elseif ($type==='youtube' && ($url || $publish)) {
                    if (!preg_match('~^https://(?:(?:www\\.|m\\.)?youtube\\.com/(?:watch\\?v=|shorts/|embed/)|youtu\\.be/)([A-Za-z0-9_-]{11})(?:[?&#].*)?$~D',$url,$m)) $fail('Enter a YouTube video URL.');
                    $row['url']='https://www.youtube.com/embed/'.$m[1];
                } elseif (in_array($type,['image','video'],true) && ($url || $asset || $publish)) {
                    if ($asset && preg_match('/^[a-f0-9]{64}\.(jpg|png|webp|mp4|webm)$/D',$asset,$m) && is_file(storage_path('app/campaign-assets/'.$asset))) {
                        $row['type']=in_array($m[1],['mp4','webm'],true)?'video':'image';$row['url']=route('campaign.asset',$asset,false);
                    } elseif (!collect($legacy)->contains(fn($old)=>!empty($old['url']) && $old['url']===$url && $old['type']===$type && empty($old['asset']))) $fail('Choose a verified media file.');
                } elseif (in_array($type,['text','html','code','emoji'],true)) {
                    if ($type==='html') $row['body']=\App\Support\EditorHtml::clean($row['body'] ?? '',true) ?? '';
                    if ($publish && trim($row['body'] ?? '')==='') $fail('Add lesson content.');
                }
                if ($asset && !in_array($type,['image','video'],true)) {
                    if (!preg_match('/^[a-f0-9]{64}\.(jpg|png|webp|mp4|webm)$/D',$asset)
                        || !is_file(storage_path('app/campaign-assets/'.$asset))) $fail('Choose verified uploaded lesson media.');
                    $row['url']=route('campaign.asset',$asset,false);
                }
                if (!$asset && !in_array($type,['image','video'],true)) unset($row['asset']);
                $rows[]=$row;
            }
            $json=json_encode($rows,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $update=['draft'=>$publish?null:$json,'version'=>$state->version+1,'updated_by'=>$request->user()->id];
            if ($publish) $update+=['published'=>$json,'published_at'=>now()];
            DB::table('homepage_campaigns')->where('id',1)->update($update);
        });
        return response()->json(['version'=>(int)$data['version']+1,'message'=>$publish?'Campaign published.':'Campaign draft saved.']);
    }

    public function check(Request $request)
    {
        $data=$request->validate(['key'=>'required|string|size:64','answers'=>'required|array|max:50','answers.*'=>'required|integer|min:0|max:5']);
        $rows=json_decode(DB::table('homepage_campaigns')->where('id',1)->value('published'),true);
        foreach ($rows as $row) {
            if (!in_array($row['type'],['quiz','exam'],true) || !hash_equals(self::lessonKey($row),$data['key'])) continue;
            $results=self::grade($row['questions'],$data['answers']);
            return response()->json(['results'=>$results,'score'=>count(array_filter(array_column($results,'correct'))),'total'=>count($results)]);
        }
        abort(409,'This lesson has changed. Reload the homepage.');
    }

    public static function lessonKey(array $row): string
    {
        return hash_hmac('sha256',json_encode($row,JSON_THROW_ON_ERROR),config('app.key'));
    }

    public static function grade(array $questions,array $answers): array
    {
        $results=[];
        foreach ($questions as $i=>$q) {
            if (!isset($answers[$i],$q['options'][$answers[$i]])) throw ValidationException::withMessages(['answers'=>'Choose an answer for every question.']);
            $correct=(int)$answers[$i]===(int)$q['correct'];
            $results[]=['correct'=>$correct,'answer'=>$q['options'][$q['correct']],'feedback'=>$q['feedback'] ?? ''];
        }
        return $results;
    }
}
