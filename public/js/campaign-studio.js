(() => {
    const root = document.querySelector('[data-studio]');
    const modules = root.querySelector('[data-modules]');
    const dialog = root.querySelector('dialog');
    const status = root.querySelector('[data-status]');
    let selected, page = 1, pending = 0, requestId = 0;
    function element(tag, text, cls) {
        const el = document.createElement(tag);
        if (text) el.textContent = text;
        if (cls) el.className = cls;
        return el;
    }
    function button(text, action) {
        const el = element('button', text);
        el.type = 'button';
        el.onclick = action;
        return el;
    }
    function field(name, value) {
        const label = element('label', name);
        const input = element('input');
        input.value = value || '';
        input.maxLength = 140;
        label.append(input);
        return {label,input};
    }
    function count() {
        root.querySelector('[data-count]').textContent = new Intl.NumberFormat().format(modules.children.length)+' module'+(modules.children.length===1?'':'s')+' · '+modules.querySelectorAll('.cs-lesson').length+' lesson'+(modules.querySelectorAll('.cs-lesson').length===1?'':'s');
    }
    function moving(node, parent, name) {
        const group = element('div', '', 'cs-actions');
        group.append(button('↑', () => {if(node.previousElementSibling) parent.insertBefore(node,node.previousElementSibling);}),
            button('↓', () => {if(node.nextElementSibling) parent.insertBefore(node.nextElementSibling,node);}),
            button('Remove '+name, () => {node.remove();count();}));
        group.children[0].setAttribute('aria-label','Move '+name+' up');
        group.children[1].setAttribute('aria-label','Move '+name+' down');
        return group;
    }
    async function api(url, options={}) {
        const res=await fetch(url,{...options,headers:{Accept:'application/json','X-CSRF-TOKEN':root.dataset.token,...options.headers}});
        const data=await res.json().catch(()=>({}));
        if(!res.ok) throw Error(Object.values(data.errors||{}).flat()[0] || data.message || 'Request failed ('+res.status+').');
        return data;
    }
    function youtubeId(value) {
        return /^https:\/\/(?:(?:www\.|m\.)?youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})(?:[?&#].*)?$/.exec(value)?.[1];
    }
    function preview(row) {
        const out=root.querySelector('[data-preview]');
        out.replaceChildren();
        const d=row.media;
        if(!d.url) return;
        const el=element(d.type==='youtube'?'iframe':d.type==='image'?'img':'video');
        if(d.type==='youtube') {
            const id=youtubeId(d.url);
            if(!id) return;
            el.src='https://www.youtube-nocookie.com/embed/'+id;el.title='YouTube lesson preview';el.allowFullscreen=true;
        } else {el.src=d.url;if(d.type==='video') el.controls=true;else el.alt='Lesson preview';}
        out.append(el);
    }
    async function importMedia(row,form) {
        pending++;status.textContent='Checking media dimensions…';
        try {
            row.media=await api(root.dataset.media,{method:'POST',body:form});
            row.note.textContent='1920 × 1080 · '+row.media.type;
            row.youtube.hidden=true;preview(row);status.textContent='Media ready.';
        } catch(e){status.textContent=e.message;} finally {pending--;}
    }
    async function library(reset=true) {
        const seq=++requestId, results=root.querySelector('[data-results]');
        if(reset){page=1;results.replaceChildren();}
        try {
            const url=new URL(root.dataset.library,location.href);
            url.searchParams.set('q',root.querySelector('[data-search]').value);url.searchParams.set('page',page);
            const data=await api(url);
            if(seq!==requestId)return;
            for(const item of data.items) results.append(button(item.name,()=>{
                dialog.close();const form=new FormData();form.set('media_id',item.id);importMedia(selected,form);
            }));
            if(!results.children.length)results.textContent='No matching media.';
            root.querySelector('[data-more]').hidden=page>=data.last_page;
        } catch(e){results.textContent=e.message;}
    }
    function selectField(name, items, value) {
        const label=element('label',name), select=element('select');
        for(const [key,title] of items){const opt=element('option',title); opt.value=key;select.append(opt);}
        select.value=value;label.append(select);return {label,select};
    }
    function multiline(labelText,value,rows=3) {
        const label=element('label',labelText),input=element('textarea');
        input.rows=rows;input.value=value||'';label.append(input);return {label,input};
    }
    function question(parent,data={}) {
        const box=element('fieldset','','cs-question'),prompt=field('Question',data.prompt);
        prompt.input.maxLength=1000;
        const choices=multiline('Answer choices · one per line',(data.options||['','']).join('\n'));
        const answer=selectField('Correct answer',[],'');
        function refresh(index) {
            answer.select.replaceChildren();
            choices.input.value.split('\n').forEach((name,i)=>{const o=element('option',(i+1)+'. '+name);o.value=i;answer.select.append(o);});
            answer.select.value=String(Math.min(Math.max(0,index),answer.select.options.length-1));
        }
        refresh(data.correct||0);choices.input.oninput=()=>refresh(Number(answer.select.value));
        const feedback=multiline('Answer explanation',data.feedback,2);
        box.values=()=>({prompt:prompt.input.value.trim(),options:choices.input.value.split('\n').map(s=>s.trim()),correct:Number(answer.select.value),feedback:feedback.input.value.trim()});
        box.append(prompt.label,choices.label,answer.label,feedback.label,button('Remove question',()=>box.remove()));parent.append(box);
    }
    function editor(row,data,actions,file,yt) {
        const mode=['image','video','youtube'].includes(data.type)?'media':data.type||'text';
        const picker=selectField('Lesson element',[['text','Text'],['html','HTML / rich text'],['emoji','Emoji'],['media','Media'],['code','Programming language'],['quiz','Quiz'],['exam','Exam']],mode);
        const body=multiline('Lesson content',data.body),language=field('Programming language',data.language||'JavaScript');
        const takeaway=multiline('Lesson feedback (optional)',data.feedback,2);
        const toolbar=element('div','','cs-tools');
        const tools=selectField('Insert HTML element',[
            ['p','Paragraph'],['h2','Heading'],['strong','Bold'],['em','Italic'],['u','Underline'],['s','Strikethrough'],['blockquote','Quotation'],['ul','Bulleted list'],['ol','Numbered list'],['a','Link'],['table','Table'],['pre','Code block'],['sup','Superscript'],['sub','Subscript'],['hr','Divider']
        ],'p');
        function insert(before,after='') {
            const input=body.input, a=input.selectionStart,b=input.selectionEnd, text=input.value.slice(a,b);
            input.setRangeText(before+text+after,a,b,'end');input.focus();
        }
        toolbar.append(tools.label,button('Insert',()=>{
            const tag=tools.select.value;
            if(tag==='hr')insert('<hr>');
            else if(tag==='ul'||tag==='ol')insert('<'+tag+'><li>','</li></'+tag+'>');
            else if(tag==='table')insert('<table><tbody><tr><td>','</td></tr></tbody></table>');
            else if(tag==='a')insert('<a href="https://">','</a>');
            else insert('<'+tag+'>','</'+tag+'>');
        }));
        const emojis=element('div','','cs-tools');
        for(const emoji of ['🙂','🎓','💡','✅','👏','📝'])emojis.append(button(emoji,()=>insert(emoji)));
        const questions=element('div'),add=button('＋ Add question',()=>question(questions));
        (data.questions||[]).forEach(q=>question(questions,q));
        const mediaBox=element('div');mediaBox.append(actions,file,yt.label,row.note);
        function display(){
            const kind=picker.select.value, assessment=['quiz','exam'].includes(kind);
            body.label.hidden=assessment||kind==='media';language.label.hidden=kind!=='code';toolbar.hidden=kind!=='html';emojis.hidden=kind!=='emoji';mediaBox.hidden=kind!=='media';
            questions.hidden=add.hidden=!assessment;
            if(assessment&&!questions.children.length)question(questions);
        }
        picker.select.onchange=display;display();
        row.content=()=>{
            const kind=picker.select.value;
            const item=kind==='media'?{...row.media,type:row.media.type||'image'}:{type:kind};
            if(!body.label.hidden)item.body=body.input.value;
            if(kind==='code')item.language=language.input.value;
            if(!questions.hidden)item.questions=Array.from(questions.children,q=>q.values());
            item.feedback=takeaway.input.value;return item;
        };
        function previewContent(){
            const item=row.content(),out=root.querySelector('[data-preview]');
            if(kindIsMedia(item))return preview(row);
            const content=element('div','','cs-preview-content');
            if(item.type==='html'){
                const frame=element('iframe');frame.setAttribute('sandbox','');frame.title='HTML preview';
                frame.srcdoc='<meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;; style-src &apos;unsafe-inline&apos;"><style>body{background:white;color:#172638;font:16px system-ui}</style>'+item.body;
                content.append(frame);
            }else if(item.questions){
                item.questions.forEach(q=>{const group=element('fieldset');group.append(element('legend',q.prompt));q.options.forEach((v,i)=>group.append(button(v||'Choice',()=>{result.textContent=(i===q.correct?'Correct. ':'Correct answer: '+q.options[q.correct]+'. ')+q.feedback;})));const result=element('p');group.append(result);content.append(group);});
            }else content.append(element(item.type==='code'?'pre':'p',item.body));
            out.replaceChildren(content);
        }
        function kindIsMedia(item){return ['image','video','youtube'].includes(item.type);}
        row.append(picker.label,toolbar,emojis,language.label,body.label,mediaBox,questions,add,takeaway.label,button('Preview lesson',previewContent));
    }
    function lesson(parent,data={}) {
        const row=element('article','','cs-lesson'), title=field('Lesson title',data.lesson);
        row.media={type:data.type,url:data.url,asset:data.asset};row.titleInput=title.input;
        const actions=element('div','','cs-actions'),file=element('input');file.type='file';file.hidden=true;
        file.onchange=()=>{if(file.files[0]){const f=new FormData();f.append('file',file.files[0]);importMedia(row,f);file.value='';}};
        for(const kind of ['Image','Video']) actions.append(button('Upload '+kind.toLowerCase(),()=>{file.accept=kind==='Image'?'image/jpeg,image/png,image/webp':'video/mp4,video/webm';file.click();}));
        const yt=field('YouTube URL',data.type==='youtube'?data.url:'');yt.input.maxLength=2048;yt.label.hidden=data.type!=='youtube';
        yt.input.oninput=()=>{row.media={type:'youtube',url:yt.input.value.trim()};row.note.textContent='YouTube';};
        actions.append(button('YouTube',()=>{yt.label.hidden=false;yt.input.focus();}),button('Media library',()=>{selected=row;dialog.showModal();library();}),button('Preview',()=>preview(row)));
        row.youtube=yt.label;row.note=element('small',data.type||'Choose lesson media');
        row.append(title.label);editor(row,data,actions,file,yt);row.append(moving(row,parent,'lesson'));parent.append(row);count();
    }
    function module(title='') {
        const box=element('section','','cs-module cs-card'), name=field('Module title',title), list=element('div');
        box.titleInput=name.input;box.list=list;
        box.append(name.label,moving(box,modules,'module'),list,button('＋ Add lesson',()=>lesson(list)));modules.append(box);count();
        return box;
    }
    const groups=new Map();
    for(const row of JSON.parse(root.dataset.rows)){if(!groups.has(row.course))groups.set(row.course,module(row.course));lesson(groups.get(row.course).list,row);}
    if(!groups.size)lesson(module().list);
    root.querySelector('[data-add-module]').onclick=()=>lesson(module().list);
    root.querySelector('[data-close]').onclick=()=>dialog.close();
    let timer;root.querySelector('[data-search]').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>library(),250);};
    root.querySelector('[data-more]').onclick=()=>{page++;library(false);};
    async function save(action){
        try {
            if(pending)throw Error('Wait for the media check to finish.');
            const rows=[],names=new Set();
            for(const mod of modules.children){
                const course=mod.titleInput.value.trim();
                if(!course || names.has(course) || !mod.list.children.length)throw Error('Each module needs a unique title and at least one lesson.');
                names.add(course);
                for(const row of mod.list.children){
                    const lesson=row.titleInput.value.trim();
                    if(action==='publish' && !lesson)throw Error('Each published lesson needs a title.');
                    rows.push({course,lesson,...row.content()});
                }
            }
            const controls=root.querySelectorAll('[data-publish],[data-save-draft]');controls.forEach(b=>b.disabled=true);
            const result=await api(root.dataset.save,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({version:root.dataset.version,action,rows})});
            root.dataset.version=result.version;status.textContent=result.message;root.querySelector('[data-state]').textContent=action==='publish'?'Published':'Unpublished draft';
         }catch(e){status.textContent=e.message;}finally{root.querySelectorAll('[data-publish],[data-save-draft]').forEach(b=>b.disabled=false);}
    }
    root.querySelector('[data-publish]').onclick=()=>save('publish');
    root.querySelector('[data-save-draft]').onclick=()=>save('draft');
})();
