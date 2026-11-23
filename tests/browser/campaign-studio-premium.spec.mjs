import { test, expect } from '@playwright/test';

const initial = [
  {
    course: 'English Grammar',
    lesson: 'Present simple',
    type: 'quiz',
    body: '<p>Choose the correct verb.</p>',
    feedback: 'A routine action',
    questions: [
      {
        prompt: 'She ___ to school every day.',
        options: ['go', 'goes', 'going'],
        correct: 1,
        feedback: 'Third person singular needs -s.'
      }
    ]
  },
  {
    course: 'English Grammar',
    lesson: 'Past simple',
    type: 'exam',
    body: '<p>Use the correct form.</p>',
    questions: [
      {
        prompt: 'Yesterday, they ___ to the market.',
        options: ['go', 'went'],
        correct: 1,
        feedback: 'Went is the past tense of go.'
      }
    ]
  },
];

function fixture() {
  return [
    '<!doctype html><html><head><meta name="csrf-token" content="fixture-csrf"></head><body>',
    '<section id="cs-builder" data-save-url="/mock-campaign-save" data-upload-url="/mock-media"',
    ' data-campaign-id="" data-asset-base="/campaign-media" data-version="4" data-cover-asset="" data-cover-kind="quiz">',
    '<input id="cs-campaign-title" value="English Grammar Practice">',
    '<span id="cs-save-state"></span><div id="cs-alert" hidden></div>',
    '<button id="cs-save-draft-top">Save draft</button><button id="cs-publish-top">Publish</button>',
    '<button id="cs-save-draft">Save draft</button><button id="cs-publish">Publish</button>',
    '<button id="cs-add-module">Add module</button>',
    '<span id="cs-modules-count"></span><span id="cs-lessons-count"></span><span id="cs-questions-count"></span>',
    '<div id="cs-modules"></div><div id="cs-outline"></div>',
    '<input id="cs-cover-file" type="file"><select id="cs-cover-kind"><option value="quiz">Quiz</option><option value="exam">Exam</option></select>',
    '<img id="cs-cover-image" hidden><div id="cs-cover-placeholder"></div><button id="cs-remove-cover" hidden>Remove cover</button>',
    '<p id="cs-cover-message"></p>',
    '<script id="cs-initial-rows" type="application/json">',
    JSON.stringify(initial),
    '</script></section></body></html>',
  ].join('');
}

test('campaign studio preserves lessons, allows collapsible modules, and numbers questions', async ({ page }) => {
  await page.route('**/up', route => route.fulfill({ status: 200, contentType:'text/html', body:'OK' }));
  await page.goto('/up');
  await page.setContent(fixture());
  await page.addScriptTag({ path: 'public/js/campaign-studio-premium.js' });

  await expect(page.locator('.cs-module')).toHaveCount(1);
  await expect(page.locator('.cs-lesson')).toHaveCount(2);
  await expect(page.locator('[data-question-number]')).toHaveText(['Q1', 'Q2']);
  await expect(page.locator('#cs-questions-count')).toHaveText('2');

  const firstQuestion=page.locator('.cs-quiz-card').first();
  await expect(firstQuestion.locator('input[type=radio][value="1"]')).toBeChecked();
  await firstQuestion.locator('input[data-option-index="0"]').fill('walk');
  await expect(firstQuestion.locator('input[type=radio][value="1"]')).toBeChecked();

  await page.locator('[data-action="add-question"]').first().click();
  await expect(page.locator('[data-question-number]')).toHaveText(['Q1','Q2','Q3']);
  await expect(page.locator('#cs-questions-count')).toHaveText('3');

  const module=page.locator('details.cs-module').first();
  await module.locator(':scope > summary').click();
  await expect(module).not.toHaveAttribute('open');
  await module.locator(':scope > summary').click();
  await expect(module).toHaveAttribute('open', '');

  await page.locator('#cs-add-module').click();
  await expect(page.locator('.cs-module')).toHaveCount(2);
  await expect(page.locator('.cs-lesson')).toHaveCount(3);
});

test('campaign studio draft submission keeps correct answer keys and explanations', async ({ page }) => {
  const submissions=[];
  await page.route('**/up', route => route.fulfill({ status: 200, contentType:'text/html', body:'OK' }));
  await page.goto('/up');
  await page.route('**/mock-campaign-save', async route => {
    submissions.push(JSON.parse(route.request().postData()));
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({version:5,message:'Campaign draft saved.'}) });
  });
  await page.setContent(fixture());
  await page.addScriptTag({ path: 'public/js/campaign-studio-premium.js' });

  await page.locator('#cs-save-draft').click();
  await expect(page.locator('#cs-save-state')).toHaveText('Campaign draft saved.');
  expect(submissions).toHaveLength(1);
  expect(submissions[0].action).toBe('draft');
  expect(submissions[0].title).toBe('English Grammar Practice');
  expect(submissions[0].kind).toBe('quiz');
  expect(submissions[0].version).toBe(4);
  expect(submissions[0].rows).toHaveLength(2);
  expect(submissions[0].rows[0].questions[0].correct).toBe(1);
  expect(submissions[0].rows[0].questions[0].feedback).toBe('Third person singular needs -s.');
});
