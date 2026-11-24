import { test, expect } from '@playwright/test';

test('homepage hero runs quiz inline without navigation', async ({page}) => {
  const answers=[];
  await page.route('**/up', route => route.fulfill({status:200,contentType:'text/html',body:'ok'}));
  await page.route('**/mock/play', route => route.fulfill({
    status:200,contentType:'application/json',body:JSON.stringify({
      title:'English Grammar Practice',kind:'quiz',questions:[
        {row:0,question:0,module:'Grammar',lesson:'Present simple',body:'<p>Fill the gap.</p>',prompt:'She ___ every day.',options:['go','goes','going']},
        {row:0,question:1,module:'Grammar',lesson:'Present simple',body:'',prompt:'They ___ every day.',options:['walk','walks']}
      ]
    })
  }));
  await page.route('**/mock/check',async route=>{
    answers.push(JSON.parse(route.request().postData()));
    const answer=answers[answers.length-1];
    const correct=answer.question===0?answer.answer===1:answer.answer===0;
    await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({
      correct,answer:answer.question===0?'goes':'walk',
      feedback:correct?'Correct grammar!':'Review the third-person rule.'
    })});
  });
  await page.goto('/up');
  await page.setContent([
    '<html><head><meta name="csrf-token" content="test-token"></head><body>',
    '<div id="hc-hero" data-csrf="test-token">',
    '<div id="hc-chooser"><button data-hc-start data-play-url="/mock/play" data-check-url="/mock/check">English Grammar Practice</button></div>',
    '<div id="hc-play" hidden><button id="hc-back">Back</button><span id="hc-progress"></span>',
    '<h3 id="hc-title"></h3><p id="hc-lesson"></p><div id="hc-question"></div>',
    '<div id="hc-feedback" hidden></div><button id="hc-next" hidden>Next question</button>',
    '<button id="hc-finish" hidden>All campaigns</button></div><div id="hc-error" hidden></div>',
    '</div></body></html>'
  ].join(''));
  await page.addScriptTag({path:'public/js/homepage-hero-campaigns.js'});
  await page.getByRole('button',{name:'English Grammar Practice'}).click();
  await expect(page.locator('#hc-title')).toHaveText('English Grammar Practice');
  await expect(page.locator('#hc-question .hc-prompt')).toHaveText('Q1. She ___ every day.');
  await expect(page.locator('#hc-progress')).toHaveText('1 / 2');
  await expect(page).toHaveURL(/\/up$/);
  await page.locator('input[name="hc-answer"][value="1"]').check();
  await page.getByRole('button',{name:'Check answer'}).click();
  await expect(page.locator('#hc-feedback')).toContainText('Correct grammar!');
  await page.getByRole('button',{name:'Next question'}).click();
  await expect(page.locator('#hc-question .hc-prompt')).toHaveText('Q2. They ___ every day.');
  await page.locator('input[name="hc-answer"][value="0"]').check();
  await page.getByRole('button',{name:'Check answer'}).click();
  await expect(page.locator('#hc-feedback')).toContainText('Your score: 2 / 2');
  await page.getByRole('button',{name:'All campaigns'}).click();
  await expect(page.locator('#hc-chooser')).toBeVisible();
  expect(answers).toHaveLength(2);
  expect(answers[0]).toMatchObject({row:0,question:0,answer:1});
});

test('SVG editor toolbar controls have accessible labels', async ({page}) => {
  await page.route('**/up',route=>route.fulfill({status:200,body:'ok',contentType:'text/html'}));
  await page.goto('/up');
  await page.setContent([
    '<html><head><meta name="csrf-token" content="fixture"></head><body>',
    '<section id="cs-builder" data-save-url="/mock/save" data-upload-url="/mock/upload"',
    ' data-campaign-id="" data-version="0" data-cover-asset="" data-cover-kind="quiz">',
    '<input id="cs-campaign-title" value="My campaign"><span id="cs-save-state"></span>',
    '<div id="cs-alert" hidden></div><button id="cs-save-draft-top"></button>',
    '<button id="cs-publish-top"></button><button id="cs-save-draft"></button><button id="cs-publish"></button>',
    '<button id="cs-add-module"></button><span id="cs-modules-count"></span>',
    '<span id="cs-lessons-count"></span><span id="cs-questions-count"></span>',
    '<div id="cs-modules"></div><div id="cs-outline"></div>',
    '<input id="cs-cover-file" type="file"><select id="cs-cover-kind"><option value="quiz">Quiz</option></select>',
    '<img id="cs-cover-image" hidden><div id="cs-cover-placeholder"></div>',
    '<button id="cs-remove-cover" hidden></button><p id="cs-cover-message"></p>',
    '<script id="cs-initial-rows" type="application/json">[]</script>',
    '</section></body></html>'
  ].join(''));
  await page.addScriptTag({path:'public/js/campaign-studio-premium.js'});
  await expect(page.locator('.cs-editor-toolbar button svg')).toHaveCount(28);
  await expect(page.getByRole('button',{name:'Bold'})).toBeVisible();
  await expect(page.getByRole('button',{name:'Underline'})).toBeVisible();
  await expect(page.getByRole('button',{name:'HTML source'})).toBeVisible();
});
