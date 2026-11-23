(() => {
'use strict';
const root = document.getElementById('cs-builder');
if (!root) return;

const el = id => document.getElementById(id);
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, chr =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[chr]));
const extract = (text, fallback) => {
    try { return JSON.parse(text) || fallback; } catch { return fallback; }
};
const initialRows = extract(el('cs-initial-rows')?.textContent || '[]', []);
const optionsDefault = () => ['Option A', 'Option B', 'Option C', 'Option D'];
const blankQuestion = () => ({prompt:'',options:optionsDefault(),correct:0,feedback:''});
const blankLesson = () => ({
    uid: makeId(), title:'New lesson', type:'quiz', url:'', asset:'', body:'',
    language:'', feedback:'', questions:[blankQuestion()]
});
const blankModule = () => ({uid:makeId(),title:'New module',lessons:[blankLesson()]});
const makeId = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    return 'cs-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
};

const knownTypes = ['text','html','emoji','code','youtube','image','video','quiz','exam'];
const normalizeLesson = r => ({
    uid: makeId(),
    title:String(r.lesson || 'Untitled lesson'),
    type: knownTypes.includes(r.type) ? r.type : 'text',
    body:String(r.body || ''),
    url:String(r.url || ''),
    asset:String(r.asset || ''),
    language:String(r.language || ''),
    feedback:String(r.feedback || ''),
    questions:Array.isArray(r.questions) ? r.questions.map(q => ({
        prompt:String(q.prompt || ''),
        options:Array.isArray(q.options) && q.options.length >= 2
            ? q.options.map(o => String(o)) : optionsDefault(),
        correct:Number.isInteger(Number(q.correct)) ? Number(q.correct) : 0,
        feedback:String(q.feedback || '')
    })) : []
});
let modules = [];
if (Array.isArray(initialRows)) {
    for (const row of initialRows) {
        if (!row || typeof row !== 'object') continue;
        const title = String(row.course || 'Untitled module');
        let target = modules[modules.length - 1];
        if (!target || target.title !== title) {
            target = {uid:makeId(), title:title, lessons:[]};
            modules.push(target);
        }
        target.lessons.push(normalizeLesson(row));
    }
}
if (!modules.length) modules = [blankModule()];

let version = Number(root.dataset.version || 0);
let coverAsset = root.dataset.coverAsset || '';
let coverKind = root.dataset.coverKind || 'quiz';
const moduleOpen = new Map();
const lessonOpen = new Map();
const buttons = (action, label, title) => (
    '<button type="button" class="cs-btn cs-btn-quiet cs-btn-xs" data-action="' +
    action + '" aria-label="' + escapeHtml(label) + '" title="' + escapeHtml(label) + '">' + title + '</button>'
);
const input = (field, value, max, extra) => (
    '<input class="cs-input" data-field="' + field + '" maxlength="' + max +
    '" value="' + escapeHtml(value) + '" ' + (extra || '') + '>'
);
const readSourceHtml = html => {
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
    doc.querySelectorAll('script,style,iframe,object,embed,svg,math,form,input,button,link,meta,base').forEach(n => n.remove());
    const allowed = new Set(['P','DIV','BR','STRONG','B','EM','I','U','S','H2','H3','H4',
        'BLOCKQUOTE','PRE','CODE','UL','OL','LI','HR','A','TABLE','THEAD','TBODY','TR','TH','TD',
        'SPAN','SUP','SUB','FIGURE','FIGCAPTION']);
    [...doc.body.querySelectorAll('*')].forEach(n => {
        const href = n.tagName === 'A' ? n.getAttribute('href') : null;
        for (const a of [...n.attributes]) n.removeAttribute(a.name);
        if (n.tagName === 'A' && href && /^(https?:\/\/|mailto:)/i.test(href)) {
            n.setAttribute('href', href);
            n.setAttribute('rel', 'noopener noreferrer');
        }
        if (!allowed.has(n.tagName)) n.replaceWith(...n.childNodes);
    });
    return doc.body.innerHTML;
};
const editor = l => (
    '<div class="cs-editor-box">' +
    '<div class="cs-editor-toolbar" role="toolbar" aria-label="Text formatting">' +
    ['bold','italic','underline','insertUnorderedList','insertOrderedList','formatBlock','createLink',
    'emoji','html'].map(action => {
        const name = {'bold':'B','italic':'I','underline':'U','insertUnorderedList':'• List',
        'insertOrderedList':'1. List','formatBlock':'H2','createLink':'Link',
        'emoji':'😊','html':'HTML'}[action];
        return '<button type="button" data-format="' + action + '" aria-label="' +
            escapeHtml(action) + '">' + name + '</button>';
    }).join('') +
    '</div><div class="cs-editor" contenteditable="true" data-editor data-placeholder="Write lesson content, instructions, or rich text…" role="textbox" aria-multiline="true">' +
    readSourceHtml(l.body) + '</div>' +
    '<textarea class="cs-editor-html" data-editor-source aria-label="Lesson HTML source" hidden></textarea></div>'
);
const questionCard = (q, idx) => (
    '<div class="cs-quiz-card" data-question-index="' + idx + '">' +
    '<div class="cs-question-head"><span class="cs-question-number" data-question-number>Q</span>' +
    '<strong>Question ' + (idx+1) + '</strong>' +
    buttons('move-question-up','Move question up','↑') +
    buttons('move-question-down','Move question down','↓') +
    buttons('remove-question','Remove question','✕') + '</div>' +
    '<label class="cs-label">Question prompt</label>' +
    '<textarea class="cs-textarea" data-question-field="prompt" rows="2" maxlength="1000" placeholder="Write a clear question">' +
    escapeHtml(q.prompt) + '</textarea>' +
    '<label class="cs-label">Answer choices · choose the correct option</label>' +
    '<div class="cs-options">' +
    q.options.map((o,i) =>
        '<label class="cs-option"><input type="radio" data-question-correct name="cs-correct-' +
        idx + '-' + escapeHtml(q._uid || (q._uid=makeId())) + '" value="' + i +
        '"' + (Number(q.correct)===i?' checked':'') +
        ' aria-label="Correct choice ' + (i+1) + '">' +
        '<input class="cs-input" data-option-index="' + i + '" value="' +
        escapeHtml(o) + '" maxlength="500" aria-label="Answer choice ' + (i+1) + '"></label>'
    ).join('') + '</div>' +
    '<div class="cs-actions" style="justify-content:flex-start">' +
    buttons('add-choice','Add answer choice','＋ Choice') +
    buttons('remove-choice','Remove last answer choice','− Choice') + '</div>' +
    '<label class="cs-label">Answer explanation &amp; feedback</label>' +
    '<textarea class="cs-textarea" data-question-field="feedback" maxlength="1000" rows="2" placeholder="Explain why this answer is correct. This appears after checking an answer.">' +
    escapeHtml(q.feedback) + '</textarea>' +
    '</div>'
);
const lessonCard = (lesson, mi, li) => {
    const isQuiz = lesson.type === 'quiz' || lesson.type === 'exam';
    const isMedia = lesson.type === 'image' || lesson.type === 'video';
    const showAsset = lesson.asset && /^(?:[a-f0-9]{64})\.(?:jpg|png|webp|mp4|webm)$/.test(lesson.asset);
    const assetURL = showAsset ? root.dataset.assetBase.replace(/\/$/,'') + '/' + encodeURIComponent(lesson.asset) : '';
    const preview = showAsset
        ? (/\.(?:mp4|webm)$/.test(lesson.asset)
            ? '<video class="cs-media-preview" controls preload="none" src="' + escapeHtml(assetURL) + '"></video>'
            : '<img class="cs-media-preview" loading="lazy" src="' + escapeHtml(assetURL) + '" alt="Lesson media preview">')
        : '';
    return '<details class="cs-lesson" data-lesson-index="' + li + '" data-lesson-id="' +
        escapeHtml(lesson.uid) + '"' + (lessonOpen.get(lesson.uid)!==false?' open':'') + '>' +
        '<summary><span class="cs-lesson-number">' + (mi+1) + '.' + (li+1) + '</span>' +
        '<span class="cs-summary-copy"><span class="cs-summary-title" data-lesson-heading>' +
        escapeHtml(lesson.title || 'Untitled lesson') + '</span><span class="cs-summary-subtitle">' +
        escapeHtml(lesson.type.replace(/_/g,' ').toUpperCase()) +
        '</span></span><span class="cs-chevron">⌄</span></summary>' +
        '<div class="cs-lesson-body"><div class="cs-actions">' +
        buttons('lesson-up','Move lesson up','↑') +
        buttons('lesson-down','Move lesson down','↓') +
        buttons('lesson-duplicate','Duplicate lesson','Duplicate') +
        buttons('lesson-remove','Remove lesson','Remove') +
        '</div><div class="cs-field-grid"><div><label class="cs-label">Lesson title</label>' +
        input('title',lesson.title,140) + '</div><div><label class="cs-label">Lesson type</label>' +
        '<select class="cs-control" data-field="type">' +
        [['quiz','Quiz'],['exam','Exam'],['text','Text'],['html','Rich HTML'],['emoji','Emoji'],['code','Code'],
        ['image','Image'],['video','Video'],['youtube','YouTube video']].map(([v,n]) =>
            '<option value="' + v + '"' + (lesson.type===v?' selected':'') + '>' + n + '</option>').join('') +
        '</select></div></div>' +
        '<label class="cs-label">Lesson text editor</label>' + editor(lesson) +
        '<div class="cs-lesson-media"><strong style="font-size:12px">Lesson media</strong>' +
        '<p class="cs-muted">Add optional image or video media. The featured cover is configured separately in the right sidebar.</p>' +
        '<div class="cs-media-toolbar"><label class="cs-btn cs-btn-outline cs-btn-xs">＋ Upload media' +
        '<input type="file" data-lesson-file accept="image/png,image/jpeg,image/webp,video/mp4,video/webm" hidden></label>' +
        '<button type="button" class="cs-btn cs-btn-quiet cs-btn-xs" data-action="remove-media">Remove media</button></div>' +
        (preview || '') +
        '<label class="cs-label">' + (lesson.type === 'youtube' ? 'YouTube HTTPS URL' : 'Media URL (optional)') + '</label>' +
        input('url',lesson.url,2048,'placeholder="https://..."') +
        '</div>' +
        (isQuiz
            ? '<div class="cs-questions" data-questions><div class="cs-section-heading" style="margin-top:17px">' +
              '<div><h2 style="font-size:14px">Questions &amp; answers</h2>' +
              '<p>Numbering is automatic, including across lessons.</p></div>' +
              '<button type="button" class="cs-btn cs-btn-outline cs-btn-xs" data-action="add-question">＋ Question</button></div>' +
              '<div data-question-list>' +
              lesson.questions.map((q,index)=>questionCard(q,index)).join('') +
              '</div></div>'
            : '<p class="cs-muted" style="margin-top:12px">Select Quiz or Exam to add questions and answer choices.</p>') +
        '<label class="cs-label">Lesson feedback or takeaway (optional)</label>' +
        '<textarea class="cs-textarea" data-field="feedback" maxlength="1000" rows="2" placeholder="Key learning takeaway…">' +
        escapeHtml(lesson.feedback) + '</textarea></div></details>';
};
const moduleCard = (module, mi) => (
    '<details class="cs-module" data-module-index="' + mi + '" data-module-id="' +
    escapeHtml(module.uid) + '"' + (moduleOpen.get(module.uid)!==false?' open':'') + '>' +
    '<summary><span class="cs-module-number">' + String(mi+1).padStart(2,'0') +
    '</span><span class="cs-summary-copy"><span class="cs-summary-title" data-module-heading>' +
    escapeHtml(module.title || 'Untitled module') + '</span><span class="cs-summary-subtitle">' +
    module.lessons.length + ' lesson' + (module.lessons.length!==1?'s':'') +
    '</span></span><span class="cs-chevron">⌄</span></summary>' +
    '<div class="cs-module-body"><div class="cs-field-grid">' +
    '<div><label class="cs-label">Module title</label>' + input('module-title',module.title,140) +
    '</div><div class="cs-actions">' + buttons('module-up','Move module up','↑') +
    buttons('module-down','Move module down','↓') +
    buttons('module-remove','Remove module','Remove') +
    '</div></div>' +
    '<div class="cs-lesson-list">' +
    module.lessons.map((lesson,li)=>lessonCard(lesson,mi,li)).join('') +
    '</div><button type="button" class="cs-btn cs-btn-outline cs-full" data-action="add-lesson">＋ Add lesson</button>' +
    '</div></details>'
);
function renderOutline() {
    const outline=el('cs-outline');
    outline.innerHTML = modules.map((m,i) =>
        '<div class="cs-outline-item"><strong>' + String(i+1).padStart(2,'0') +
        ' · ' + escapeHtml(m.title) + '</strong>' +
        m.lessons.map((lesson,li)=>'<div>' + (i+1) + '.' + (li+1) + ' ' +
        escapeHtml(lesson.title) + '</div>').join('') + '</div>').join('');
}
function updateStats() {
    el('cs-modules-count').textContent = modules.length;
    el('cs-lessons-count').textContent = modules.reduce((n,m)=>n+m.lessons.length,0);
    el('cs-questions-count').textContent = modules.reduce((n,m)=>n+m.lessons.reduce((x,l)=>
        x+(l.type==='quiz'||l.type==='exam'?l.questions.length:0),0),0);
    renderOutline();
}
function render() {
    el('cs-modules').innerHTML = modules.map(moduleCard).join('');
    let n=0;
    el('cs-modules').querySelectorAll('[data-question-number]').forEach(node=>{
        node.textContent='Q'+(++n);
    });
    updateStats();
}
function note(text,isError) {
    const box=el('cs-alert');
    box.hidden=!isError;
    box.textContent=isError?text:'';
    el('cs-save-state').textContent=text;
}
function apiError(response,text) {
    if(response.ok) return;
    let message=text.message || text.error || 'An unexpected server error occurred.';
    if(text.errors && typeof text.errors==='object'){
        const errors=Object.values(text.errors).flat();
        if(errors.length) message=errors[0];
    }
    throw new Error(message);
}
async function requestJSON(url, body) {
    const response=await fetch(url,{
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,
                 'X-Requested-With':'XMLHttpRequest'},
        body:JSON.stringify(body)
    });
    const data=await response.json().catch(()=>({message:'Server returned an invalid response.'}));
    apiError(response,data);
    return data;
}
function exportRows() {
    const rows=[];
    for(const module of modules) {
        for(const l of module.lessons) {
            const isQuiz=l.type==='quiz'||l.type==='exam';
            const row={
                course:module.title.trim().slice(0,140) || 'Untitled module',
                lesson:l.title.trim().slice(0,140) || 'Untitled lesson',
                type:l.type,
                body:readSourceHtml(l.body).slice(0,20000),
                url:l.url.trim().slice(0,2048),
                asset:l.asset || '',
                language:l.language || '',
                feedback:l.feedback.trim().slice(0,1000),
                questions:isQuiz?l.questions.map(q=>({
                    prompt:q.prompt.trim().slice(0,1000),
                    options:q.options.map(o=>String(o).slice(0,500)),
                    correct:Number(q.correct),
                    feedback:q.feedback.trim().slice(0,1000)
                })):[]
            };
            // Existing controller only allows media/image-backed assets already uploaded.
            if (!row.asset) delete row.asset;
            rows.push(row);
        }
    }
    return rows;
}
let busy=false;
async function save(action) {
    if(busy) return;
    if(!modules.length) return note('Add at least one module before saving.',true);
    const rows=exportRows();
    if(action==='publish' && !rows.length) return note('Add at least one lesson before publishing.',true);
    busy=true;
    note(action==='publish'?'Publishing…':'Saving draft…',false);
    root.querySelectorAll('#cs-save-draft,#cs-publish,#cs-save-draft-top,#cs-publish-top').forEach(b=>b.disabled=true);
    try {
        const response=await requestJSON(root.dataset.saveUrl,{action,version,rows});
        version=Number(response.version);
        root.dataset.version=String(version);
        note(response.message || (action==='publish'?'Campaign published.':'Draft saved.'),false);
    }catch(error){note(error.message,true)}
    finally {
        busy=false;
        root.querySelectorAll('#cs-save-draft,#cs-publish,#cs-save-draft-top,#cs-publish-top').forEach(b=>b.disabled=false);
    }
}
async function uploadFile(file) {
    const body=new FormData();
    body.append('file',file);
    const response=await fetch(root.dataset.uploadUrl,{
        method:'POST',credentials:'same-origin',
        headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf,
            'X-Requested-With':'XMLHttpRequest'},body
    });
    const data=await response.json().catch(()=>({message:'Invalid upload response.'}));
    apiError(response,data);
    return data;
}
async function saveCover() {
    await requestJSON(root.dataset.coverSaveUrl,{asset:coverAsset || null,kind:coverKind});
}
function previewCover() {
    const img=el('cs-cover-image');
    img.hidden=!coverAsset;
    el('cs-cover-placeholder').hidden=!!coverAsset;
    if(coverAsset) img.src=root.dataset.assetBase.replace(/\/$/,'')+'/'+encodeURIComponent(coverAsset);
    el('cs-remove-cover').hidden=!coverAsset;
}
function inspectImage(file) {
    return new Promise((resolve,reject)=>{
        const url=URL.createObjectURL(file), image=new Image();
        image.onload=()=>{const dimensions={width:image.naturalWidth,height:image.naturalHeight};URL.revokeObjectURL(url);resolve(dimensions)};
        image.onerror=()=>{URL.revokeObjectURL(url);reject(new Error('Unable to read the selected image.'))};
        image.src=url;
    });
}
el('cs-cover-file').addEventListener('change',async event=>{
    const file=event.target.files?.[0];
    if(!file) return;
    const status=el('cs-cover-message');
    status.textContent='Verifying cover…';
    try {
        const d=await inspectImage(file);
        if(d.width!==1920 || d.height!==1080) throw new Error(
            'Cover must be 1920 × 1080 pixels. Selected: '+d.width+' × '+d.height+'.');
        status.textContent='Uploading featured cover…';
        const media=await uploadFile(file);
        if(media.type!=='image') throw new Error('Choose a JPG, PNG, or WebP image.');
        const previous=coverAsset;
        coverAsset=media.asset;
        try {await saveCover()} catch(error){coverAsset=previous;throw error}
        previewCover();
        status.textContent='Featured cover uploaded and saved.';
    } catch(error) {status.textContent=error.message;note(error.message,true)}
    event.target.value='';
});
el('cs-cover-kind').addEventListener('change',async event=>{
    coverKind=event.target.value;
    if(!coverAsset)return;
    try{await saveCover();el('cs-cover-message').textContent='Featured type saved.'}
    catch(error){note(error.message,true)}
});
el('cs-remove-cover').addEventListener('click',async ()=>{
    if(!window.confirm('Remove the campaign featured cover?'))return;
    const old=coverAsset;
    coverAsset='';
    try{await saveCover();previewCover();el('cs-cover-message').textContent='Featured cover removed.'}
    catch(error){coverAsset=old;note(error.message,true)}
});
el('cs-save-draft').addEventListener('click',()=>save('draft'));
el('cs-save-draft-top').addEventListener('click',()=>save('draft'));
el('cs-publish').addEventListener('click',()=>save('publish'));
el('cs-publish-top').addEventListener('click',()=>save('publish'));
el('cs-add-module').addEventListener('click',()=>{
    modules.push(blankModule());render();note('Module added. Save draft to keep changes.',false);
});

const list=el('cs-modules');
list.addEventListener('toggle', event=>{
    const box=event.target;
    if(!(box instanceof HTMLDetailsElement))return;
    if(box.dataset.moduleId)moduleOpen.set(box.dataset.moduleId,box.open);
    if(box.dataset.lessonId)lessonOpen.set(box.dataset.lessonId,box.open);
},true);
function coords(target) {
    const mod=target.closest('[data-module-index]');
    const les=target.closest('[data-lesson-index]');
    const question=target.closest('[data-question-index]');
    return {
        mi:mod?Number(mod.dataset.moduleIndex):-1,
        li:les?Number(les.dataset.lessonIndex):-1,
        qi:question?Number(question.dataset.questionIndex):-1
    };
}
function getEntities(pos) {
    const module=modules[pos.mi];
    const lesson=pos.li>=0 ? module?.lessons[pos.li]:undefined;
    const question=pos.qi>=0 ? lesson?.questions[pos.qi]:undefined;
    return {module,lesson,question};
}
function mutateFromInput(target) {
    const pos=coords(target);
    const {module,lesson,question}=getEntities(pos);
    const field=target.dataset.field;
    const questionField=target.dataset.questionField;
    if(target.dataset.optionIndex!==undefined && question){
        question.options[Number(target.dataset.optionIndex)]=target.value;
    }else if(target.hasAttribute('data-question-correct') && question){
        question.correct=Number(target.value);
    }else if(questionField && question){
        question[questionField]=target.value;
    }else if(field==='module-title' && module){
        module.title=target.value;
        const heading=target.closest('[data-module-index]')?.querySelector('[data-module-heading]');
        if(heading)heading.textContent=module.title||'Untitled module';
    }else if(field && lesson){
        lesson[field]=target.value;
        if(field==='title'){
            const heading=target.closest('[data-lesson-index]')?.querySelector('[data-lesson-heading]');
            if(heading)heading.textContent=lesson.title||'Untitled lesson';
        }
    }else if(target.matches('[data-editor]') && lesson){
        lesson.body=readSourceHtml(target.innerHTML);
    }else if(target.matches('[data-editor-source]') && lesson){
        lesson.body=readSourceHtml(target.value);
    }
    if(field==='module-title'||field==='title')renderOutline();
}
list.addEventListener('input',event=>mutateFromInput(event.target));
list.addEventListener('change',event=>{
    mutateFromInput(event.target);
    if(event.target.matches('[data-field="type"]')){
        const {lesson}=getEntities(coords(event.target));
        if(lesson && (lesson.type==='quiz'||lesson.type==='exam') && lesson.questions.length===0)
            lesson.questions=[blankQuestion()];
        render();
    }
});
list.addEventListener('click',event=>{
    const formatButton=event.target.closest('[data-format]');
    if(formatButton){
        const wrapper=formatButton.closest('.cs-editor-box');
        const visual=wrapper.querySelector('[data-editor]');
        const source=wrapper.querySelector('[data-editor-source]');
        const action=formatButton.dataset.format;
        const lesson=getEntities(coords(formatButton)).lesson;
        if(action==='html') {
            if(source.hidden){
                source.value=readSourceHtml(visual.innerHTML);
                source.hidden=false;visual.hidden=true;
            }else{
                const safe=readSourceHtml(source.value);
                visual.innerHTML=safe;
                if(lesson)lesson.body=safe;
                source.hidden=true;visual.hidden=false;
            }
            return;
        }
        if(!source.hidden){note('Switch back to Visual editor before using formatting tools.',true);return;}
        visual.focus();
        if(action==='emoji'){document.execCommand('insertText',false,'😊');}
        else if(action==='formatBlock'){document.execCommand('formatBlock',false,'h2');}
        else if(action==='createLink'){
            const url=window.prompt('Enter a secure HTTPS link');
            if(url && /^https:\/\//i.test(url))document.execCommand('createLink',false,url);
        }else{document.execCommand(action,false,null)}
        if(lesson)lesson.body=readSourceHtml(visual.innerHTML);
        return;
    }
    const button=event.target.closest('button[data-action]');
    if(!button)return;
    const action=button.dataset.action, pos=coords(button), entity=getEntities(pos);
    const m=entity.module,l=entity.lesson,q=entity.question;
    if(action==='module-up' && pos.mi>0) [modules[pos.mi-1],modules[pos.mi]]=[modules[pos.mi],modules[pos.mi-1]];
    else if(action==='module-down' && pos.mi<modules.length-1)
        [modules[pos.mi+1],modules[pos.mi]]=[modules[pos.mi],modules[pos.mi+1]];
    else if(action==='module-remove'){
        if(!window.confirm('Remove this module and all its lessons?'))return;
        modules.splice(pos.mi,1);
    }else if(action==='add-lesson'){m.lessons.push(blankLesson());moduleOpen.set(m.uid,true)}
    else if(action==='lesson-up' && pos.li>0) [m.lessons[pos.li-1],m.lessons[pos.li]]=[m.lessons[pos.li],m.lessons[pos.li-1]];
    else if(action==='lesson-down' && pos.li<m.lessons.length-1)
        [m.lessons[pos.li+1],m.lessons[pos.li]]=[m.lessons[pos.li],m.lessons[pos.li+1]];
    else if(action==='lesson-duplicate'){
        const dup=structuredClone(l);
        dup.uid=makeId();
        m.lessons.splice(pos.li+1,0,dup);
    }else if(action==='lesson-remove'){
        if(!window.confirm('Remove this lesson?'))return;
        m.lessons.splice(pos.li,1);
    }else if(action==='add-question'){l.questions.push(blankQuestion())}
    else if(action==='remove-question'){
        if(!window.confirm('Remove this question?'))return;
        l.questions.splice(pos.qi,1);
    }else if(action==='move-question-up' && pos.qi>0)
        [l.questions[pos.qi-1],l.questions[pos.qi]]=[l.questions[pos.qi],l.questions[pos.qi-1]];
    else if(action==='move-question-down' && pos.qi<l.questions.length-1)
        [l.questions[pos.qi+1],l.questions[pos.qi]]=[l.questions[pos.qi],l.questions[pos.qi+1]];
    else if(action==='add-choice' && q && q.options.length<6)q.options.push('New choice');
    else if(action==='remove-choice' && q && q.options.length>2){
        q.options.pop();
        q.correct=Math.min(Number(q.correct),q.options.length-1);
    }else if(action==='remove-media'){
        l.asset='';l.url='';
    }else return;
    render();
    note('Unsaved changes. Save draft or Publish when ready.',false);
});
list.addEventListener('change',async event=>{
    const field=event.target;
    if(!field.matches('[data-lesson-file]'))return;
    const file=field.files?.[0];
    if(!file)return;
    const {lesson}=getEntities(coords(field));
    if(!lesson)return;
    note('Uploading lesson media…',false);
    try{
        const data=await uploadFile(file);
        lesson.asset=data.asset;
        lesson.url=data.url;
        if(lesson.type==='image'||lesson.type==='video')lesson.type=data.type;
        render();
        note('Lesson media uploaded. Save draft to keep this attachment.',false);
    }catch(error){note(error.message,true)}
    field.value='';
});
render();
})();
