/* MCP Management dialog: presentation only; no requests, credentials or writes. */
(()=>{
    'use strict';
    const root=document.querySelector('[data-mcp-manager]');
    if(!root)return;
    const dialog=root.querySelector('#mcp-tool-dialog');
    if(!dialog || typeof dialog.showModal!=='function')return;
    const title=root.querySelector('#mcp-dialog-title');
    const id=root.querySelector('#mcp-detail-id');
    const scope=root.querySelector('#mcp-detail-scope');
    const risk=root.querySelector('#mcp-detail-risk');
    const summary=root.querySelector('#mcp-detail-summary');
    root.addEventListener('click',event=>{
        const button=event.target.closest('[data-mcp-tool-details]');
        if(button && root.contains(button)){
            title.textContent=button.dataset.title||'Tool details';
            id.textContent=button.dataset.name||'';
            scope.textContent=button.dataset.scope||'';
            risk.textContent=button.dataset.risk||'';
            summary.textContent=button.dataset.summary||'';
            dialog.showModal();
            return;
        }
        if(event.target.closest('[data-mcp-dialog-close]')){
            dialog.close();
        }
    });
    dialog.addEventListener('click',event=>{
        if(event.target===dialog)dialog.close();
    });
    const browserSection=root.querySelector('.mcp-browser-section');
    const authDialog=root.querySelector('#mcp-browser-auth-dialog');
    if(!browserSection||!authDialog)return;
    const authForm=root.querySelector('#mcp-browser-auth-form');
    const issued=root.querySelector('#mcp-browser-issued');
    const tokenInput=root.querySelector('#mcp-issued-token');
    const feedback=root.querySelector('#mcp-browser-feedback');
    const csrf=document.querySelector('meta[name="csrf-token"]')?.content || '';
    const issueUrl=browserSection.dataset.mcpIssueUrl;
    const revokePrefix=browserSection.dataset.mcpRevokePrefix;
    const showMessage=msg=>{feedback.textContent=String(msg||'');};
    function clearSecret(){
        tokenInput.value='';
        issued.hidden=true;
        authForm.hidden=false;
        root.querySelector('#mcp-consent-accept').checked=false;
        showMessage('');
    }
    root.addEventListener('click',async event=>{
        if(event.target.closest('[data-mcp-open-auth]')){
            clearSecret();
            authDialog.showModal();
            root.querySelector('#mcp-client-name').focus();
            return;
        }
        if(event.target.closest('[data-mcp-auth-close]')){
            clearSecret();
            authDialog.close();
            return;
        }
        if(event.target.closest('[data-mcp-copy-token]')){
            try{
                await navigator.clipboard.writeText(tokenInput.value);
                showMessage('Token copied. Protect this credential and revoke it when no longer needed.');
            }catch(_error){
                tokenInput.select();
                showMessage('Clipboard unavailable. Copy the selected token manually.');
            }
            return;
        }
        const revoke=event.target.closest('[data-mcp-revoke]');
        if(!revoke)return;
        if(!window.confirm('Revoke this MCP session immediately? This cannot be undone.'))return;
        revoke.disabled=true;
        try{
            const response=await fetch(revokePrefix+'/'+encodeURIComponent(revoke.dataset.mcpRevoke),{
                method:'DELETE',credentials:'same-origin',
                headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf}
            });
            if(!response.ok)throw new Error('Revoke failed (HTTP '+response.status+').');
            const row=revoke.closest('tr');row?.remove();
            showMessage('MCP session revoked.');
        }catch(error){
            showMessage(error.message);
            revoke.disabled=false;
        }
    });
    authDialog.addEventListener('close',clearSecret);
    authForm.addEventListener('submit',async event=>{
        event.preventDefault();
        if(!authForm.reportValidity())return;
        const chosen=[...authForm.querySelectorAll('input[name="scopes[]"]:checked')].map(node=>node.value);
        if(!chosen.length){showMessage('Choose at least one permission.');return}
        if(!root.querySelector('#mcp-consent-accept').checked){
            showMessage('Confirm the permission statement before connecting.');return;
        }
        const submit=authForm.querySelector('[data-mcp-connect-submit]');
        submit.disabled=true;showMessage('Verifying administrator approval…');
        try{
            const response=await fetch(issueUrl,{
                method:'POST',credentials:'same-origin',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
                body:JSON.stringify({
                    client_name:root.querySelector('#mcp-client-name').value,
                    scopes:chosen,
                    confirmation:'CONNECT',
                    expires_in_minutes:Number(root.querySelector('#mcp-expiry').value),
                }),
            });
            if(response.redirected && response.url.includes('/security/confirm-password')){
                showMessage('Re-enter your website password first. Opening the password confirmation page.');
                window.location.assign(response.url);
                return;
            }
            const body=await response.json().catch(()=>({}));
            if(!response.ok){
                const errors=Object.values(body.errors||{}).flat();
                throw new Error(errors[0]||body.message||('Authorization refused (HTTP '+response.status+').'));
            }
            if(!body.access_token)throw new Error('Server did not return a one-time credential.');
            tokenInput.value=body.access_token;
            authForm.hidden=true;issued.hidden=false;
            showMessage('Session '+body.session_id+' created. Token expires at '+body.expires_at+'.');
            const empty=root.querySelector('[data-mcp-empty]');
            empty?.remove();
            const tbody=root.querySelector('[data-mcp-sessions]');
            const row=document.createElement('tr');
            row.dataset.mcpSessionId=String(body.session_id);
            const label=document.createElement('td');
            label.textContent='LearnCambodia MCP: '+root.querySelector('#mcp-client-name').value;
            const scope=document.createElement('td');
            scope.textContent=(body.scopes||[]).join(', ');
            const expires=document.createElement('td');
            expires.textContent=String(body.expires_at||'');
            const used=document.createElement('td');used.textContent='Never';
            const action=document.createElement('td');
            const button=document.createElement('button');
            button.type='button';button.className='mcp-revoke-button';
            button.dataset.mcpRevoke=String(body.session_id);
            button.textContent='Revoke';
            action.appendChild(button);
            row.append(label,scope,expires,used,action);
            tbody?.prepend(row);
        }catch(error){
            showMessage(error.message);
        }finally{
            submit.disabled=false;
        }
    });
})();
