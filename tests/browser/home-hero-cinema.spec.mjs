import { expect, test } from '@playwright/test';

test('cinema frame stays 16:9, has no native controls and advances and loops', async ({page}) => {
    await page.emulateMedia({reducedMotion:'no-preference'});
    await page.goto('/');
    // Exercise the actual deployed standalone JS and stylesheet against deterministic
    // media events, without depending on external videos or codecs.
    await page.evaluate(() => {
        const videoProto = HTMLMediaElement.prototype;
        Object.defineProperty(videoProto, 'paused', {configurable:true,get(){return this.dataset.mockPlaying !== 'true';}});
        videoProto.play = function(){this.dataset.mockPlaying='true';this.dispatchEvent(new Event('playing'));return Promise.resolve();};
        videoProto.pause = function(){this.dataset.mockPlaying='false';this.dispatchEvent(new Event('pause'));};
        videoProto.load = function(){this.dataset.mockPlaying='false';this.dispatchEvent(new Event('loadeddata'));};
        document.body.innerHTML = '<div style="width:min(640px,100%)" class="home-hero-media" data-home-hero-media="playlist"><div class="hhp hhp-cinema" data-hero-playlist data-admin-preview="false"><video playsinline></video><div class="hhp-overlay"><div class="hhp-actions"><button class="hhp-glass" data-hhp-play><svg><path data-hhp-play-icon/><path data-hhp-pause-icon/></svg></button><button class="hhp-glass" data-hhp-mute><svg><path data-hhp-muted-icon/><path data-hhp-sound-icon/></svg></button></div><button class="hhp-glass hhp-prev" data-hhp-prev>Previous</button><button class="hhp-glass hhp-next" data-hhp-next>Next</button><span data-hhp-position></span><p data-hhp-status hidden></p></div></div></div>';
        const root = document.querySelector('[data-hero-playlist]');
        root.dataset.clips = JSON.stringify([{url:'/clip-1.mp4',name:'1'},{url:'/clip-2.mp4',name:'2'}]);
        root.dataset.options = JSON.stringify({autoplay:true,muted:true,advance:true,loop:true,controls:true,navigation:true});
        document.documentElement.dataset.dataSaver='false';
    });
    await page.route('**/clip-*.mp4', route => route.abort());
    await page.addStyleTag({url:'/css/home-hero-media.css?v=2'});
    await page.addScriptTag({url:'/js/home-hero-playlist.js?v=2'});
    const player=page.locator('.hhp-cinema');
    const video=player.locator('video');
    const bounds=await player.boundingBox();
    expect(Math.abs(bounds.width/bounds.height - 16/9)).toBeLessThan(.02);
    expect(await video.evaluate(el=>el.controls)).toBe(false);
    expect(await video.evaluate(el=>el.muted)).toBe(true);
    await expect(player).toHaveAttribute('data-playing','true');
    await video.evaluate(el=>el.dispatchEvent(new Event('ended')));
    await expect(video).toHaveAttribute('src','/clip-2.mp4');
    await video.evaluate(el=>el.dispatchEvent(new Event('ended')));
    await expect(video).toHaveAttribute('src','/clip-1.mp4');
    await player.locator('[data-hhp-next]').focus();
    await player.locator('[data-hhp-next]').click();
    await expect(video).toHaveAttribute('src','/clip-2.mp4');
    await player.locator('[data-hhp-play]').focus();
    await player.locator('[data-hhp-play]').click();
    await expect(player).toHaveAttribute('data-playing','false');
});
