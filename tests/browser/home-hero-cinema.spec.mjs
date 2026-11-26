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
    await page.route('**/clip-*.mp4', route => route.fulfill({status:200,contentType:'video/webm',body:Buffer.from('GkXfo59ChoEBQveBAULygQRC84EIQoKEd2VibUKHgQJChYECGFOAZwEAAAAAAAJnEU2bdLpNu4tTq4QVSalmU6yBoU27i1OrhBZUrmtTrIHWTbuMU6uEElTDZ1OsggEjTbuMU6uEHFO7a1OsggJR7AEAAAAAAABZAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAVSalmsCrXsYMPQkBNgIxMYXZmNjEuNy4xMDNXQYxMYXZmNjEuNy4xMDNEiYhAp3AAAAAAABZUrmvIrgEAAAAAAAA/14EBc8WI6p6I8dTtfZOcgQAitZyDdW5kiIEAhoVWX1ZQOIOBASPjg4Q7msoA4JCwgSC6gSCagQJVsIRVuYEBElTDZ/tzc59jwIBnyJlFo4dFTkNPREVSRIeMTGF2ZjYxLjcuMTAzc3PWY8CLY8WI6p6I8dTtfZNnyKFFo4dFTkNPREVSRIeUTGF2YzYxLjE5LjEwMSBsaWJ2cHhnyKFFo4hEVVJBVElPTkSHkzAwOjAwOjAzLjAwMDAwMDAwMAAfQ7Z1QKjngQCjv4EAAIDQAgCdASogACAAAEcIhYWImYSIAgICdaoD+AIIISmecP79IUX/8Bf/gL/8Bf/+Av//fAn3gT7wJ/tsAKOwgQPoANEBAAEQEAAYAB5X9AwAQQ4A/v0hRf/wF/+Av/wF//4C//98CfeBPvAn+2wAo7CBB9AA0QEAARAQABgAHlf0DABBDgD+/SFF//AX/4C//AX//gL//3wJ94E+8Cf7bAAcU7trkbuPs4EAt4r3gQHxggGj8IED','base64')}));
    await page.addStyleTag({url:'/css/home-hero-media.css?v=3'});
    await page.addScriptTag({url:'/js/home-hero-playlist.js?v=3'});
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

test('actual media reaches ended, advances and loops with hidden navigation', async ({page}, info) => {
    await page.emulateMedia({reducedMotion:'no-preference'});
    await page.goto('/');
    const supported = await page.evaluate(() => !!document.createElement('video').canPlayType('video/webm; codecs="vp8"'));
    test.skip(!supported, 'Browser does not advertise VP8 decoding');
    const bytes = Buffer.from('GkXfo59ChoEBQveBAULygQRC84EIQoKEd2VibUKHgQJChYECGFOAZwEAAAAAAAJnEU2bdLpNu4tTq4QVSalmU6yBoU27i1OrhBZUrmtTrIHWTbuMU6uEElTDZ1OsggEjTbuMU6uEHFO7a1OsggJR7AEAAAAAAABZAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAVSalmsCrXsYMPQkBNgIxMYXZmNjEuNy4xMDNXQYxMYXZmNjEuNy4xMDNEiYhAp3AAAAAAABZUrmvIrgEAAAAAAAA/14EBc8WI6p6I8dTtfZOcgQAitZyDdW5kiIEAhoVWX1ZQOIOBASPjg4Q7msoA4JCwgSC6gSCagQJVsIRVuYEBElTDZ/tzc59jwIBnyJlFo4dFTkNPREVSRIeMTGF2ZjYxLjcuMTAzc3PWY8CLY8WI6p6I8dTtfZNnyKFFo4dFTkNPREVSRIeUTGF2YzYxLjE5LjEwMSBsaWJ2cHhnyKFFo4hEVVJBVElPTkSHkzAwOjAwOjAzLjAwMDAwMDAwMAAfQ7Z1QKjngQCjv4EAAIDQAgCdASogACAAAEcIhYWImYSIAgICdaoD+AIIISmecP79IUX/8Bf/gL/8Bf/+Av//fAn3gT7wJ/tsAKOwgQPoANEBAAEQEAAYAB5X9AwAQQ4A/v0hRf/wF/+Av/wF//4C//98CfeBPvAn+2wAo7CBB9AA0QEAARAQABgAHlf0DABBDgD+/SFF//AX/4C//AX//gL//3wJ94E+8Cf7bAAcU7trkbuPs4EAt4r3gQHxggGj8IED', 'base64');
    await page.route('**/hero-real-*.webm', async route => {
        const range = route.request().headers()['range'];
        const match = range?.match(/^bytes=(\d+)-(\d*)$/);
        if (match) {
            const start = Number(match[1]), end = Math.min(match[2] ? Number(match[2]) : bytes.length-1, bytes.length-1);
            if (start > end) return route.fulfill({status:416,headers:{'Content-Range':'bytes */'+bytes.length}});
            return route.fulfill({status:206,contentType:'video/webm',headers:{'Accept-Ranges':'bytes','Content-Range':'bytes '+start+'-'+end+'/'+bytes.length,'Content-Length':String(end-start+1)},body:bytes.subarray(start,end+1)});
        }
        return route.fulfill({status:200,contentType:'video/webm',headers:{'Accept-Ranges':'bytes','Content-Length':String(bytes.length)},body:bytes});
    });
    await page.evaluate(() => {
        document.body.innerHTML = '<div style="width:min(640px,100%)" class="home-hero-media" data-home-hero-media="playlist"><div class="hhp hhp-cinema" data-hero-playlist data-admin-preview="false"><video playsinline muted></video><div class="hhp-overlay"><button class="hhp-glass" data-hhp-play><svg><path data-hhp-play-icon/><path data-hhp-pause-icon/></svg></button><button class="hhp-glass" data-hhp-mute hidden><svg><path data-hhp-muted-icon/><path data-hhp-sound-icon/></svg></button><p data-hhp-status hidden></p></div></div></div>';
        const root = document.querySelector('[data-hero-playlist]');
        root.dataset.clips = JSON.stringify([{url:'/hero-real-1.webm',name:'1'},{url:'/hero-real-2.webm',name:'2'}]);
        root.dataset.options = JSON.stringify({autoplay:true,muted:true,advance:true,loop:true,controls:false,navigation:false});
        document.documentElement.dataset.dataSaver='false';
        window.heroEnded = [];
        root.querySelector('video').addEventListener('ended', e=>window.heroEnded.push(e.target.getAttribute('src')));
    });
    await page.addStyleTag({url:'/css/home-hero-media.css?v=3'});
    await page.addScriptTag({url:'/js/home-hero-playlist.js?v=3'});
    const player = page.locator('.hhp-cinema');
    await expect(player).toHaveAttribute('data-autoplay','enabled');
    await expect.poll(()=>page.evaluate(()=>window.heroEnded.slice(0,2)),{timeout:20000}).toEqual(['/hero-real-1.webm','/hero-real-2.webm']);
    await expect(player).toHaveAttribute('data-clip-index','1');
    await expect(player.locator('[data-hhp-next]')).toHaveCount(0);
    if (await page.evaluate(()=>matchMedia('(hover:hover)').matches)) {
        await player.hover();
        await expect.poll(()=>player.evaluate(el=>getComputedStyle(el,'::after').animationName)).toBe('hhp-sheen');
    }
});
