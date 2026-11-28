for (const player of document.querySelectorAll('[data-campaign-player]')) {
    const slides = Array.from(player.querySelectorAll('button[data-media]'));
    const screen = player.querySelector('[data-campaign-screen]');
    let active = 0, start;
    function select(index) {
        if (!slides.length) return;
        index = (index + slides.length) % slides.length;
        if (index === active) return;
        active = index;
        const row = slides[index];
        screen.querySelector('video')?.pause();
        screen.replaceChildren(row.nextElementSibling.content.cloneNode(true));
        row.closest('details').open=true;
        slides.forEach((b,i)=>b.setAttribute('aria-pressed',String(i===index)));
        const output=player.querySelector('output');if(output)output.textContent=(index+1)+' / '+slides.length;
    }
    screen.addEventListener('submit', async event=>{
        const form=event.target.closest('[data-assessment]');
        if(!form)return;
        event.preventDefault();
        const fields=Array.from(form.querySelectorAll('fieldset'));
        const answers=fields.map(field=>Number(field.querySelector(':checked').value));
        const submit=form.querySelector('[type=submit]'), score=form.querySelector('[data-score]');
        submit.disabled=true;
        try {
            const response=await fetch(form.action,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':player.dataset.token},body:JSON.stringify({key:form.dataset.key,answers})});
            if(!response.ok)throw Error(response.status===409?'This lesson changed. Reload to try it again.':'Unable to check answers. Please retry.');
            const result=await response.json();
            result.results.forEach((item,i)=>{fields[i].querySelector('[data-result]').textContent=(item.correct?'Correct. ':'Correct answer: '+item.answer+'. ')+item.feedback;});
            score.textContent='Score: '+result.score+' / '+result.total;
        }catch(e){score.textContent=e.message;}finally{submit.disabled=false;}
    });
    player.addEventListener('click',event=>{
        const step=event.target.closest('[data-step]'), row=event.target.closest('[data-media]');
        if(step)select(active+Number(step.dataset.step));else if(row)select(slides.indexOf(row));
    });
    player.addEventListener('keydown',event=>{
        if(event.target.tagName!=='BUTTON')return;
        if(event.key==='ArrowRight'||event.key==='ArrowLeft'){event.preventDefault();select(active+(event.key==='ArrowRight'?1:-1));}
    });
    screen.addEventListener('touchstart',e=>{start=[e.touches[0].clientX,e.touches[0].clientY];},{passive:true});
    screen.addEventListener('touchend',e=>{if(!start)return;const dx=e.changedTouches[0].clientX-start[0],dy=e.changedTouches[0].clientY-start[1];if(Math.abs(dx)>50&&Math.abs(dx)>2*Math.abs(dy))select(active+(dx<0?1:-1));start=null;},{passive:true});
}
