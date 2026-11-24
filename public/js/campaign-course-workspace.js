(() => {
'use strict';
const root = document.getElementById('cs-builder');
if (!root || !root.classList.contains('cs-course-workspace')) return;
const list = document.getElementById('cs-modules');
const tree = document.getElementById('cs-course-tree');
const byId = id => document.getElementById(id);
if (!list || !tree) return;
// New campaigns start with required metadata; existing campaigns open the current lesson.
let activeModule = 0, activeLesson = 0, activeTab = 'metadata';
let pendingSelection = null, dragSource = null, scheduled = false;
const collapsedUnits = new Set();
const escaped = value => String(value ?? '').replace(/[&<>"']/g, x =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));
const modules = () => [...list.querySelectorAll(':scope > .cs-module')];
const lessons = mod => mod ? [...mod.querySelectorAll(':scope > .cs-module-body > .cs-lesson-list > .cs-lesson')] : [];
const moduleAt = i => modules()[i];
const lessonAt = (i,j) => lessons(moduleAt(i))[j];
const titleOf = element => (element?.querySelector(':scope > summary [data-module-heading],:scope > summary [data-lesson-heading]')?.textContent || '').trim();
const lessonType = element => element?.querySelector('select[data-field="type"]')?.value || 'text';

function makePanel(name, title) {
    const panel = document.createElement('section');
    panel.className = 'cs-course-tab-panel';
    panel.dataset.csPanel = name;
    panel.setAttribute('aria-label', title);
    const header = document.createElement('div');
    header.className = 'cs-form-heading';
    const heading = document.createElement('h3');
    heading.textContent = title;
    header.appendChild(heading);
    panel.appendChild(header);
    return panel;
}
function decorateLesson(lesson) {
    if (lesson.dataset.csAdapted === 'yes') return;
    const body = lesson.querySelector(':scope > .cs-lesson-body');
    if (!body) return;
    const panels = {
        metadata: makePanel('metadata', 'Lesson metadata & CEFR'),
        curriculum: makePanel('curriculum', 'Lesson content and media'),
        assessments: makePanel('assessments', 'Interactive practice & quizzes'),
        translation: makePanel('translation', 'English / Khmer translation')
    };
    for (const child of [...body.children]) {
        if (child.classList.contains('cs-actions') ||
            child.classList.contains('cs-field-grid') ||
            child.classList.contains('cs-course-cefr-field')) {
            panels.metadata.appendChild(child);
        } else if (child.classList.contains('cs-questions') ||
            (child.matches('p.cs-muted') && child.textContent.includes('Select Quiz or Exam'))) {
            panels.assessments.appendChild(child);
        } else if (child.classList.contains('cs-course-translation-fields')) {
            panels.translation.appendChild(child);
        } else {
            panels.curriculum.appendChild(child);
        }
    }
    const hint = document.createElement('p');
    hint.className = 'cs-course-type-guidance';
    hint.setAttribute('data-cs-type-hint', '');
    panels.curriculum.insertBefore(hint, panels.curriculum.children[1] || null);
    Object.values(panels).forEach(p => body.appendChild(p));
    lesson.dataset.csAdapted = 'yes';
}
function treeMarkup() {
    return modules().map((mod,mi) => {
        const entries = lessons(mod);
        let html = '<div class="cs-tree-unit" data-tree-module="'+mi+'"><div class="cs-tree-unit-heading">' +
            '<span class="cs-tree-drag" data-cs-drag-module="'+mi+'" draggable="true" title="Drag to reorder unit" aria-label="Drag unit '+(mi+1)+'">⠿</span>' +
            '<button type="button" class="cs-tree-collapse" data-cs-toggle-unit="'+mi+'" aria-expanded="'+(!collapsedUnits.has(mi))+'" aria-label="Toggle Unit '+(mi+1)+' lessons">'+(collapsedUnits.has(mi)?'▸':'⌄')+'</button>' +
            '<button type="button" class="cs-tree-unit-button" data-cs-select-module="'+mi+'">' +
            '<span class="cs-tree-folder" aria-hidden="true">▣</span><span class="cs-tree-unit-name">Unit '+(mi+1)+': '+escaped(titleOf(mod)||'Untitled unit')+'</span></button>' +
            '<button type="button" class="cs-tree-rename" data-cs-rename-module="'+mi+'" title="Rename module" aria-label="Rename unit '+(mi+1)+'">' +
            '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5 4 4M4 20l4.5-1 11-11a2.8 2.8 0 0 0-4-4l-11 11L4 20Z"/></svg></button>' +
            '<button type="button" class="cs-tree-add" data-cs-add-lesson="'+mi+'" title="Add lesson" aria-label="Add lesson to unit '+(mi+1)+'">＋</button></div>' +
            '<div class="cs-tree-lessons"'+(collapsedUnits.has(mi)?' hidden':'')+'>';
        entries.forEach((lesson,li)=>{
            const selected = mi===activeModule && li===activeLesson;
            html += '<div class="cs-tree-lesson'+(selected?' is-active':'')+'" data-tree-lesson="'+mi+'-'+li+'">' +
                '<span class="cs-tree-drag" data-cs-drag-lesson="'+mi+'-'+li+'" draggable="true" title="Drag to reorder lesson" aria-label="Drag lesson '+(mi+1)+'.'+(li+1)+'">⠿</span>' +
                '<button type="button" class="cs-tree-lesson-button" data-cs-select-lesson="'+mi+'-'+li+'"'+(selected?' aria-current="true"':'')+'>' +
                '<span class="cs-tree-lesson-title">Lesson '+(mi+1)+'.'+(li+1)+': '+escaped(titleOf(lesson)||'Untitled lesson')+'</span>' +
                '<span class="cs-tree-type">'+escaped(lessonType(lesson))+'</span></button></div>';
        });
        return html+(!entries.length?'<span class="cs-tree-empty">No lessons. Use + to add one.</span>':'')+'</div></div>';
    }).join('');
}
function trustedPreviewUrl(raw) {
    try {
        const address=new URL(String(raw||''),location.href);
        const trustedLocal=address.origin===location.origin && address.pathname.startsWith('/campaign-media/');
        return address.protocol==='https:' || trustedLocal ? address : null;
    }catch(_error){return null}
}
function youtubeVideoId(url) {
    if(!url)return null;
    const host=url.hostname.toLowerCase();
    if(!['youtube.com','www.youtube.com','m.youtube.com','youtube-nocookie.com',
        'www.youtube-nocookie.com','youtu.be','www.youtu.be'].includes(host))return null;
    let id='';
    if(host.endsWith('youtu.be')) id=url.pathname.slice(1).split('/')[0];
    else if(url.pathname==='/watch') id=url.searchParams.get('v')||'';
    else id=url.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})(?:\/|$)/)?.[1]||'';
    return /^[A-Za-z0-9_-]{11}$/.test(id)?id:null;
}
function showActiveMedia() {
    const selected=lessonAt(activeModule,activeLesson);
    for(const box of list.querySelectorAll('[data-cs-lesson-media-preview]')) {
        const isActive=box.closest('.cs-lesson')===selected && activeTab==='curriculum';
        if(!isActive){
            if(box.firstChild)box.replaceChildren();
            box.hidden=true;
            box.removeAttribute('data-preview-key');
            continue;
        }
        const lesson=selected;
        const kind=lessonType(lesson);
        const asset=lesson.dataset.lessonAsset||'';
        const url=(lesson.querySelector('[data-field="url"]')?.value||'').trim();
        const key=kind+'|'+asset+'|'+url;
        // Keep walking the other lessons: their old iframe/video must be unloaded.
        if(box.dataset.previewKey===key)continue;
        box.dataset.previewKey=key;
        box.replaceChildren();
        box.hidden=true;

        let resolved=null;
        if(/^[a-f0-9]{64}\.(?:jpg|png|webp|mp4|webm)$/.test(asset)){
            resolved=trustedPreviewUrl(root.dataset.assetBase.replace(/\/$/,'')+'/'+encodeURIComponent(asset));
        }else if(url){
            resolved=trustedPreviewUrl(url);
        }
        if(!resolved){
            if(url){
                const hint=document.createElement('p');
                hint.className='cs-media-error';
                hint.textContent='Preview unavailable. Use a valid HTTPS media URL.';
                box.appendChild(hint);
                box.hidden=false;
            }
            continue;
        }
        const mediaType=asset?(/\.(mp4|webm)$/.test(asset)?'video':'image'):
            youtubeVideoId(resolved)?'youtube':
            kind==='image'?'image':
            kind==='video'?'video':
            /\.(jpg|jpeg|png|webp|gif|avif)$/i.test(resolved.pathname)?'image':
            /\.(mp4|webm|ogg|mov)$/i.test(resolved.pathname)?'video':
            /\.(mp3|wav|m4a|oga)$/i.test(resolved.pathname)?'audio':'link';
        const caption=document.createElement('div');
        caption.className='cs-media-preview-heading';
        caption.textContent='Lesson media preview';
        box.appendChild(caption);
        let node;
        if(mediaType==='youtube') {
            node=document.createElement('iframe');
            node.src='https://www.youtube-nocookie.com/embed/'+youtubeVideoId(resolved);
            node.title='YouTube lesson video preview';
            node.loading='lazy';
            node.referrerPolicy='strict-origin-when-cross-origin';
            node.setAttribute('sandbox','allow-scripts allow-same-origin allow-presentation');
            node.setAttribute('allow','encrypted-media; picture-in-picture; fullscreen');
            node.setAttribute('allowfullscreen','');
        }else if(mediaType==='image') {
            node=document.createElement('img');
            node.src=resolved.href;
            node.alt='Selected lesson image preview';
            node.loading='lazy';
        }else if(mediaType==='video') {
            node=document.createElement('video');
            node.src=resolved.href;
            node.controls=true;
            node.preload='metadata';
            node.setAttribute('playsinline','');
        }else if(mediaType==='audio') {
            node=document.createElement('audio');
            node.src=resolved.href;
            node.controls=true;
            node.preload='none';
        }else {
            node=document.createElement('a');
            node.href=resolved.href;
            node.target='_blank';
            node.rel='noopener noreferrer';
            node.textContent='Open media preview';
        }
        node.className='cs-selected-media-element';
        box.appendChild(node);
        box.hidden=false;
    }
}
function setActivePanel() {
    const unit = moduleAt(activeModule), lesson = lessonAt(activeModule,activeLesson);
    byId('cs-edit-title').textContent = lesson ? titleOf(lesson) : 'Add a lesson';
    byId('cs-edit-subtitle').textContent = unit ?
        (titleOf(unit)||'Untitled unit')+' · '+(lesson?'Edit selected lesson':'Add your first lesson') :
        'Add a unit and lesson to begin.';
    byId('cs-current-lesson-number').textContent = lesson ? 'LESSON '+(activeModule+1)+'.'+(activeLesson+1) : 'NO LESSON';
    byId('cs-current-unit-heading').textContent = unit ? 'Unit '+(activeModule+1)+': '+(titleOf(unit)||'Untitled unit') : 'Course outline';
    byId('cs-current-content-type').textContent = lessonType(lesson).toUpperCase();
    byId('cs-unit-label').textContent = modules().length+' unit'+(modules().length===1?'':'s');
    root.dataset.csTabActive = activeTab;
    const moduleEditor = byId('cs-selected-module-editor');
    const moduleInput = byId('cs-selected-module-title');
    const innerModuleInput = unit?.querySelector(':scope > .cs-module-body > .cs-field-grid [data-field="module-title"]');
    moduleEditor.hidden = !unit || activeTab !== 'metadata';
    if (innerModuleInput && moduleInput.value !== innerModuleInput.value) {
        moduleInput.value = innerModuleInput.value;
    }
    const help = {
        quiz: 'Quiz lesson: add written instructions here, then switch to Assessments for questions, choices, answer keys and feedback.',
        exam: 'Exam lesson: add written instructions here, then switch to Assessments for scored questions and explanations.',
        text: 'Text lesson: use the rich text editor below for paragraphs, headings, lists, tables, links and formatting.',
        html: 'Rich HTML lesson: use the HTML source control in the editor toolbar. Unsafe scripts are removed automatically.',
        emoji: 'Emoji lesson: use the Emoji toolbar control or type emoji directly into the rich text editor.',
        code: 'Code lesson: write code in the dedicated monospaced source editor below.',
        image: 'Image lesson: upload or link an image in the Lesson Media section below.',
        video: 'Video lesson: upload an MP4/WebM file or provide an HTTPS video URL below.',
        youtube: 'YouTube lesson: paste the HTTPS YouTube video URL in Lesson Media below.'
    };
    for (const mod of modules()) {
        const selected = Number(mod.dataset.moduleIndex) === activeModule;
        mod.toggleAttribute('data-cs-active',selected);
        if (selected && !mod.open) mod.open = true;
        for (const l of lessons(mod)) {
            const isActive=selected && Number(l.dataset.lessonIndex)===activeLesson;
            l.toggleAttribute('data-cs-active',isActive);
            if(isActive && !l.open)l.open=true;
            l.querySelectorAll('[data-cs-panel]').forEach(p=>p.hidden=p.dataset.csPanel!==activeTab);
            const hint=l.querySelector('[data-cs-type-hint]');
            if(hint)hint.textContent=help[lessonType(l)]||help.text;
        }
    }
    root.querySelectorAll('[data-cs-tab]').forEach(t=>{
        const selected=t.dataset.csTab===activeTab;
        t.classList.toggle('is-active',selected);
        t.setAttribute('aria-selected',String(selected));
        t.tabIndex=selected?0:-1;
        if(selected)byId('cs-course-stage').setAttribute('aria-labelledby',t.id);
    });
    showActiveMedia();
    const campaignTitle=byId('cs-campaign-title');
    // Campaign title and type are global settings, not lesson-level fields.
    const count=Number(byId('cs-questions-count').textContent||'0');
    const ready=count>0 && !byId('cs-cover-image').hidden && campaignTitle.value.trim();
    const marker=byId('cs-readiness-mark');
    marker.classList.toggle('is-complete',Boolean(ready));
    marker.textContent=ready?'✓':'2';
    byId('cs-readiness-label').textContent=ready?'Questions and cover are ready':'Add a quiz question, title and featured cover';
}
function sync(){
    scheduled=false;
    for(const m of modules())for(const l of lessons(m))decorateLesson(l);
    if(pendingSelection){activeModule=pendingSelection.mi;activeLesson=pendingSelection.li;pendingSelection=null}
    activeModule=Math.min(activeModule,Math.max(0,modules().length-1));
    activeLesson=Math.min(activeLesson,Math.max(0,lessons(moduleAt(activeModule)).length-1));
    tree.innerHTML=treeMarkup();
    setActivePanel();
}
function schedule(){if(scheduled)return;scheduled=true;queueMicrotask(sync)}
function select(mi,li){
    // Unload media from the previously selected lesson *before* changing focus.
    // In particular, this immediately destroys embedded YouTube iframes.
    for(const box of list.querySelectorAll('[data-cs-lesson-media-preview]')){
        if(box.firstChild)box.replaceChildren();
        box.hidden=true;
        box.removeAttribute('data-preview-key');
    }
    if(mediaPreviewTimer!==null){
        clearTimeout(mediaPreviewTimer);
        mediaPreviewTimer=null;
    }
    activeModule=mi;
    activeLesson=li;
    collapsedUnits.delete(mi);
    const kind=lessonType(lessonAt(mi,li));
    activeTab=(kind==='quiz'||kind==='exam')?'assessments':'curriculum';
    sync();
}
function setTab(tab){
    if(!['metadata','curriculum','assessments','translation'].includes(tab))return;
    activeTab=tab;setActivePanel();
}

tree.addEventListener('click',event=>{
    const unit=event.target.closest('[data-cs-select-module]');
    const lesson=event.target.closest('[data-cs-select-lesson]');
    const plus=event.target.closest('[data-cs-add-lesson]');
    const toggle=event.target.closest('[data-cs-toggle-unit]');
    const rename=event.target.closest('[data-cs-rename-module]');
    if(toggle){
        const mi=Number(toggle.dataset.csToggleUnit);
        if(collapsedUnits.has(mi))collapsedUnits.delete(mi);else collapsedUnits.add(mi);
        sync();
    }else if(rename){
        select(Number(rename.dataset.csRenameModule),0);
        setTab('metadata');
        const moduleTitleInput=byId('cs-selected-module-title');
        moduleTitleInput.focus();
        moduleTitleInput.select();
    }else if(plus){
        const mi=Number(plus.dataset.csAddLesson), mod=moduleAt(mi);
        pendingSelection={mi,li:lessons(mod).length};
        collapsedUnits.delete(mi);
        mod?.querySelector(':scope > .cs-module-body > button[data-action="add-lesson"]')?.click();
        schedule();
    }else if(lesson){
        const [mi,li]=lesson.dataset.csSelectLesson.split('-').map(Number);
        select(mi,li);
    }else if(unit)select(Number(unit.dataset.csSelectModule),0);
});
tree.addEventListener('dragstart',event=>{
    const mod=event.target.closest('[data-cs-drag-module]');
    const lesson=event.target.closest('[data-cs-drag-lesson]');
    if(mod)dragSource={type:'module',mi:Number(mod.dataset.csDragModule)};
    else if(lesson){
        const [mi,li]=lesson.dataset.csDragLesson.split('-').map(Number);
        dragSource={type:'lesson',mi,li};
    }else return;
    event.dataTransfer.effectAllowed='move';
    event.dataTransfer.setData('text/plain','campaign-course-reorder');
});
tree.addEventListener('dragover',event=>{
    if(!dragSource)return;
    if(event.target.closest('[data-tree-module],[data-tree-lesson]')){
        event.preventDefault();event.dataTransfer.dropEffect='move';
    }
});
tree.addEventListener('drop',event=>{
    if(!dragSource)return;
    const target=event.target.closest('[data-tree-module],[data-tree-lesson]');
    if(!target){dragSource=null;return}
    event.preventDefault();
    if(dragSource.type==='module' && target.hasAttribute('data-tree-module')){
        const to=Number(target.dataset.treeModule);
        let index=dragSource.mi;
        while(index!==to){
            const selector='button[data-action="'+(to<index?'module-up':'module-down')+'"]';
            const button=moduleAt(index)?.querySelector(':scope > .cs-module-body '+selector);
            if(!button)break;
            button.click();index+=to<index?-1:1;
        }
        pendingSelection={mi:index,li:0};
    }else if(dragSource.type==='lesson' && target.hasAttribute('data-tree-lesson')){
        const [mi,to]=target.dataset.treeLesson.split('-').map(Number);
        if(mi===dragSource.mi){
            let index=dragSource.li;
            while(index!==to){
                const button=lessonAt(mi,index)?.querySelector('button[data-action="'+(to<index?'lesson-up':'lesson-down')+'"]');
                if(!button)break;
                button.click();index+=to<index?-1:1;
            }
            pendingSelection={mi,li:index};
        }
    }
    dragSource=null;schedule();
});
tree.addEventListener('dragend',()=>{dragSource=null});

const tabs=[...root.querySelectorAll('[data-cs-tab]')];
tabs.forEach(t=>t.addEventListener('click',()=>setTab(t.dataset.csTab)));
root.querySelector('.cs-course-tabs')?.addEventListener('keydown',event=>{
    if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
    event.preventDefault();
    const i=tabs.indexOf(document.activeElement);
    const next=event.key==='Home'?0:event.key==='End'?tabs.length-1:
        (i+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;
    tabs[next].focus();setTab(tabs[next].dataset.csTab);
});
byId('cs-save-draft-panel').addEventListener('click',()=>byId('cs-save-draft').click());
byId('cs-course-type').addEventListener('change',event=>{
    byId('cs-cover-kind').value=event.target.value;
    byId('cs-cover-kind').dispatchEvent(new Event('change',{bubbles:true}));
});
byId('cs-cover-kind').addEventListener('change',event=>byId('cs-course-type').value=event.target.value);
byId('cs-add-module').addEventListener('click',()=>{
    pendingSelection={mi:modules().length-1,li:0};schedule();
});
byId('cs-campaign-title').addEventListener('input',setActivePanel);
byId('cs-selected-module-title').addEventListener('input',event=>{
    const input=moduleAt(activeModule)?.querySelector(':scope > .cs-module-body > .cs-field-grid [data-field="module-title"]');
    if(!input)return;
    input.value=event.target.value;
    input.dispatchEvent(new Event('input',{bubbles:true}));
    byId('cs-current-unit-heading').textContent='Unit '+(activeModule+1)+': '+(event.target.value.trim()||'Untitled unit');
    byId('cs-save-state').textContent='Unsaved module name. Save draft to keep changes.';
});

let mediaPreviewTimer=null;
function scheduleMediaPreview(){
    if(mediaPreviewTimer!==null)clearTimeout(mediaPreviewTimer);
    mediaPreviewTimer=setTimeout(()=>{
        mediaPreviewTimer=null;
        showActiveMedia();
    },350);
}
list.addEventListener('input',event=>{
    const lesson=event.target.closest('.cs-lesson');
    if(lesson && event.target.matches('[data-field="title"]')){
        const mirror=lesson.querySelector('[data-cs-english-title]');
        if(mirror)mirror.textContent=event.target.value;
    }
    if(lesson && event.target.matches('[data-editor]')){
        const mirror=lesson.querySelector('[data-cs-english-preview]');
        if(mirror)mirror.textContent=event.target.innerText;
    }
    if(event.target.matches('[data-field="url"]'))scheduleMediaPreview();
    if(event.target.matches('[data-field="module-title"],[data-field="title"]'))schedule();
});
list.addEventListener('change',event=>{
    if(event.target.matches('[data-field="type"]')){
        // Redraw only when the editor layout changes. Redrawing every field on
        // blur destroys tree buttons between pointerdown and click (notably after
        // entering a media URL), preventing users from navigating to a lesson.
        const kind=event.target.value;
        activeTab=(kind==='quiz'||kind==='exam')?'assessments':'curriculum';
        schedule();
    }else if(event.target.matches('[data-field="cefr"]')){
        // A CEFR change updates the Khmer preview badge and metadata text.
        schedule();
    }
    // Text fields and media URLs already update the base model on input/change;
    // they must not replace the outline while another control is being clicked.
});
// Capture the old lesson index before the base editor redraws the DOM.
list.addEventListener('click',event=>{
    const duplicate=event.target.closest('button[data-action="lesson-duplicate"]');
    if(!duplicate)return;
    const mod=duplicate.closest('[data-module-index]');
    const lesson=duplicate.closest('[data-lesson-index]');
    if(!mod || !lesson)return;
    pendingSelection={mi:Number(mod.dataset.moduleIndex),li:Number(lesson.dataset.lessonIndex)+1};
    activeTab='metadata';
},true);
list.addEventListener('click',event=>{
    const button=event.target.closest('[data-action]');
    if(!button)return;
    const ops=['module-remove','module-up','module-down','lesson-remove','lesson-up','lesson-down','lesson-duplicate','add-lesson','add-question','remove-question','move-question-up','move-question-down'];
    if(!ops.includes(button.dataset.action))return;
    if(button.dataset.action==='add-lesson'){
        const mod=button.closest('.cs-module'),mi=Number(mod?.dataset.moduleIndex||0);
        pendingSelection={mi,li:lessons(mod).length};
    }
    schedule();
});
const observer=new MutationObserver(records=>{
    if(records.some(r=>[...r.addedNodes].some(n=>n.nodeType===1 &&
        (n.matches?.('.cs-module,.cs-lesson')||n.querySelector?.('.cs-module,.cs-lesson')))))schedule();
});
observer.observe(list,{childList:true,subtree:true});
const status=byId('cs-save-state');
new MutationObserver(()=>{
    if(!/published/i.test(status.textContent||''))return;
    root.dataset.publicationStatus='published';
    const label=byId('cs-publication-status');
    label.textContent='Published';label.classList.add('is-published');label.classList.remove('is-draft');
    const mark=byId('cs-published-mark');mark.textContent='✓';mark.classList.add('is-complete');
    byId('cs-published-label').textContent='Visible in the homepage hero';
}).observe(status,{childList:true,subtree:true});
sync();
})();
