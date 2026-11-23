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
        '<header><h1><span id="cs-header-title"></span></h1>',
        '<span id="cs-save-state"></span><button id="cs-save-draft-top"></button></header>',
        '<div id="cs-alert" hidden></div><button id="cs-publish-top" hidden></button>',
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
        '<div id="cs-course-stage"><div class="cs-course-campaign-metadata">',
        '<input id="cs-campaign-title" value="English Grammar">',
        '<select id="cs-course-type"><option value="quiz">Quiz</option><option value="exam">Exam</option></select></div>',
        '<h3 id="cs-current-unit-heading"></h3><span id="cs-current-content-type"></span>',
        '<div id="cs-modules"></div></div>',
        '<span id="cs-modules-count"></span><span id="cs-lessons-count"></span><span id="cs-questions-count"></span></main>',
        '<aside class="cs-sidebar"><div id="cs-readiness-mark"></div><span id="cs-readiness-label"></span>',
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
    await page.locator('[data-cs-toggle-unit="0"]').click();
    await expect(page.locator('[data-tree-module="0"] .cs-tree-lessons')).toBeHidden();
    await page.locator('[data-cs-toggle-unit="0"]').click();
    await expect(page.locator('[data-tree-module="0"] .cs-tree-lessons')).toBeVisible();
    await expect(page.locator('#cs-edit-title')).toHaveText('Present simple');
    await expect(page.locator('#cs-modules-count')).toHaveText('1');
    await expect(page.locator('#cs-lessons-count')).toHaveText('2');
    await expect(page.locator('#cs-questions-count')).toHaveText('2');
    await expect(page.locator('[data-cs-tab="curriculum"]')).toHaveAttribute('aria-selected','true');

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
