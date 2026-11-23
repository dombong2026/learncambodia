#!/usr/bin/env bash
# Static acceptance for the copy-deployment Course Player overlay (no production writes).
set -Eeuo pipefail
cd "$(git rev-parse --show-toplevel)"
python3 - <<'PY'
from pathlib import Path
import hashlib, re, sys

root=Path('.')
release=(root/'support/operator/learncambodia-course-player-release-aapanel.sh').read_text()
match=re.search(r"MANIFEST=\$\(cat <<'RELEASE_FILES'\n(.*?)\nRELEASE_FILES\n",release,re.S)
assert match, 'Release manifest missing'
rows=[line.split(' ',2) for line in match.group(1).splitlines()]
assert len(rows)==11, f'Expected 11 release paths, got {len(rows)}'
for oldsha,newsha,path in rows:
    target=root/'support/operator/staged-20261123'/path
    assert target.is_file(), 'Staged source missing: '+path
    data=target.read_bytes()
    digest=hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()
    assert digest==newsha, f'Invalid new Git blob for {path}: {digest}'
    if oldsha!='-':
        previous=root/'support/operator/live-20261123'/path
        assert previous.is_file(), 'Original source missing: '+path
        data=previous.read_bytes()
        digest=hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()
        assert digest==oldsha, f'Invalid original Git blob for {path}'
print('Release manifest and original source SHAs: passed')

old=(root/'support/operator/live-20261123/resources/views/home.blade.php').read_text()
new=(root/'support/operator/staged-20261123/resources/views/home.blade.php').read_text()
markers=['lc-home-hero-copy','images/home/hero-cutout.webp',
         "promo-player.placement", 'practice-exams','practice-quizzes',
         'home-courses','home-cefr','home-articles','home-educators']
for mark in markers:
    assert mark in old and mark in new, 'Live homepage feature was not preserved: '+mark
assert new.count("@include('partials.home-course-player-hero')")==1
assert "@if(($hero['hero_mode'] ?? 'default') === 'course_player' && $heroCourses->isNotEmpty())" in new
assert "asset('js/home-course-hero.js')" in new
assert "asset('css/home-course-hero.css')" in new
app=(root/'support/operator/staged-20261123/app/Support/Appearance.php').read_text()
assert "foreach (['hero_title', 'hero_intro'] as $key)" in app, 'Non-string home defaults may be translated accidentally'
assert "'hero_course_id' => null" in app
player=(root/'support/operator/staged-20261123/resources/views/partials/home-course-player-hero.blade.php').read_text()
assert "@if($lesson->is_preview)" in player, 'Course lesson visibility guard missing'
assert "data-home-course-screen" in player and 'data-home-course-outline' in player
assert "data-home-lesson-choice" in player
assert 'aspect-ratio: 16/9' in (root/'support/operator/staged-20261123/public/css/home-course-hero.css').read_text()
print('Default home preservation, accessibility and preview guards: passed')
PY

for path in \
  support/operator/staged-20261123/app/Support/Appearance.php \
  support/operator/staged-20261123/app/Http/Controllers/CatalogController.php \
  support/operator/staged-20261123/app/Http/Controllers/Workspace/AdminAppearanceController.php \
  support/operator/staged-20261123/lang/en/home_hero.php \
  support/operator/staged-20261123/lang/km/home_hero.php \
  support/operator/staged-20261123/lang/zh/home_hero.php
do
  php -l "$path" >/dev/null
done
node --check support/operator/staged-20261123/public/js/home-course-hero.js
bash -n support/operator/learncambodia-course-player-release-aapanel.sh
bash -n support/operator/learncambodia-course-player-rollback-aapanel.sh
echo 'PHP syntax, JavaScript syntax and operator scripts: passed'
