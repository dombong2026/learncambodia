(() => {
'use strict';
const root = document.getElementById('cs-builder');
if (!root || !root.classList.contains('cs-course-workspace')) return;
const list = document.getElementById('cs-modules');
const tree = document.getElementById('cs-course-tree');
const byId = id => document.getElementById(id);
if (!list || !tree) return;
let activeModule = 0, activeLesson = 0, activeTab = 'curriculum';
let pendingSelection = null, dragSource = null, scheduled = false;
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
    Object.values(panels).forEach(p => body.appendChild(p));
    lesson.dataset.csAdapted = 'yes';
}
function treeMarkup() {
    return modules().map((mod,mi) => {
        const entries = lessons(mod);
        let html = '<div class="cs-tree-unit" data-tree-module="'+mi+'"><div class="cs-tree-unit-heading">' +
            '<span class="cs-tree-drag" data-cs-drag-module="'+mi+'" draggable="true" title="Drag to reorder unit" aria-label="Drag unit '+(mi+1)+'">⠿</span>' +
            '<button type="button" class="cs-tree-unit-button" data-cs-select-module="'+mi+'">' +
            '<span class="cs-tree-folder" aria-hidden="true">▣</span><span class="cs-tree-unit-name">Unit '+(mi+1)+': '+escaped(titleOf(mod)||'Untitled unit')+'</span></button>' +
            '<button type="button" class="cs-tree-add" data-cs-add-lesson="'+mi+'" title="Add lesson" aria-label="Add lesson to unit '+(mi+1)+'">＋</button></div>' +
            '<div class="cs-tree-lessons">';
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
    const meta=root.querySelector('.cs-course-campaign-metadata');
    meta.hidden = activeTab !== 'metadata';
    for (const mod of modules()) {
        const selected = Number(mod.dataset.moduleIndex) === activeModule;
        mod.toggleAttribute('data-cs-active',selected);
        if (selected && !mod.open) mod.open = true;
        for (const l of lessons(mod)) {
            const isActive=selected && Number(l.dataset.lessonIndex)===activeLesson;
            l.toggleAttribute('data-cs-active',isActive);
            if(isActive && !l.open)l.open=true;
            l.querySelectorAll('[data-cs-panel]').forEach(p=>p.hidden=p.dataset.csPanel!==activeTab);
        }
    }
    root.querySelectorAll('[data-cs-tab]').forEach(t=>{
        const selected=t.dataset.csTab===activeTab;
        t.classList.toggle('is-active',selected);
        t.setAttribute('aria-selected',String(selected));
        t.tabIndex=selected?0:-1;
        if(selected)byId('cs-course-stage').setAttribute('aria-labelledby',t.id);
    });
    const campaignTitle=byId('cs-campaign-title');
    byId('cs-header-title').textContent=campaignTitle.value.trim()||'New learning campaign';
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
function select(mi,li){activeModule=mi;activeLesson=li;sync()}
function setTab(tab){
    if(!['metadata','curriculum','assessments','translation'].includes(tab))return;
    activeTab=tab;setActivePanel();
}

tree.addEventListener('click',event=>{
    const unit=event.target.closest('[data-cs-select-module]');
    const lesson=event.target.closest('[data-cs-select-lesson]');
    const plus=event.target.closest('[data-cs-add-lesson]');
    if(plus){
        const mi=Number(plus.dataset.csAddLesson), mod=moduleAt(mi);
        pendingSelection={mi,li:lessons(mod).length};
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
    if(event.target.matches('[data-field="module-title"],[data-field="title"]'))schedule();
});
list.addEventListener('change',schedule);
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
