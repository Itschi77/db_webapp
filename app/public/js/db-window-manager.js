(() => {
    const isHost = window.top === window && location.pathname === '/';
    const eligiblePath = p => p === '/auftraege' || p.startsWith('/accounting-berichte') || p.startsWith('/anbindungen') || p.startsWith('/netze') || p.startsWith('/ports') || p.startsWith('/kunden') || p === '/dokumentation' || p === '/handbuch' || p === '/sql-wiki' || p.startsWith('/rechnungen') || p === '/wiedervorlagen' || p === '/fremdaccounting' || p.startsWith('/datev') || p.startsWith('/domainkonditionen') || p.startsWith('/rechnungslauf') || p.startsWith('/produkte') || p.startsWith('/staffelgruppen') || p.startsWith('/sms-zugaenge') || p.startsWith('/linearstaffeln') || p.startsWith('/bandbreitentarife') || p.startsWith('/bereichsstaffeln') || p.startsWith('/zeittarife') || p.startsWith('/branchen-auswertung') || p.startsWith('/rechnungen-ohne-ust') || p.startsWith('/lastschriften');
    const navWords = /^(←\s*)?(zurück|abbrechen|hauptmenü|zum kunden|aufträge)$/i;
    const sameOriginUrl = href => { try { const u = new URL(href, location.href); return u.origin === location.origin ? u : null; } catch { return null; } };

    function shouldOpenLink(a) {
        if (!a || a.dataset.dbInline === '1' || a.target === '_blank' || a.hasAttribute('download')) return false;
        const u = sameOriginUrl(a.href); if (!u || !eligiblePath(u.pathname)) return false;
        const text = (a.textContent || '').trim();
        return !navWords.test(text);
    }

    function interceptLinks() {
        document.addEventListener('click', e => {
            const a = e.target.closest('a[href]'); if (!shouldOpenLink(a)) return;
            const opener = window.top !== window && window.parent.openDbWindow ? window.parent : (isHost ? window : null);
            if (!opener) return;
            e.preventDefault();
            opener.openDbWindow(a.href, (a.dataset.windowTitle || a.textContent || 'Fenster').trim());
        });
    }

    if (!isHost) { interceptLinks(); return; }

    let z = 1000, cascade = 0;
    const windows = new Map();
    const stateKey = 'dbapp-window-state-v1';
    const readState = () => { try { return JSON.parse(localStorage.getItem(stateKey) || '[]'); } catch { return []; } };
    const saveState = () => {
        try {
            const data = [...windows.values()].map(x => ({href:x.frame.src,title:x.task.textContent,left:x.w.style.left,top:x.w.style.top,width:x.w.style.width,height:x.w.style.height,min:x.w.style.display==='none',max:x.w.classList.contains('max')}));
            localStorage.setItem(stateKey, JSON.stringify(data));
        } catch {}
    };
    const style = document.createElement('style');
    style.textContent = `
      #db-window-layer{position:fixed;inset:0;pointer-events:none;z-index:900}
      .db-win{position:absolute;width:min(980px,82vw);height:min(720px,78vh);min-width:420px;min-height:260px;background:#eee;border:1px solid #666;box-shadow:0 8px 30px #0005;resize:both;overflow:hidden;pointer-events:auto}
      .db-win.active{box-shadow:0 10px 36px #0008}.db-win.max{left:8px!important;top:8px!important;width:calc(100vw - 16px)!important;height:calc(100vh - 62px)!important;resize:none}
      .db-winbar{height:34px;background:#f6f6f6;border-bottom:1px solid #aaa;display:flex;align-items:center;padding:0 7px;cursor:move;user-select:none}
      .db-wintitle{flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font:13px "Segoe UI",Arial,sans-serif}
      .db-winbtn{width:31px;height:26px;border:0;background:transparent;cursor:pointer;font-size:15px}.db-winbtn:hover{background:#ddd}.db-winbtn.close:hover{background:#c42b1c;color:white}
      .db-frame{width:100%;height:calc(100% - 34px);border:0;background:white;display:block}
      #db-taskbar{position:fixed;left:0;right:0;bottom:0;height:46px;z-index:3000;background:#ececec;border-top:1px solid #999;display:flex;align-items:center;gap:5px;padding:5px 8px;overflow-x:auto}
      .db-task{max-width:240px;height:34px;padding:0 12px;border:1px solid #999;background:#f7f7f7;cursor:pointer;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
    `;
    document.head.appendChild(style);
    const layer = document.createElement('div'); layer.id = 'db-window-layer'; document.body.appendChild(layer);
    const taskbar = document.createElement('div'); taskbar.id = 'db-taskbar'; document.body.appendChild(taskbar);
    document.body.style.paddingBottom = '54px';

    function focusWin(w) { document.querySelectorAll('.db-win').forEach(x=>x.classList.remove('active')); w.classList.add('active'); w.style.zIndex=++z; }
    function removeWin(key,w,task){ windows.delete(key); task.remove(); w.remove(); saveState(); }
    function toggleMin(w,task){ const hidden=w.style.display==='none'; w.style.display=hidden?'block':'none'; task.style.fontWeight=hidden?'700':'400'; if(hidden) focusWin(w); saveState(); }

    window.openDbWindow = (href, title='Fenster', opts={}) => {
        const u = new URL(href, location.href); const key = u.pathname + u.search;
        if (windows.has(key)) { const x=windows.get(key); x.w.style.display='block'; focusWin(x.w); return x.w; }
        const w=document.createElement('section'); w.className='db-win';
        const left=24+(cascade%8)*34, top=22+(cascade%7)*28; cascade++;
        const isConnections = u.pathname.startsWith('/anbindungen');
        const defaultWidth = isConnections ? 'min(1120px,94vw)' : null;
        const defaultHeight = isConnections ? 'min(860px,90vh)' : null;
        w.style.left=opts.left||left+'px';
        w.style.top=opts.top||top+'px';
        w.style.width=opts.width||defaultWidth||'';
        w.style.height=opts.height||defaultHeight||'';
        const bar=document.createElement('div');bar.className='db-winbar';
        const t=document.createElement('div');t.className='db-wintitle';t.textContent=title;
        const min=document.createElement('button');min.className='db-winbtn';min.type='button';min.title='Minimieren';min.textContent='—';
        const max=document.createElement('button');max.className='db-winbtn';max.type='button';max.title='Maximieren';max.textContent='□';
        const close=document.createElement('button');close.className='db-winbtn close';close.type='button';close.title='Schließen';close.textContent='×';
        bar.append(t,min,max,close); const frame=document.createElement('iframe');frame.className='db-frame';frame.src=u.href;frame.name='dbwin_'+Date.now();
        w.append(bar,frame);layer.appendChild(w);
        const task=document.createElement('button');task.className='db-task';task.type='button';task.textContent=title;taskbar.appendChild(task);
        windows.set(key,{w,task,frame}); focusWin(w);
        w.addEventListener('mousedown',()=>focusWin(w)); task.onclick=()=>toggleMin(w,task); min.onclick=()=>toggleMin(w,task); max.onclick=()=>{w.classList.toggle('max');focusWin(w);saveState()}; close.onclick=()=>removeWin(key,w,task);
        if(opts.max) w.classList.add('max'); if(opts.min){w.style.display='none';task.style.fontWeight='700';}
        let drag=null; bar.addEventListener('mousedown',e=>{if(e.target.closest('button')||w.classList.contains('max'))return;drag={x:e.clientX,y:e.clientY,l:w.offsetLeft,t:w.offsetTop};e.preventDefault();});
        document.addEventListener('mousemove',e=>{if(!drag)return;w.style.left=Math.max(0,drag.l+e.clientX-drag.x)+'px';w.style.top=Math.max(0,drag.t+e.clientY-drag.y)+'px';});
        document.addEventListener('mouseup',()=>{if(drag)saveState();drag=null});
        if(window.ResizeObserver) new ResizeObserver(()=>saveState()).observe(w);
        frame.addEventListener('load',()=>{try{const tt=frame.contentDocument?.title?.trim();if(tt){t.textContent=tt;task.textContent=tt;}}catch{}});
        saveState();
        return w;
    };
    readState().forEach(x => {
        try {
            const u = new URL(x.href, location.href);
            const connection = u.pathname.startsWith('/anbindungen');
            openDbWindow(x.href,x.title,{
                left:x.left, top:x.top,
                width:connection ? 'min(1120px,94vw)' : x.width,
                height:connection ? 'min(860px,90vh)' : x.height,
                min:x.min, max:x.max
            });
        } catch {}
    });
    interceptLinks();
})();