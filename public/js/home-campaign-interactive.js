// @ts-nocheck
// Homepage campaign composer; draft data is only persisted after the Laravel form is submitted.
const form = document.querySelector('#home-campaign-form');
const root = document.querySelector('#campaign-module-list');
const modulesField = document.querySelector('#campaign-modules-json');
if (form && root && modulesField) {
    const source = document.querySelector('#campaign-initial-modules');
    let modules = JSON.parse(source?.textContent || '[]');
    const id = () => (window.crypto?.randomUUID?.() || 'item-' + Date.now() + '-' + Math.random().toString(36).slice(2));
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const blankLesson = () => ({
        id: id(), title: 'New lesson', type: 'multiple_choice', content_html: '',
        explanation_html: '', feedback_correct: 'Correct! You applied the grammar rule.',
        feedback_incorrect: 'Try again. Read the explanation for guidance.',
        options: ['Option A', 'Option B', 'Option C', 'Option D'], correct_index: 0,
        pairs: [{left:'Present simple',right:'Habits and routines'},{left:'Present continuous',right:'Actions happening now'}],
        sequence: ['First step', 'Second step', 'Third step'],
        media_kind: '', media_url: '',
    });
    const blankModule = () => ({id:id(),title:'New module',lessons:[blankLesson()]});
    const button = (action,label,title) => '<button type="button" data-action="' + action + '" title="' + label + '" aria-label="' + label + '" class="campaign-secondary">' + title + '</button>';
    const input = (field,value,placeholder,max) => '<input data-field="' + field + '" maxlength="' + max + '" placeholder="' + escape(placeholder) + '" value="' + escape(value) + '">';
    const textarea = (field,value,rows) => '<textarea data-field="' + field + '" rows="' + rows + '">' + escape(value) + '</textarea>';
    const safeVisual = html => {
        const documentNode = new DOMParser().parseFromString(String(html || ''), 'text/html');
        const forbidden = 'script,style,iframe,object,embed,svg,math,form,input,button,link,meta,base';
        documentNode.querySelectorAll(forbidden).forEach(element => element.remove());
        const allowed = new Set(['P','DIV','BR','B','STRONG','EM','I','U','S','H1','H2','H3','H4','BLOCKQUOTE','PRE','CODE','UL','OL','LI','HR','A','TABLE','THEAD','TBODY','TR','TH','TD','SPAN','SUP','SUB','FIGURE','FIGCAPTION']);
        documentNode.body.querySelectorAll('*').forEach(element => {
            const attributes = Array.from(element.attributes);
            attributes.forEach(attr => element.removeAttribute(attr.name));
            if (element.tagName === 'A') {
                const href = attributes.find(attr => attr.name === 'href')?.value;
                if (href && /^(https?:\/\/|mailto:|\/[^/])/i.test(href)) element.setAttribute('href', href);
            }
            if (element.tagName === 'CODE') {
                const language = attributes.find(attr => attr.name === 'data-language')?.value;
                if (language && /^[a-z][a-z0-9+#-]{0,23}$/.test(language)) element.setAttribute('data-language', language);
            }
            if (!allowed.has(element.tagName)) element.replaceWith(...Array.from(element.childNodes));
        });
        return documentNode.body.innerHTML;
    };
    function editorMarkup(field,label,value) {
        return '<div data-editor-wrap data-editor-field="' + field + '" class="mt-4 min-w-0">' +
            '<p class="mb-1 text-xs font-extrabold">' + label + '</p>' +
            '<div class="campaign-toolbar" role="toolbar" aria-label="' + label + ' formatting">' +
            button('format-bold','Bold','B') + button('format-italic','Italic','I') +
            button('format-underline','Underline','U') + button('format-h2','Heading','H₂') +
            button('format-insertUnorderedList','Bulleted list','• List') +
            button('format-insertOrderedList','Numbered list','1. List') +
            button('format-blockquote','Quotation','Quote') +
            button('format-code','Code block','Code') +
            button('format-link','Insert link','Link') +
            button('format-table','Insert table','Table') +
            button('format-emoji','Insert emoji','😊') +
            button('format-html','Toggle HTML source','HTML') +
            '</div><div data-editor-visual contenteditable="true" spellcheck="true" role="textbox" aria-multiline="true" class="campaign-editor" data-placeholder="Write one clear sentence, explanation, emoji, media reference, or code snippet…">' + safeVisual(value) +
            '</div><textarea data-editor-source class="campaign-source" aria-label="' + label + ' HTML source" hidden></textarea></div>';
    }
    function answerMarkup(lesson) {
        if (lesson.type === 'multiple_choice') {
            const options = lesson.options?.length >= 2 ? lesson.options : ['Option A','Option B'];
            return '<div class="mt-4 grid gap-3 sm:grid-cols-2"><div><p class="text-xs font-extrabold">Answer options</p>' +
                options.map((option,index) => '<label class="campaign-field mt-2">Option ' + (index+1) + '<input data-option value="' + escape(option) + '" maxlength="300"></label>').join('') +
                '<div class="mt-2 flex gap-2">' + button('add-option','Add answer option','+ Option') + button('remove-option','Remove last option','− Option') + '</div></div>' +
                '<label class="campaign-field">Correct answer<select data-correct-index>' +
                options.map((option,index) => '<option value="' + index + '"' + (Number(lesson.correct_index)===index?' selected':'') + '>Option ' + (index+1) + '</option>').join('') +
                '</select></label></div>';
        }
        if (lesson.type === 'matching') {
            const pairs = lesson.pairs?.length >= 2 ? lesson.pairs : blankLesson().pairs;
            return '<div class="mt-4"><p class="text-xs font-extrabold">Match the left block with the right answer. Students can drag blocks or use select controls.</p><div class="mt-2 space-y-2" data-pairs>' +
                pairs.map((pair,index) => '<div data-pair class="grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]"><label class="campaign-field">Prompt ' + (index+1) + input('left',pair.left,'Left prompt',300) + '</label><label class="campaign-field">Correct match' + input('right',pair.right,'Right answer',300) + '</label>' + button('pair-up','Move pair up','↑') + '</div>').join('') +
                '</div><div class="mt-2 flex gap-2">' + button('add-pair','Add matching pair','+ Pair') + button('remove-pair','Remove last pair','− Pair') + '</div></div>';
        }
        if (lesson.type === 'ordering') {
            const steps = lesson.sequence?.length >= 2 ? lesson.sequence : blankLesson().sequence;
            return '<div class="mt-4"><p class="text-xs font-extrabold">Enter blocks in the correct sequence. Students will arrange shuffled blocks.</p><div data-steps class="mt-2 space-y-2">' +
                steps.map((step,index) => '<div data-step class="flex items-end gap-2"><label class="campaign-field flex-1">Step ' + (index+1) + input('step',step,'Step text',300) + '</label>' + button('step-up','Move step up','↑') + '</div>').join('') +
                '</div><div class="mt-2 flex gap-2">' + button('add-step','Add a movable step','+ Block') + button('remove-step','Remove last step','− Block') + '</div></div>';
        }
        return '<p class="mt-3 text-xs text-slate-500">Reading / media lesson: no answer key needed.</p>';
    }
    function lessonMarkup(lesson,lessonIndex) {
        const details = document.createElement('details');
        details.open = true;
        details.className = 'campaign-lesson min-w-0';
        details.dataset.lessonId = lesson.id || id();
        details.draggable = true;
        details.innerHTML =
            '<summary class="flex cursor-pointer list-none flex-wrap items-center gap-2"><span class="campaign-step" data-lesson-number>' + (lessonIndex + 1) + '</span>' +
            '<span class="min-w-0 flex-1 text-sm font-black" data-lesson-label>' + escape(lesson.title || 'New lesson') + '</span>' +
            '<span class="text-xs text-slate-500">⋮⋮ Drag</span></summary>' +
            '<div class="mt-4 space-y-3"><div class="flex flex-wrap justify-end gap-2">' +
            button('lesson-up','Move lesson up','↑') + button('lesson-down','Move lesson down','↓') +
            button('lesson-duplicate','Duplicate lesson','Duplicate') + button('lesson-remove','Delete lesson','Delete') + '</div>' +
            '<div class="grid gap-3 sm:grid-cols-[1fr_185px]"><label class="campaign-field">Lesson title' +
            input('title',lesson.title,'Lesson title',180) + '</label>' +
            '<label class="campaign-field">Type<select data-field="type">' +
            [['reading','Text / media'],['multiple_choice','Multiple choice'],['matching','Matching blocks'],['ordering','Reorder blocks']].map(([value,label]) => '<option value="' + value + '"' + (lesson.type===value?' selected':'') + '>' + label + '</option>').join('') +
            '</select></label></div>' +
            editorMarkup('content_html','Lesson content · text, HTML, emoji, code',lesson.content_html) +
            '<div class="grid gap-3 sm:grid-cols-[160px_1fr]"><label class="campaign-field">Media type<select data-field="media_kind">' +
            [['','None'],['image','Image'],['video','Video'],['audio','Audio'],['youtube','YouTube']].map(([value,label]) => '<option value="' + value + '"' + ((lesson.media_kind || '')===value?' selected':'') + '>' + label + '</option>').join('') +
            '</select></label><label class="campaign-field">Media URL (HTTPS or YouTube link)' +
            input('media_url',lesson.media_url || '','https://...',2048) + '</label></div>' +
            '<div data-answers>' + answerMarkup(lesson) + '</div>' +
            editorMarkup('explanation_html','Accurate explanation · visible after answer check',lesson.explanation_html) +
            '<div class="grid gap-3 sm:grid-cols-2"><label class="campaign-field">Correct-answer feedback' +
            textarea('feedback_correct',lesson.feedback_correct || '',2) + '</label><label class="campaign-field">Incorrect-answer feedback' +
            textarea('feedback_incorrect',lesson.feedback_incorrect || '',2) + '</label></div></div>';
        return details;
    }
    function capture() {
        modules = Array.from(root.querySelectorAll(':scope > [data-module]')).map(moduleElement => ({
            id: moduleElement.dataset.moduleId,
            title: moduleElement.querySelector(':scope > div input[data-module-title]')?.value || '',
            lessons: Array.from(moduleElement.querySelectorAll(':scope > [data-lessons] > [data-lesson-id]')).map(lessonElement => {
                const result = {
                    id:lessonElement.dataset.lessonId,
                    title: lessonElement.querySelector('[data-field="title"]')?.value || '',
                    type: lessonElement.querySelector('[data-field="type"]')?.value || 'reading',
                    media_kind: lessonElement.querySelector('[data-field="media_kind"]')?.value || '',
                    media_url: lessonElement.querySelector('[data-field="media_url"]')?.value || '',
                    options: Array.from(lessonElement.querySelectorAll('[data-option]')).map(input => input.value),
                    correct_index: Number(lessonElement.querySelector('[data-correct-index]')?.value || 0),
                    pairs: Array.from(lessonElement.querySelectorAll('[data-pair]')).map(pair => ({
                        left: pair.querySelector('[data-field="left"]')?.value || '',
                        right: pair.querySelector('[data-field="right"]')?.value || '',
                    })),
                    sequence: Array.from(lessonElement.querySelectorAll('[data-step]')).map(step =>
                        step.querySelector('[data-field="step"]')?.value || ''),
                    feedback_correct: lessonElement.querySelector('[data-field="feedback_correct"]')?.value || '',
                    feedback_incorrect: lessonElement.querySelector('[data-field="feedback_incorrect"]')?.value || '',
                };
                lessonElement.querySelectorAll('[data-editor-wrap]').forEach(editor => {
                    const field = editor.dataset.editorField;
                    const source = editor.querySelector('[data-editor-source]');
                    result[field] = source.hidden ? editor.querySelector('[data-editor-visual]').innerHTML : source.value;
                });
                return result;
            }),
        }));
    }
    function draw() {
        root.replaceChildren();
        modules.forEach((module,index) => {
            const card = document.createElement('section');
            card.className = 'campaign-module min-w-0';
            card.dataset.module = '1';
            card.dataset.moduleId = module.id || id();
            card.draggable = true;
            card.innerHTML = '<div class="flex flex-wrap items-center gap-2"><span class="campaign-step">' + (index+1) + '</span>' +
                '<label class="campaign-field min-w-[150px] flex-1">Module ' + (index+1) +
                '<input data-module-title maxlength="180" value="' + escape(module.title || '') + '" placeholder="Module title"></label>' +
                '<span class="text-xs text-slate-500">⋮⋮ Drag</span>' +
                button('module-up','Move module up','↑') + button('module-down','Move module down','↓') +
                button('module-remove','Delete module','Delete') + '</div>' +
                '<div data-lessons class="mt-3 border-l-2 border-amber-300 pl-2 sm:pl-4"></div>' +
                '<div class="mt-3">' + button('lesson-add','Add a new lesson','+ Add lesson') + '</div>';
            const lessons = card.querySelector('[data-lessons]');
            (module.lessons || []).forEach((lesson,lessonIndex) => lessons.append(lessonMarkup(lesson,lessonIndex)));
            root.append(card);
        });
        modulesField.value = JSON.stringify(modules);
    }
    const move = (items,index,offset) => {
        const target = index+offset;
        if (target<0 || target>=items.length) return;
        [items[index],items[target]] = [items[target],items[index]];
    };
    document.querySelector('#campaign-add-module').addEventListener('click', () => {
        capture(); if (modules.length >=25) return alert('Maximum 25 modules.');
        modules.push(blankModule()); draw();
    });
    root.addEventListener('input', event => {
        if (event.target.matches('[data-field="title"]')) {
            event.target.closest('[data-lesson-id]')?.querySelector('[data-lesson-label]')?.replaceChildren(document.createTextNode(event.target.value || 'New lesson'));
        }
    });
    root.addEventListener('change', event => {
        if (event.target.matches('[data-field="type"]')) { capture(); draw(); }
    });
    root.addEventListener('click', event => {
        const control = event.target.closest('[data-action]');
        if (!control) return;
        const action = control.dataset.action;
        if (action.startsWith('format-')) {
            const wrap = control.closest('[data-editor-wrap]');
            const visual = wrap.querySelector('[data-editor-visual]');
            const source = wrap.querySelector('[data-editor-source]');
            const format = action.slice(7);
            if (format === 'html') {
                if (source.hidden) { source.value = visual.innerHTML; source.hidden = false; visual.hidden = true; }
                else { visual.innerHTML = safeVisual(source.value); visual.hidden = false; source.hidden = true; }
                return;
            }
            if (!source.hidden) { source.focus(); return; }
            visual.focus();
            if (format==='emoji') document.execCommand('insertText',false,'✅');
            else if (format==='h2' || format==='blockquote' || format==='code') document.execCommand('formatBlock',false,format==='h2'?'h2':format==='code'?'pre':'blockquote');
            else if (format==='link') {
                const url = prompt('HTTPS link');
                if (url && /^https:\/\//i.test(url)) document.execCommand('createLink',false,url);
            } else if (format==='table') {
                document.execCommand('insertHTML',false,'<table><tr><th>Heading</th><th>Heading</th></tr><tr><td>Cell</td><td>Cell</td></tr></table>');
            } else document.execCommand(format,false,null);
            return;
        }
        capture();
        const moduleNode = control.closest('[data-module]');
        const lessonNode = control.closest('[data-lesson-id]');
        const mi = Array.from(root.children).indexOf(moduleNode);
        const li = lessonNode ? Array.from(moduleNode.querySelector('[data-lessons]').children).indexOf(lessonNode) : -1;
        if (action==='module-up') move(modules,mi,-1);
        if (action==='module-down') move(modules,mi,1);
        if (action==='module-remove' && confirm('Delete this module and all lessons?')) modules.splice(mi,1);
        if (action==='lesson-add' && modules[mi].lessons.length<50) modules[mi].lessons.push(blankLesson());
        if (action==='lesson-up') move(modules[mi].lessons,li,-1);
        if (action==='lesson-down') move(modules[mi].lessons,li,1);
        if (action==='lesson-remove' && confirm('Delete this lesson?')) modules[mi].lessons.splice(li,1);
        if (action==='lesson-duplicate') modules[mi].lessons.splice(li+1,0,{...structuredClone(modules[mi].lessons[li]),id:id()});
        const lesson = modules[mi]?.lessons[li];
        if (lesson && action==='add-option' && lesson.options.length<6) lesson.options.push('New option');
        if (lesson && action==='remove-option' && lesson.options.length>2) lesson.options.pop();
        if (lesson && action==='add-pair' && lesson.pairs.length<12) lesson.pairs.push({left:'New prompt',right:'New match'});
        if (lesson && action==='remove-pair' && lesson.pairs.length>2) lesson.pairs.pop();
        if (lesson && action==='add-step' && lesson.sequence.length<12) lesson.sequence.push('New step');
        if (lesson && action==='remove-step' && lesson.sequence.length>2) lesson.sequence.pop();
        if (lesson && action==='pair-up') {
            const pi = Array.from(control.closest('[data-pairs]').children).indexOf(control.closest('[data-pair]'));
            move(lesson.pairs,pi,-1);
        }
        if (lesson && action==='step-up') {
            const si = Array.from(control.closest('[data-steps]').children).indexOf(control.closest('[data-step]'));
            move(lesson.sequence,si,-1);
        }
        draw();
    });
    let dragged = null;
    root.addEventListener('dragstart', event => {
        if (event.target.closest('input,textarea,select,[contenteditable]')) return event.preventDefault();
        const lesson = event.target.closest('[data-lesson-id]');
        const moduleNode = event.target.closest('[data-module]');
        const mi = Array.from(root.children).indexOf(moduleNode);
        const li = lesson ? Array.from(moduleNode.querySelector('[data-lessons]').children).indexOf(lesson) : -1;
        dragged = {mi,li}; event.dataTransfer.effectAllowed='move';
        event.dataTransfer.setData('text/plain', 'campaign-reorder');
    });
    root.addEventListener('dragover', event => { if (dragged) event.preventDefault(); });
    root.addEventListener('drop', event => {
        if (!dragged) return; event.preventDefault(); capture();
        const node = event.target.closest('[data-module]');
        if (!node) return;
        const mi = Array.from(root.children).indexOf(node);
        const lesson = event.target.closest('[data-lesson-id]');
        if (dragged.li < 0 && !lesson) {
            const item = modules.splice(dragged.mi,1)[0]; modules.splice(mi,0,item);
        } else if (dragged.li >= 0) {
            const item = modules[dragged.mi].lessons.splice(dragged.li,1)[0];
            const li = lesson ? Array.from(node.querySelector('[data-lessons]').children).indexOf(lesson) : modules[mi].lessons.length;
            modules[mi].lessons.splice(dragged.mi===mi && li>dragged.li?li-1:li,0,item);
        }
        dragged=null; draw();
    });
    root.addEventListener('dragend', () => { dragged=null; });
    root.addEventListener('paste', event => {
        const visual = event.target.closest('[data-editor-visual]');
        if (!visual) return;
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') || '');
    });
    form.querySelectorAll('[data-campaign-intent]').forEach(button => button.addEventListener('click', () => {
        form.querySelector('#campaign-publication-intent').value=button.dataset.campaignIntent;
    }));
    form.addEventListener('submit', event => {
        capture();
        modulesField.value=JSON.stringify(modules);
        if (form.querySelector('#campaign-publication-intent').value==='publish' && modules.every(module => module.lessons.length===0)) {
            event.preventDefault(); alert('Add at least one lesson before publishing.');
        }
    });
    const coverInput = document.querySelector('#campaign-cover-input');
    let coverObjectUrl;
    coverInput?.addEventListener('change', () => {
        const file=coverInput.files?.[0]; if (!file) return;
        if (coverObjectUrl) URL.revokeObjectURL(coverObjectUrl);
        coverObjectUrl=URL.createObjectURL(file);
        const image=document.querySelector('#campaign-cover-preview');
        image.src=coverObjectUrl; image.hidden=false;
        document.querySelector('#campaign-cover-empty').hidden=true;
    });
    draw();
}
