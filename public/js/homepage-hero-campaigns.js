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
    get('hc-question').innerHTML=
        (q.body?'<div class="hc-instruction">'+q.body+'</div>':'')+
        '<p class="hc-prompt">Q'+(current+1)+'. '+safe(q.prompt)+'</p>'+
        '<form id="hc-answer-form"><fieldset><legend class="sr-only">Select the correct answer</legend>'+
        options+'</fieldset>'+
        '<button type="submit" class="hc-submit mt-3">Check answer</button></form>';
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
