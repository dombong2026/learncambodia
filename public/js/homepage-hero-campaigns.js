(() => {
'use strict';
const root=document.getElementById('hc-hero');
if(!root)return;
const get=id=>document.getElementById(id);
const safe=x=>String(x??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const chooser=get('hc-chooser'),player=get('hc-play'),errors=get('hc-error');
const csrf=root.dataset.csrf||document.querySelector('meta[name="csrf-token"]')?.content||'';
let questions=[],current=0,score=0,checkUrl='',busy=false;
const report=message=>{errors.textContent=message;errors.hidden=!message};
function back(){
    player.hidden=true;chooser.hidden=false;questions=[];current=0;score=0;checkUrl='';busy=false;report('');
}
const validatedURL=raw=>{
    try {
        const url=new URL(String(raw||''),location.href);
        return url.protocol==='https:' || (url.origin===location.origin &&
            url.pathname.startsWith('/campaign-media/')) ? url : null;
    }catch(_error){return null}
};
const youtubeId=url=>{
    if(!url)return '';
    const host=url.hostname.toLowerCase();
    if(!['youtube.com','www.youtube.com','m.youtube.com','youtu.be','www.youtu.be',
        'youtube-nocookie.com','www.youtube-nocookie.com'].includes(host))return '';
    const id=host.includes('youtu.be')?url.pathname.split('/')[1]:
        url.pathname==='/watch'?url.searchParams.get('v'):
        url.pathname.match(/^\/(?:embed|shorts|live)\/([^/]+)/)?.[1];
    return /^[a-z0-9_-]{11}$/i.test(id||'')?id:'';
};
function displayQuestionMedia(container,q){
    container.replaceChildren();
    const url=validatedURL(q.media_asset_url||q.media_url);
    if(!url){container.hidden=true;return}
    const yt=youtubeId(url);
    let type=q.media_asset_url?
        /\.(?:mp4|webm)$/.test(url.pathname)?'video':'image':
        q.media_kind||'auto';
    if(type==='auto'){
        type=yt?'youtube':
            /\.(png|jpg|jpeg|webp|gif|avif)$/i.test(url.pathname)?'image':
            /\.(mp4|webm|ogv)$/i.test(url.pathname)?'video':
            /\.(mp3|m4a|oga|wav)$/i.test(url.pathname)?'audio':'link';
    }
    let node;
    if(type==='youtube' && yt){
        node=document.createElement('iframe');
        node.src='https://www.youtube-nocookie.com/embed/'+yt;
        node.title='Question video';
        node.loading='lazy';
        node.referrerPolicy='strict-origin-when-cross-origin';
        node.setAttribute('sandbox','allow-scripts allow-same-origin allow-presentation');
        node.setAttribute('allow','encrypted-media; picture-in-picture; fullscreen');
        node.setAttribute('allowfullscreen','');
    }else if(type==='image'){
        node=document.createElement('img');
        node.alt='Question illustration';node.loading='lazy';node.src=url.href;
    }else if(type==='video'){
        node=document.createElement('video');
        node.src=url.href;node.controls=true;node.preload='metadata';
    }else if(type==='audio'){
        node=document.createElement('audio');
        node.src=url.href;node.controls=true;node.preload='none';
    }else{
        node=document.createElement('a');
        node.href=url.href;node.target='_blank';node.rel='noopener noreferrer';
        node.textContent='Open question media';
    }
    node.className='hc-question-media-element';
    container.appendChild(node);container.hidden=false;
}
function question(){
    if(!questions.length){back();report('This campaign has no available Quiz or Exam questions.');return}
    const q=questions[current];
    get('hc-progress').textContent=(current+1)+' / '+questions.length;
    get('hc-lesson').textContent=(q.module||'')+(q.lesson?' · '+q.lesson:'');
    get('hc-feedback').hidden=true;get('hc-feedback').textContent='';
    get('hc-next').hidden=true;get('hc-finish').hidden=true;
    const options=(q.options||[]).map((text,index)=>
        '<label class="hc-question-option"><input type="radio" name="hc-answer" value="'+index+'">'+
        '<span>'+safe(text)+'</span></label>').join('');
    // prompt_html has already been sanitized by EditorHtml on the server.
    const formatted=q.prompt_html||safe(q.prompt);
    get('hc-question').innerHTML=
        (q.body?'<div class="hc-instruction">'+q.body+'</div>':'')+
        '<div class="hc-prompt">Q'+(current+1)+'. '+formatted+'</div>'+
        '<div class="hc-question-media" data-hc-question-media hidden></div>'+
        '<form id="hc-answer-form"><fieldset><legend class="sr-only">Select the correct answer</legend>'+
        options+'</fieldset>'+
        '<button type="submit" class="hc-submit mt-3">Check answer</button></form>';
    displayQuestionMedia(get('hc-question').querySelector('[data-hc-question-media]'),q);
    get('hc-question').querySelector('form').addEventListener('submit',async event=>{
        event.preventDefault();
        if(busy)return;
        const chosen=get('hc-question').querySelector('input[name="hc-answer"]:checked');
        if(!chosen){report('Choose an answer before checking.');return}
        report('');busy=true;
        const submit=get('hc-question').querySelector('.hc-submit');submit.disabled=true;
        try{
            const response=await fetch(checkUrl,{
                method:'POST',credentials:'same-origin',
                headers:{'Content-Type':'application/json','Accept':'application/json',
                         'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                body:JSON.stringify({row:q.row,question:q.question,answer:Number(chosen.value)})
            });
            const data=await response.json().catch(()=>({message:'The server returned an invalid response.'}));
            if(!response.ok)throw new Error(data.message||'Unable to check this answer.');
            if(data.correct)score++;
            const answer=get('hc-feedback');
            answer.hidden=false;
            answer.textContent=(data.correct?'✓ Correct. ':'✕ Not quite. Correct answer: '+(data.answer||'')+'. ')+
                (data.feedback||'');
            get('hc-question').querySelectorAll('input[name="hc-answer"]').forEach(input=>input.disabled=true);
            get('hc-next').hidden=current>=questions.length-1;
            get('hc-finish').hidden=current<questions.length-1;
            if(current===questions.length-1){
                answer.textContent+=' Your score: '+score+' / '+questions.length+'.';
            }
        }catch(error){report(error.message);submit.disabled=false}
        finally{busy=false}
    });
}
root.addEventListener('click',async event=>{
    const button=event.target.closest('[data-hc-start]');
    if(!button)return;
    report('');button.disabled=true;button.setAttribute('aria-busy','true');
    try{
        const response=await fetch(button.dataset.playUrl,{
            credentials:'same-origin',headers:{'Accept':'application/json'}
        });
        const data=await response.json().catch(()=>({message:'Invalid campaign response.'}));
        if(!response.ok)throw new Error(data.message||'Unable to open this campaign.');
        if(!Array.isArray(data.questions)||!data.questions.length)throw new Error('No questions are available yet.');
        questions=data.questions;current=0;score=0;checkUrl=button.dataset.checkUrl;
        get('hc-title').textContent=data.title||'Interactive practice';
        chooser.hidden=true;player.hidden=false;question();
    }catch(error){report(error.message)}
    finally{button.disabled=false;button.removeAttribute('aria-busy')}
});
get('hc-back').addEventListener('click',back);
get('hc-finish').addEventListener('click',back);
get('hc-next').addEventListener('click',()=>{if(current<questions.length-1){current++;question();}});
})();
