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
})();
