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
const blankQuestion = () => ({
    prompt:'',prompt_html:'',media_kind:'auto',media_url:'',media_asset:'',
    options:optionsDefault(),correct:0,feedback:''
});
const blankLesson = () => ({
    uid: makeId(), title:'New lesson', type:'quiz', url:'', asset:'', body:'',
    language:'', cefr:'B2', title_km:'', body_km:'', feedback:'', questions:[blankQuestion()]
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
    cefr:String(r.cefr || 'B2'),
    title_km:String(r.title_km || ''),
    body_km:String(r.body_km || ''),
    feedback:String(r.feedback || ''),
    questions:Array.isArray(r.questions) ? r.questions.map(q => ({
        prompt:String(q.prompt || ''),
        prompt_html:String(q.prompt_html || ''),
        media_kind:['auto','image','video','audio','youtube'].includes(q.media_kind) ? q.media_kind : 'auto',
        media_url:String(q.media_url || ''),
        media_asset:String(q.media_asset || ''),
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
// Keep the same allowlisted formats that Laravel's EditorHtml accepts.
const safeEditorStyle = raw => {
    const safe = new Map();
    for (const declaration of String(raw || '').toLowerCase().split(';')) {
        const index = declaration.indexOf(':');
        if (index < 1) continue;
        const key = declaration.slice(0,index).trim();
        let value = declaration.slice(index+1).trim();
        if (['color','background-color'].includes(key)) {
            const hex = /^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/.test(value);
            const match = value.match(/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/);
            if (!hex && match && match.slice(1).every(v => Number(v) <= 255))
                value = '#' + match.slice(1).map(v=>Number(v).toString(16).padStart(2,'0')).join('');
            if (hex || /^#[a-f0-9]{6}$/.test(value)) safe.set(key,value);
        } else if (key==='text-align' && ['left','center','right','justify'].includes(value)) {
            safe.set(key,value);
        } else if (['margin-left','margin-inline-start'].includes(key) &&
            /^(40|80|120|160)px$/.test(value)) {
            safe.set('margin-inline-start',value);
        } else if (key==='font-size' && /^(12|14|16|18|20|24|28|32|36)px$/.test(value)) {
            safe.set(key,value);
        } else if (key==='font-family' && ['arial','georgia','monospace'].includes(value)) {
            safe.set(key,value);
        }
    }
    return [...safe.entries()].map(([k,v])=>k+':'+v).join(';');
};
const readSourceHtml = html => {
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
    doc.querySelectorAll('script,style,iframe,object,embed,svg,math,form,input,button,link,meta,base').forEach(n => n.remove());
    const allowed = new Set(['P','DIV','BR','STRONG','B','EM','I','U','S','STRIKE','H1','H2','H3','H4','H5','H6',
        'BLOCKQUOTE','PRE','CODE','UL','OL','LI','HR','A','TABLE','THEAD','TBODY','TR','TH','TD',
        'SPAN','SUP','SUB','FIGURE','FIGCAPTION']);
    [...doc.body.querySelectorAll('*')].forEach(n => {
        if (!n.isConnected) return;
        const tag = n.tagName;
        const href = tag==='A' ? n.getAttribute('href') : null;
        let sourceStyle = n.getAttribute('style') || '';
        if (n.hasAttribute('align')) sourceStyle += ';text-align:'+n.getAttribute('align');
        if (tag==='FONT') {
            if (n.getAttribute('color')) sourceStyle += ';color:'+n.getAttribute('color');
            const sizes = {1:12,2:14,3:16,4:18,5:24,6:32,7:36};
            const size=sizes[Number(n.getAttribute('size'))];
            if (size) sourceStyle += ';font-size:'+size+'px';
            const face=(n.getAttribute('face')||'').toLowerCase();
            if (['arial','georgia','monospace'].includes(face)) sourceStyle += ';font-family:'+face;
        }
        const safeStyle = safeEditorStyle(sourceStyle);
        const language = tag==='CODE' ? n.getAttribute('data-language') : '';
        const colspan = ['TD','TH'].includes(tag) ? n.getAttribute('colspan') : '';
        const rowspan = ['TD','TH'].includes(tag) ? n.getAttribute('rowspan') : '';
        for (const attribute of [...n.attributes]) n.removeAttribute(attribute.name);
        const element = tag==='FONT' ? doc.createElement('span') : n;
        if (safeStyle) element.setAttribute('style',safeStyle);
        if (tag==='A' && href && /^(https?:\/\/|mailto:|\/(?!\/))/i.test(href)) {
            element.setAttribute('href',href);
            element.setAttribute('rel','noopener noreferrer nofollow');
        }
        if (tag==='CODE' && language && /^[a-z][a-z0-9+#-]{0,23}$/i.test(language))
            element.setAttribute('data-language',language);
        if (['TD','TH'].includes(tag)) {
            if (colspan && /^[1-8]$/.test(colspan)) element.setAttribute('colspan',colspan);
            if (rowspan && /^[1-8]$/.test(rowspan)) element.setAttribute('rowspan',rowspan);
        }
        if (tag==='FONT') {
            while (n.firstChild) element.appendChild(n.firstChild);
            n.replaceWith(element);
        } else if (!allowed.has(tag)) {
            n.replaceWith(...n.childNodes);
        }
    });
    return doc.body.innerHTML;
};
const codePlainText = raw => {
    const value=String(raw||'');
    if (/^\s*<pre[\s>]/i.test(value)) {
        const doc = new DOMParser().parseFromString(value,'text/html');
        return doc.querySelector('pre')?.textContent || '';
    }
    return value;
};
// Icon controls are SVG-only, with accessible names and matching dark/light styling.
const svgIcons = {
    undo:'<path d="m9 14-5-5 5-5M4 9h10a6 6 0 0 1 0 12h-2"/>',
    redo:'<path d="m15 14 5-5-5-5m5 5H10a6 6 0 0 0 0 12h2"/>',
    bold:'<path d="M6 4h7a4 4 0 0 1 0 8H6V4Zm0 8h8a4 4 0 0 1 0 8H6v-8Z"/>',
    italic:'<path d="M19 4h-9m4-0-4 16m-5 0h10"/>',
    underline:'<path d="M6 4v7a6 6 0 0 0 12 0V4M4 21h16"/>',
    strikeThrough:'<path d="M4 12h16M8 8a4 4 0 0 1 8-1m-8 9a4 4 0 0 0 8-1"/>',
    paragraph:'<path d="M18 4H9a5 5 0 0 0 0 10h3M12 4v16m4-16v16"/>',
    heading1:'<path d="M3 6v12M11 6v12M3 12h8M17 9h3v10m-3 0h6"/>',
    heading2:'<path d="M2 6v12m8-12v12M2 12h8m6-2a3 3 0 0 1 6 0c0 3-6 4-6 8h7"/>',
    heading3:'<path d="M2 6v12m8-12v12M2 12h8m7-2h5l-3 4a3 3 0 1 1-2 5"/>',
    insertUnorderedList:'<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
    insertOrderedList:'<path d="M10 6h11M10 12h11M10 18h11M4 6h1v4M3 9h3M3 15c1-1 3-1 3 0s-3 2-3 3h3"/>',
    justifyLeft:'<path d="M4 6h16M4 10h12M4 14h16M4 18h12"/>',
    justifyCenter:'<path d="M4 6h16M6 10h12M4 14h16M6 18h12"/>',
    justifyRight:'<path d="M4 6h16M8 10h12M4 14h16M8 18h12"/>',
    indent:'<path d="M4 6h16M10 10h10M10 14h10M4 18h16m-1-2"/>',
    outdent:'<path d="M4 6h16M10 10h10M10 14h10M4 18h16m-1-2"/>',
    blockquote:'<path d="M9 10H4v6h6v-6c0-3 1-5 4-6M20 10h-5v6h6v-6c0-3 1-5 3-6"/>',
    codeBlock:'<path d="M8 7 3 12l5 5m8-10 5 5-5 5M14 4l-4 16"/>',
    table:'<rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 10h18M3 15h18M9 4v16M15 4v16"/>',
    horizontalRule:'<path d="M4 12h16"/>',
    createLink:'<path d="M10 13a5 5 0 0 0 7 .5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
    unlink:'<path d="M8 10 4 14m12-4 4-4M8 16a5 5 0 0 0 7 0m-6-7a5 5 0 0 1 7 0M4 4l16 16"/>',
    removeFormat:'<path d="M4 5h16M12 5l-4 13m-4 2h16M3 3l18 18"/>',
    textColor:'<path d="m5 17 7-14 7 14M7 13h10M4 21h16"/>',
    highlight:'<path d="M6 16 15 4l5 4-9 12H6v-4ZM3 22h18"/>',
    emoji:'<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>',
    html:'<path d="m8 8-4 4 4 4m8-8 4 4-4 4M14 4l-4 16"/>'
};
const svg = name => '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'+svgIcons[name]+'</svg>';
const editorActions = [
    ['undo','Undo'],['redo','Redo'],['bold','Bold'],['italic','Italic'],
    ['underline','Underline'],['strikeThrough','Strikethrough'],
    ['paragraph','Paragraph'],['heading1','Heading 1'],['heading2','Heading 2'],['heading3','Heading 3'],
    ['insertUnorderedList','Bulleted list'],['insertOrderedList','Numbered list'],
    ['justifyLeft','Align left'],['justifyCenter','Align center'],['justifyRight','Align right'],
    ['indent','Indent'],['outdent','Outdent'],['blockquote','Blockquote'],
    ['codeBlock','Code block'],['table','Insert table'],['horizontalRule','Horizontal line'],
    ['createLink','Insert link'],['unlink','Remove link'],['textColor','Text color'],
    ['highlight','Highlight'],['removeFormat','Clear formatting'],
    ['emoji','Insert emoji'],['html','HTML source']
];
const codeLanguages = [
    ['text','Plain text'],['javascript','JavaScript'],['typescript','TypeScript'],['php','PHP'],
    ['python','Python'],['html','HTML'],['css','CSS'],['sql','SQL'],
    ['json','JSON'],['bash','Bash'],['java','Java'],['cpp','C++']
];
const editor = l => l.type === 'code' ? (
    '<div class="cs-code-source">' +
    '<label class="cs-label">Programming language</label><select class="cs-control" data-field="language" aria-label="Programming language">' +
    codeLanguages.map(([key,label])=>'<option value="'+key+'"'+(l.language===key?' selected':'')+'>'+label+'</option>').join('') +
    '</select><label class="cs-label">Code source</label>' +
    '<textarea class="cs-code-editor cs-textarea" spellcheck="false" data-editor-code aria-label="Code source" rows="13" maxlength="20000" placeholder="Write or paste your code here...">'+
    escapeHtml(codePlainText(l.body))+'</textarea>' +
    '<p class="cs-muted">Code is rendered as escaped text, not executed by students.</p></div>'
) : (
    '<div class="cs-editor-box">' +
    '<div class="cs-editor-toolbar" role="toolbar" aria-label="Full lesson text editor">' +
    editorActions.map(([action,label],i) => {
        const groupBreak = [2,6,10,12,15,17,21,25].includes(i) ? ' cs-tool-group-start' : '';
        return '<button type="button" class="cs-tool-button'+groupBreak+'" data-format="' + action +
            '" title="' + label + '" aria-label="' + label + '">' + svg(action) + '</button>';
    }).join('') +
    '</div><div class="cs-editor" contenteditable="true" data-editor data-placeholder="Write the lesson content, instructions or rich text..." role="textbox" aria-multiline="true">' +
    readSourceHtml(l.body) + '</div>' +
    '<textarea class="cs-editor-html" data-editor-source aria-label="Lesson HTML source" hidden></textarea></div>'
);
const questionEditor = q => {
    // Reuse the complete 28-control SVG lesson editor for each question prompt.
    const html=q.prompt_html || escapeHtml(q.prompt);
    return editor({type:'text',body:html})
        .replace('data-editor-source','data-question-editor-source')
        .replace(/ data-editor(?=\s)/, ' data-question-editor')
        .replace('Write the lesson content, instructions or rich text...', 'Write a question, insert headings, lists, code or a table...');
};
const questionCard = (q, idx) => (
    '<div class="cs-quiz-card cs-assessment-card" data-question-index="' + idx +
       '" data-question-asset="' + escapeHtml(q.media_asset||'') + '">' +
    '<div class="cs-question-head"><span class="cs-question-number" data-question-number>Q</span>' +
    '<strong>Question ' + (idx+1) + '</strong>' +
    buttons('move-question-up','Move question up','↑') +
    buttons('move-question-down','Move question down','↓') +
    buttons('remove-question','Remove question','✕') + '</div>' +
    '<section class="cs-assessment-section" aria-label="Question prompt">' +
    '<h3 class="cs-assessment-section-heading">Question</h3>' +
    questionEditor(q) +
    '</section>' +
    '<section class="cs-assessment-section" aria-label="Question media">' +
    '<h3 class="cs-assessment-section-heading">Media <span class="cs-muted">(optional)</span></h3>' +
    '<div class="cs-media-toolbar">' +
    '<label class="cs-btn cs-btn-outline cs-btn-xs cs-question-upload">＋ Upload media' +
    '<input type="file" data-question-file accept="image/png,image/jpeg,image/webp,video/mp4,video/webm" hidden></label>' +
    buttons('remove-question-media','Remove question media','Remove media') +
    '</div>' +
    '<div class="cs-question-media-fields"><div>' +
    '<label class="cs-label">Media URL (HTTPS)</label>' +
    '<input class="cs-input" type="url" maxlength="2048" data-question-field="media_url" placeholder="https://... or YouTube link" value="' +
    escapeHtml(q.media_url||'') + '"></div><div>' +
    '<label class="cs-label">Media type</label>' +
    '<select class="cs-control" data-question-field="media_kind" aria-label="Question media type">' +
    [['auto','Auto-detect'],['image','Image'],['video','Video'],['youtube','YouTube'],['audio','Audio']]
        .map(([kind,title])=>'<option value="'+kind+'"'+(q.media_kind===kind?' selected':'')+'>'+title+'</option>').join('') +
    '</select></div></div>' +
    '<div class="cs-lesson-live-preview cs-question-live-preview" data-cs-question-media-preview hidden></div>' +
    '</section>' +
    '<section class="cs-assessment-section" aria-label="Answer choices">' +
    '<h3 class="cs-assessment-section-heading">Answer Choices</h3>' +
    '<div class="cs-options">' +
    q.options.map((o,i) =>
        '<div class="cs-option"><input type="radio" data-question-correct name="cs-correct-' +
        idx + '-' + escapeHtml(q._uid || (q._uid=makeId())) + '" value="' + i +
        '"' + (Number(q.correct)===i?' checked':'') +
        ' aria-label="Correct choice ' + (i+1) + '">' +
        '<input class="cs-input" data-option-index="' + i + '" value="' +
        escapeHtml(o) + '" maxlength="500" aria-label="Answer choice ' + (i+1) + '"></div>'
    ).join('') + '</div>' +
    '<div class="cs-actions" style="justify-content:flex-start">' +
    buttons('add-choice','Add answer choice','＋ Choice') +
    buttons('remove-choice','Remove last answer choice','− Choice') + '</div>' +
    '</section>' +
    '<section class="cs-assessment-section" aria-label="Answer explanation and feedback">' +
    '<h3 class="cs-assessment-section-heading">Answer Explanation &amp; Feedback</h3>' +
    '<textarea class="cs-textarea" data-question-field="feedback" maxlength="1000" rows="3" placeholder="Explain why the correct answer is right; students see this after checking.">' +
    escapeHtml(q.feedback) + '</textarea>' +
    '</section></div>'
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
        escapeHtml(lesson.uid) + '" data-lesson-asset="' + escapeHtml(showAsset ? lesson.asset : '') + '"' +
        (lessonOpen.get(lesson.uid)!==false?' open':'') + '>' +
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
        '<div class="cs-course-cefr-field"><label class="cs-label">CEFR Level</label>' +
        '<select class="cs-control" data-field="cefr">' +
        ['A1','A2','B1','B2','C1','C2'].map(level => '<option value="' + level + '"' +
            (lesson.cefr===level?' selected':'') + '>' + level + '</option>').join('') +
        '</select></div>' +
        editor(lesson) +
        '<div class="cs-course-translation-fields">' +
          '<div class="cs-course-language-grid">' +
          '<div class="cs-language-pane"><div class="cs-language-pane-head"><span class="cs-language-badge">EN</span><strong>English (EN)</strong><span class="cs-mini-chip">Primary language</span></div>' +
          '<label class="cs-label">English title</label><div class="cs-english-title" data-cs-english-title>' + escapeHtml(lesson.title) + '</div>' +
          '<label class="cs-label">English description</label><div class="cs-english-preview" data-cs-english-preview>' + readSourceHtml(lesson.body) + '</div>' +
          '<small class="cs-muted">Edit English title in Metadata and the description in Curriculum.</small></div>' +
          '<div class="cs-language-pane"><div class="cs-language-pane-head"><span class="cs-language-badge">KM</span><strong>Khmer (KM)</strong><span class="cs-mini-chip">CEFR ' + escapeHtml(lesson.cefr) + '</span></div>' +
          '<label class="cs-label">Khmer lesson title</label>' + input('title_km',lesson.title_km,140,'lang="km" placeholder="ចំណងជើងមេរៀន"') +
          '<label class="cs-label">Khmer description / objectives</label>' +
          '<textarea class="cs-textarea" data-field="body_km" rows="7" maxlength="12000" lang="km" placeholder="សេចក្ដីពណ៌នាមេរៀន...">' + escapeHtml(lesson.body_km) + '</textarea>' +
          '</div></div></div>' +
        '<div class="cs-lesson-media"><strong style="font-size:12px">Lesson media</strong>' +
        '<p class="cs-muted">Add optional image or video media. The featured cover is configured separately in the right sidebar.</p>' +
        '<div class="cs-media-toolbar"><label class="cs-btn cs-btn-outline cs-btn-xs">＋ Upload media' +
        '<input type="file" data-lesson-file accept="image/png,image/jpeg,image/webp,video/mp4,video/webm" hidden></label>' +
        '<button type="button" class="cs-btn cs-btn-quiet cs-btn-xs" data-action="remove-media">Remove media</button></div>' +
        (root.classList.contains('cs-course-workspace')
            ? '<div class="cs-lesson-live-preview" data-cs-lesson-media-preview hidden></div>'
            : (preview || '')) +
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
                body:(l.type==='code'
                    ? '<pre><code>'+escapeHtml(codePlainText(l.body))+'</code></pre>'
                    : readSourceHtml(l.body)).slice(0,20000),
                url:l.url.trim().slice(0,2048),
                asset:l.asset || '',
                language:l.language || '',
                cefr:l.cefr || 'B2',
                title_km:(l.title_km || '').trim().slice(0,140),
                body_km:(l.body_km || '').trim().slice(0,12000),
                feedback:l.feedback.trim().slice(0,1000),
                questions:isQuiz?l.questions.map(q=>({
                    prompt:q.prompt.trim().slice(0,1000),
                    prompt_html:readSourceHtml(q.prompt_html || '').slice(0,6000),
                    media_kind:q.media_kind || 'auto',
                    media_url:(q.media_url || '').trim().slice(0,2048),
                    media_asset:q.media_asset || '',
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
        const title=el('cs-campaign-title').value.trim();
        if(!title)throw new Error('Enter a campaign title before saving.');
        const response=await requestJSON(root.dataset.saveUrl,{
            id:root.dataset.campaignId?Number(root.dataset.campaignId):null,
            title,kind:coverKind,cover_asset:coverAsset||null,action,version,rows
        });
        version=Number(response.version);
        root.dataset.version=String(version);
        if(response.id){root.dataset.campaignId=String(response.id);
            if(response.edit_url)window.history.replaceState({},'',response.edit_url);}
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
        coverAsset=media.asset;
        previewCover();
        status.textContent='Cover uploaded. Save draft or Publish to keep it.';
    } catch(error) {status.textContent=error.message;note(error.message,true)}
    event.target.value='';
});
el('cs-cover-kind').addEventListener('change',event=>{
    coverKind=event.target.value;
    note('Campaign type changed. Save draft or Publish to keep it.',false);
});
el('cs-remove-cover').addEventListener('click',()=>{
    if(!window.confirm('Remove the campaign featured cover?'))return;
    coverAsset='';
    previewCover();
    el('cs-cover-message').textContent='Cover removed from the editor. Save draft to apply the change.';
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
function updateQuestionPrompt(question, markup){
    const cleaned=readSourceHtml(markup).slice(0,6000);
    const div=document.createElement('div');
    div.innerHTML=cleaned;
    question.prompt_html=cleaned;
    question.prompt=(div.textContent||'').trim().slice(0,1000);
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
    }else if(target.matches('[data-question-editor]') && question){
        updateQuestionPrompt(question,target.innerHTML);
    }else if(target.matches('[data-question-editor-source]') && question){
        updateQuestionPrompt(question,target.value);
    }else if(target.matches('[data-editor]') && lesson){
        lesson.body=readSourceHtml(target.innerHTML);
    }else if(target.matches('[data-editor-source]') && lesson){
        lesson.body=readSourceHtml(target.value);
    }else if(target.matches('[data-editor-code]') && lesson){
        lesson.body=target.value;
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
// Prevent toolbar clicks from clearing an active text selection.
list.addEventListener('mousedown',event=>{
    if(event.target.closest('button[data-format]'))event.preventDefault();
});
list.addEventListener('click',event=>{
    const formatButton=event.target.closest('button[data-format]');
    if(formatButton){
        const wrapper=formatButton.closest('.cs-editor-box');
        if(!wrapper)return;
        const visual=wrapper.querySelector('[data-editor],[data-question-editor]');
        const source=wrapper.querySelector('[data-editor-source],[data-question-editor-source]');
        const action=formatButton.dataset.format;
        const {lesson,question}=getEntities(coords(formatButton));
        const store=html=>{
            if(question)updateQuestionPrompt(question,html);
            else if(lesson)lesson.body=readSourceHtml(html);
        };
        if(action==='html') {
            if(source.hidden){
                source.value=readSourceHtml(visual.innerHTML);
                source.hidden=false;visual.hidden=true;
            }else{
                const safe=readSourceHtml(source.value);
                visual.innerHTML=safe;
                store(safe);
                source.hidden=true;visual.hidden=false;
            }
            return;
        }
        if(!source.hidden){note('Switch to the visual editor to use formatting tools.',true);return;}
        visual.focus();
        const formatBlocks = {
            paragraph:'p',heading1:'h1',heading2:'h2',heading3:'h3',
            blockquote:'blockquote',codeBlock:'pre'
        };
        if(action in formatBlocks) {
            document.execCommand('formatBlock',false,formatBlocks[action]);
        } else if(action==='table') {
            // The HTML is fixed and contains no user input or remote resources.
            document.execCommand('insertHTML',false,
                '<table><tbody><tr><th>Heading 1</th><th>Heading 2</th></tr>'+
                '<tr><td>Cell 1</td><td>Cell 2</td></tr></tbody></table><p></p>');
        } else if(action==='horizontalRule') {
            document.execCommand('insertHorizontalRule',false,null);
        } else if(action==='emoji') {
            const emoji=window.prompt('Insert an emoji', '😊');
            if(emoji && [...emoji].length<=8)document.execCommand('insertText',false,emoji);
        } else if(action==='textColor' || action==='highlight') {
            const color=window.prompt(action==='textColor'?'Enter text color (#RRGGBB)':'Enter highlight color (#RRGGBB)',
                action==='textColor'?'#2563eb':'#fef08a');
            if(color && /^#[a-f0-9]{6}$/i.test(color)){
                document.execCommand(action==='textColor'?'foreColor':'hiliteColor',false,color);
            }
        } else if(action==='createLink') {
            const url=window.prompt('Enter a secure HTTPS link');
            if(url && url.length<=2048 && /^https:\/\/[^\s]+$/i.test(url))
                document.execCommand('createLink',false,url);
        } else {
            const commands=['undo','redo','bold','italic','underline','strikeThrough',
                'insertUnorderedList','insertOrderedList','justifyLeft','justifyCenter',
                'justifyRight','indent','outdent','unlink','removeFormat'];
            if(commands.includes(action))document.execCommand(action,false,null);
        }
        store(visual.innerHTML);
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
        if (!l || !m) return;
        const dup=structuredClone(l);
        dup.uid=makeId();
        dup.title=((l.title || 'Untitled lesson').replace(/\s*\(copy(?: \d+)?\)$/i,'').slice(0,133) + ' (copy)');
        lessonOpen.set(dup.uid,true);
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
    }else if(action==='remove-question-media' && q){
        q.media_asset='';q.media_url='';q.media_kind='auto';
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

list.addEventListener('change',async event=>{
    const input=event.target;
    if(!input.matches('[data-question-file]'))return;
    const file=input.files?.[0];if(!file)return;
    const {question}=getEntities(coords(input));
    if(!question)return;
    note('Uploading question media…',false);
    try {
        const uploaded=await uploadFile(file);
        if(!['image','video'].includes(uploaded.type) || !uploaded.asset)
            throw new Error('Select an image or video for this question.');
        question.media_asset=uploaded.asset;
        question.media_url='';
        question.media_kind=uploaded.type;
        render();
        note('Question media uploaded. Save draft to retain it.',false);
    }catch(error){note(error.message,true)}
    input.value='';
});
render();
})();
