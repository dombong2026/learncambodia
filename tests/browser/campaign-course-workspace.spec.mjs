import { test, expect } from '@playwright/test';

const rows = [
    {
        course:'English Grammar',lesson:'Present simple',type:'quiz',body:'<p>Choose the answer.</p>',
        cefr:'B2',title_km:'បច្ចុប្បន្នកាល',body_km:'ការប្រើប្រាស់បច្ចុប្បន្នកាល',
        url:'',asset:'',language:'',feedback:'',
        questions:[{prompt:'She ___ to school.',options:['go','goes','going'],correct:1,feedback:'Third-person singular.'}]
    },
    {
        course:'English Grammar',lesson:'Past tense',type:'quiz',body:'<p>Choose the past form.</p>',
        cefr:'B1',title_km:'អតីតកាល',body_km:'មេរៀនអតីតកាល',
        url:'',asset:'',language:'',feedback:'',
        questions:[{prompt:'Yesterday I ___ home.',options:['walk','walked'],correct:1,feedback:'Past tense needs -ed.'}]
    }
];
function fixture() {
    return [
        '<!doctype html><html><head><meta name="csrf-token" content="fixture-csrf"></head><body>',
        '<section id="cs-builder" class="cs-course-workspace" data-campaign-id="7" data-version="1"',
        ' data-save-url="/mock/course/save" data-upload-url="/mock/course/upload"',
        ' data-asset-base="/campaign-media" data-cover-asset="" data-cover-kind="quiz" data-publication-status="draft">',
        '<div id="cs-alert" hidden></div><button id="cs-publish-top" hidden></button>',
        '<button id="cs-save-draft-top" hidden></button>',
        '<div class="cs-layout cs-course-grid">',
        '<aside class="cs-course-outline"><span id="cs-unit-label"></span><nav id="cs-course-tree"></nav>',
        '<button id="cs-add-module">Add New Unit</button></aside>',
        '<main class="cs-course-editor"><h2 id="cs-edit-title"></h2><p id="cs-edit-subtitle"></p>',
        '<span id="cs-current-lesson-number"></span><button id="cs-save-draft-panel">Save</button>',
        '<nav class="cs-course-tabs" role="tablist">',
        '<button id="cs-tab-metadata" data-cs-tab="metadata" role="tab" aria-selected="true">Metadata (CEFR)</button>',
        '<button id="cs-tab-curriculum" data-cs-tab="curriculum" role="tab">Curriculum</button>',
        '<button id="cs-tab-assessments" data-cs-tab="assessments" role="tab">Assessments</button>',
        '<button id="cs-tab-translation" data-cs-tab="translation" role="tab">Translation (EN/KM)</button></nav>',
        '<div id="cs-course-stage"><div id="cs-selected-module-editor" hidden>',
        '<label for="cs-selected-module-title">Module title</label><input id="cs-selected-module-title" maxlength="140"></div>',
        '<h3 id="cs-current-unit-heading"></h3><span id="cs-current-content-type"></span>',
        '<div id="cs-modules"></div></div>',
        '<span id="cs-modules-count"></span><span id="cs-lessons-count"></span><span id="cs-questions-count"></span></main>',
        '<aside class="cs-sidebar cs-course-workflow"><section class="cs-card cs-course-campaign-metadata">',
        '<div class="cs-course-campaign-fields"><input id="cs-campaign-title" value="English Grammar">',
        '<select id="cs-course-type"><option value="quiz">Quiz</option><option value="exam">Exam</option></select></div></section>',
        '<span id="cs-save-state"></span><div id="cs-readiness-mark"></div><span id="cs-readiness-label"></span>',
        '<span id="cs-publication-status"></span><span id="cs-published-mark"></span>',
        '<span id="cs-published-label"></span><button id="cs-publish"></button><button id="cs-save-draft"></button>',
        '<img id="cs-cover-image" hidden><div id="cs-cover-placeholder"></div>',
        '<input id="cs-cover-file" type="file">',
        '<select id="cs-cover-kind"><option value="quiz">Quiz</option><option value="exam">Exam</option></select>',
        '<button id="cs-remove-cover" hidden></button><p id="cs-cover-message"></p>',
        '<div id="cs-outline"></div></aside></div>',
        '<script id="cs-initial-rows" type="application/json">'+JSON.stringify(rows)+'</script>',
        '</section></body></html>'
    ].join('');
}
async function mount(page) {
    await page.route('**/up',route=>route.fulfill({status:200,contentType:'text/html',body:'OK'}));
    await page.goto('/up');
    await page.setContent(fixture());
    await page.addStyleTag({path:'public/css/campaign-studio-premium.css'});
    await page.addStyleTag({path:'public/css/campaign-course-workspace.css'});
    await page.addScriptTag({path:'public/js/campaign-studio-premium.js'});
    await page.addScriptTag({path:'public/js/campaign-course-workspace.js'});
}
test('three-column course outline selects lessons and keeps translated fields on draft save', async ({page})=>{
    const submissions=[];
    await page.route('**/mock/course/save',async route=>{
        submissions.push(JSON.parse(route.request().postData()));
        await route.fulfill({status:200,contentType:'application/json',
            body:JSON.stringify({id:7,version:2,status:'draft',message:'Campaign draft saved.',edit_url:'/up?id=7'})});
    });
    await mount(page);
    await expect(page.locator('#cs-course-tree .cs-tree-lesson')).toHaveCount(2);
    await expect(page.locator('.cs-course-campaign-metadata')).toHaveCount(1);
    await expect(page.locator('#cs-campaign-title')).toHaveCount(1);
    await expect(page.locator('#cs-course-type')).toHaveCount(1);
    await expect(page.locator('.cs-course-header')).toHaveCount(0);
    await page.locator('[data-cs-toggle-unit="0"]').click();
    await expect(page.locator('[data-tree-module="0"] .cs-tree-lessons')).toBeHidden();
    await page.locator('[data-cs-toggle-unit="0"]').click();
    await expect(page.locator('[data-tree-module="0"] .cs-tree-lessons')).toBeVisible();
    await expect(page.locator('#cs-edit-title')).toHaveText('Present simple');
    await expect(page.locator('#cs-modules-count')).toHaveText('1');
    await expect(page.locator('#cs-lessons-count')).toHaveText('2');
    await expect(page.locator('#cs-questions-count')).toHaveText('2');
    await expect(page.locator('[data-cs-tab="metadata"]')).toHaveAttribute('aria-selected','true');

    await page.locator('[data-cs-select-lesson="0-1"]').click();
    await expect(page.locator('#cs-edit-title')).toHaveText('Past tense');
    await page.getByRole('tab',{name:'Translation (EN/KM)'}).click();
    const selected=page.locator('.cs-lesson[data-cs-active]');
    await expect(selected.locator('input[data-field="title_km"]')).toHaveValue('អតីតកាល');
    await selected.locator('input[data-field="title_km"]').fill('អតីតកាលសាមញ្ញ');
    await selected.locator('textarea[data-field="body_km"]').fill('សូមជ្រើសរើសចម្លើយត្រឹមត្រូវ');
    await page.getByRole('tab',{name:'Metadata (CEFR)'}).click();
    await selected.locator('select[data-field="cefr"]').selectOption('B2');
    await page.getByRole('tab',{name:'Assessments'}).click();
    await expect(selected.locator('.cs-quiz-card')).toHaveCount(1);
    await expect(selected.locator('[data-question-number]')).toHaveText('Q2');
    await page.locator('#cs-save-draft-panel').click();
    await expect(page.locator('#cs-save-state')).toContainText('Campaign draft saved.');
    expect(submissions).toHaveLength(1);
    expect(submissions[0].rows[1].cefr).toBe('B2');
    expect(submissions[0].rows[1].title_km).toBe('អតីតកាលសាមញ្ញ');
    expect(submissions[0].rows[1].body_km).toBe('សូមជ្រើសរើសចម្លើយត្រឹមត្រូវ');
    expect(submissions[0].rows[1].questions[0].correct).toBe(1);
    expect(submissions[0].rows[0].questions[0].correct).toBe(1);
});
test('course outline creates units and lessons, supports tabs and readable dark mode',async({page})=>{
    await mount(page);
    await page.locator('#cs-add-module').click();
    await expect(page.locator('#cs-course-tree .cs-tree-unit')).toHaveCount(2);
    await page.locator('[data-cs-add-lesson="1"]').click();
    await expect(page.locator('#cs-course-tree [data-tree-lesson="1-1"]')).toHaveCount(1);
    await page.locator('[data-cs-select-lesson="1-1"]').click();
    await expect(page.locator('#cs-current-lesson-number')).toHaveText('LESSON 2.2');
    await page.getByRole('tab',{name:'Metadata (CEFR)'}).focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('tab',{name:'Curriculum'})).toHaveAttribute('aria-selected','true');
    await page.evaluate(()=>document.documentElement.classList.add('dark'));
    const colors=await page.locator('.cs-course-outline').evaluate(node=>{
        const computed=getComputedStyle(node);
        return {bg:computed.backgroundColor,color:computed.color};
    });
    expect(colors.bg).not.toBe('rgba(0, 0, 0, 0)');
    await expect(page.locator('#cs-course-tree')).toBeVisible();
});


test('lesson type activates its relevant editor and preserves question keys when switched back',async({page})=>{
    await mount(page);
    const select=()=>page.locator('.cs-lesson[data-cs-active] select[data-field="type"]');
    const active=()=>page.locator('.cs-lesson[data-cs-active]');
    for(const kind of ['exam','quiz']){
        await page.getByRole('tab',{name:'Metadata (CEFR)'}).click();
        await select().selectOption(kind);
        await expect(page.getByRole('tab',{name:'Assessments'})).toHaveAttribute('aria-selected','true');
        await expect(active().locator('.cs-course-tab-panel[data-cs-panel="assessments"]')).toBeVisible();
        await expect(active().locator('.cs-quiz-card')).toHaveCount(1);
        await expect(active().locator('input[data-question-correct][value="1"]')).toBeChecked();
    }
    for(const kind of ['text','html','emoji','image','video','youtube']){
        await page.getByRole('tab',{name:'Metadata (CEFR)'}).click();
        await select().selectOption(kind);
        await expect(page.getByRole('tab',{name:'Curriculum'})).toHaveAttribute('aria-selected','true');
        await expect(active().locator('.cs-course-tab-panel[data-cs-panel="curriculum"]')).toBeVisible();
        await expect(active().locator('[data-cs-type-hint]')).toBeVisible();
        await expect(active().locator('[data-editor]')).toBeVisible();
        await expect(active().locator('.cs-editor-toolbar button[data-format]')).toHaveCount(28);
    }
    await page.getByRole('tab',{name:'Metadata (CEFR)'}).click();
    await select().selectOption('code');
    await expect(page.getByRole('tab',{name:'Curriculum'})).toHaveAttribute('aria-selected','true');
    await expect(active().locator('[data-editor-code]')).toBeVisible();
    await expect(active().locator('select[data-field="language"]')).toBeVisible();
    await active().locator('[data-editor-code]').fill('<?php echo "<script>alert(1)</script>";');
    await expect(active().locator('[data-editor-code]')).toHaveValue('<?php echo "<script>alert(1)</script>";');
    await page.getByRole('tab',{name:'Metadata (CEFR)'}).click();
    await select().selectOption('quiz');
    await expect(active().locator('.cs-quiz-card')).toHaveCount(1);
    await expect(active().locator('input[data-question-correct][value="1"]')).toBeChecked();
});
test('rich text toolbar creates allowed heading and table content and strips script tags', async({page})=>{
    const submissions=[];
    await page.route('**/mock/course/save',async route=>{
        submissions.push(JSON.parse(route.request().postData()));
        await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({
            id:7,version:2,status:'draft',message:'Campaign draft saved.',edit_url:'/up?id=7'
        })});
    });
    await mount(page);
    // The initial Metadata tab intentionally hides the Curriculum editor.
    // Navigate to the lesson's editing panel before exercising its toolbar.
    await page.getByRole('tab',{name:'Curriculum'}).click();
    const active=page.locator('.cs-lesson[data-cs-active]');
    const body=active.locator('[data-editor]');
    await expect(body).toBeVisible();
    await body.fill('Welcome to IELTS');
    await active.getByRole('button',{name:'Heading 2'}).click();
    await expect(body.locator('h2')).toContainText('Welcome to IELTS');
    await active.getByRole('button',{name:'Insert table'}).click();
    await expect(body.locator('table')).toHaveCount(1);
    await active.getByRole('button',{name:'HTML source'}).click();
    const source=active.locator('[data-editor-source]');
    await expect(source).toBeVisible();
    await source.fill('<h2>Lesson</h2><script>alert("unsafe")</script><p style="text-align:center">Centered</p>');
    await active.getByRole('button',{name:'HTML source'}).click();
    await expect(body.locator('script')).toHaveCount(0);
    await expect(body.locator('h2')).toHaveText('Lesson');
    await page.locator('#cs-save-draft-panel').click();
    expect(submissions).toHaveLength(1);
    expect(submissions[0].rows[0].body).not.toContain('<script');
    expect(submissions[0].rows[0].body).toContain('text-align:center');
});
test('Code source is safely escaped in saved lesson HTML',async({page})=>{
    const submissions=[];
    await page.route('**/mock/course/save',async route=>{
        submissions.push(JSON.parse(route.request().postData()));
        await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({
            id:7,version:2,status:'draft',message:'Campaign draft saved.',edit_url:'/up?id=7'
        })});
    });
    await mount(page);
    await page.getByRole('tab',{name:'Metadata (CEFR)'}).click();
    await page.locator('.cs-lesson[data-cs-active] select[data-field="type"]').selectOption('code');
    await page.locator('.cs-lesson[data-cs-active] select[data-field="language"]').selectOption('php');
    await page.locator('.cs-lesson[data-cs-active] [data-editor-code]').fill('<?php echo "<script>alert(1)</script>";');
    await page.locator('#cs-save-draft-panel').click();
    expect(submissions).toHaveLength(1);
    const body=submissions[0].rows[0].body;
    expect(body).toContain('<pre><code>');
    expect(body).toContain('&lt;script&gt;');
    expect(body).not.toContain('<script>');
    expect(submissions[0].rows[0].language).toBe('php');
});


test('module title can be renamed from metadata or outline and persists for all lessons',async({page})=>{
    const submissions=[];
    await page.route('**/mock/course/save',async route=>{
        submissions.push(JSON.parse(route.request().postData()));
        await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({
            id:7,version:2,status:'draft',message:'Campaign draft saved.',edit_url:'/up?id=7'
        })});
    });
    await mount(page);
    await expect(page.locator('#cs-selected-module-title')).toBeVisible();
    await expect(page.locator('#cs-selected-module-title')).toHaveValue('English Grammar');
    await expect(page.locator('.cs-module[data-cs-active] [data-field="module-title"]')).toHaveCount(1);
    await page.locator('#cs-selected-module-title').fill('Intensive IELTS Preparation');
    await expect(page.locator('[data-tree-module="0"] .cs-tree-unit-name')).toContainText('Intensive IELTS Preparation');
    await expect(page.locator('#cs-current-unit-heading')).toContainText('Intensive IELTS Preparation');
    await page.getByRole('tab',{name:'Curriculum'}).click();
    await expect(page.locator('#cs-selected-module-title')).toBeHidden();
    await page.locator('[data-cs-rename-module="0"]').click();
    await expect(page.locator('#cs-selected-module-title')).toBeFocused();
    await page.locator('#cs-selected-module-title').fill('Academic Writing & Speaking');
    await page.locator('#cs-save-draft-panel').click();
    expect(submissions).toHaveLength(1);
    expect(submissions[0].rows).toHaveLength(2);
    for(const row of submissions[0].rows)expect(row.course).toBe('Academic Writing & Speaking');
});
test('duplicating a lesson opens an explicitly named copy with preserved answers',async({page})=>{
    const submissions=[];
    await page.route('**/mock/course/save',async route=>{
        submissions.push(JSON.parse(route.request().postData()));
        await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({
            id:7,version:2,status:'draft',message:'Campaign draft saved.',edit_url:'/up?id=7'
        })});
    });
    await mount(page);
    await page.locator('.cs-lesson[data-cs-active] button[data-action="lesson-duplicate"]').click();
    await expect(page.locator('#cs-course-tree .cs-tree-lesson')).toHaveCount(3);
    await expect(page.locator('#cs-edit-title')).toHaveText('Present simple (copy)');
    await expect(page.locator('#cs-current-lesson-number')).toHaveText('LESSON 1.2');
    await page.getByRole('tab',{name:'Assessments'}).click();
    await expect(page.locator('.cs-lesson[data-cs-active] input[data-question-correct][value="1"]')).toBeChecked();
    await expect(page.locator('.cs-lesson[data-cs-active] [data-question-field="feedback"]')).toHaveValue('Third-person singular.');
    await page.locator('#cs-save-draft-panel').click();
    expect(submissions).toHaveLength(1);
    expect(submissions[0].rows).toHaveLength(3);
    expect(submissions[0].rows.map(r=>r.lesson)).toEqual(['Present simple','Present simple (copy)','Past tense']);
    expect(submissions[0].rows[1].questions[0].correct).toBe(1);
    expect(submissions[0].rows[1].questions[0].feedback).toBe('Third-person singular.');
});
test('media URL and uploaded asset preview only on active lesson, and are removed on navigation',async({page})=>{
    const asset='a'.repeat(64)+'.png';
    await page.route('**/mock/course/upload',route=>route.fulfill({
        status:200,contentType:'application/json',
        body:JSON.stringify({type:'image',asset,url:'https://cdn.example.org/ignored.png'})
    }));
    await page.route('**/campaign-media/**',route=>route.fulfill({
        status:200,contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB','base64')
    }));
    await mount(page);
    await page.getByRole('tab',{name:'Metadata (CEFR)'}).click();
    await page.locator('.cs-lesson[data-cs-active] select[data-field="type"]').selectOption('image');
    await page.locator('.cs-lesson[data-cs-active] [data-field="url"]').fill('https://cdn.example.org/lesson.png');
    await expect(page.locator('.cs-lesson[data-cs-active] [data-cs-lesson-media-preview] img'))
        .toHaveAttribute('src','https://cdn.example.org/lesson.png');
    await expect(page.locator('.cs-lesson[data-cs-active] [data-cs-lesson-media-preview]')).toBeVisible();
    await page.locator('.cs-lesson[data-cs-active] input[data-lesson-file]').setInputFiles({
        name:'lesson.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB','base64')
    });
    await expect(page.locator('.cs-lesson[data-cs-active] [data-cs-lesson-media-preview] img'))
        .toHaveAttribute('src',new RegExp('/campaign-media/'+asset));
    await page.locator('[data-cs-select-lesson="0-1"]').click();
    await expect(page.locator('[data-cs-lesson-media-preview] img')).toHaveCount(0);
    await expect(page.locator('#cs-edit-title')).toHaveText('Past tense');
    await page.getByRole('tab',{name:'Metadata (CEFR)'}).click();
    await page.locator('.cs-lesson[data-cs-active] select[data-field="type"]').selectOption('youtube');
    await page.locator('.cs-lesson[data-cs-active] [data-field="url"]')
        .fill('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    await expect(page.locator('.cs-lesson[data-cs-active] [data-cs-lesson-media-preview] iframe'))
        .toHaveAttribute('src','https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
    const navigationErrors=[];
    page.on('pageerror',error=>navigationErrors.push(String(error.stack || error)));
    await page.evaluate(()=>{
        window.__campaignNavEvents=[];
        const tree=document.getElementById('cs-course-tree');
        tree.addEventListener('click',event=>{
            window.__campaignNavEvents.push({
                selected:event.target.closest('[data-cs-select-lesson]')?.dataset.csSelectLesson || '',
                before:document.getElementById('cs-edit-title')?.textContent || '',
            });
        },true);
    });
    await page.locator('[data-cs-select-lesson="0-0"]').click();
    const debug=await page.evaluate(()=>({
        events:window.__campaignNavEvents,
        title:document.getElementById('cs-edit-title')?.textContent,
        selected:[...document.querySelectorAll('.cs-lesson[data-cs-active]')].map(e=>e.dataset.lessonIndex),
        treeSelected:[...document.querySelectorAll('#cs-course-tree [aria-current]')].map(e=>e.dataset.csSelectLesson),
        src:[...document.querySelectorAll('[data-cs-lesson-media-preview] iframe')].map(e=>e.src)
    }));
    console.log('CAMPAIGN_NAV_DIAGNOSTIC='+JSON.stringify({debug,navigationErrors}));
    await expect(page.locator('#cs-edit-title')).toHaveText('Present simple');
    await expect(page.locator('#cs-current-lesson-number')).toHaveText('LESSON 1.1');
    await expect(page.locator('[data-cs-lesson-media-preview] iframe')).toHaveCount(0);
});
test('blocks unsafe media URL schemes from preview',async({page})=>{
    await mount(page);
    await page.getByRole('tab',{name:'Curriculum'}).click();
    await page.locator('.cs-lesson[data-cs-active] [data-field="url"]').fill('javascript:alert(1)');
    await expect(page.locator('.cs-lesson[data-cs-active] [data-cs-lesson-media-preview] .cs-media-error'))
        .toContainText('valid HTTPS');
    await expect(page.locator('.cs-lesson[data-cs-active] [data-cs-lesson-media-preview] img')).toHaveCount(0);
    await expect(page.locator('.cs-lesson[data-cs-active] [data-cs-lesson-media-preview] iframe')).toHaveCount(0);
});
