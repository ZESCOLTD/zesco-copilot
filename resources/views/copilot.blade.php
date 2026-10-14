<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZESCO Copilot</title>
    <style>
        :root {
            --bg:#0f172a; --panel:#111c33; --panel-2:#1b2942; --border:#28384f;
            --text:#e6edf6; --muted:#94a3b8; --accent:#38bdf8; --accent-ink:#06121f;
            --user:#2563eb; --danger:#ef4444;
        }
        * { box-sizing:border-box; }
        html, body { height:100%; margin:0; }
        body { display:flex; background:var(--bg); color:var(--text);
            font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; }

        /* sidebar */
        .sidebar { width:270px; flex:0 0 270px; background:var(--panel);
            border-right:1px solid var(--border); display:flex; flex-direction:column; }
        .brand { padding:16px; font-weight:700; letter-spacing:.3px; display:flex;
            align-items:center; gap:8px; border-bottom:1px solid var(--border); }
        .brand .dot { width:10px; height:10px; border-radius:50%; background:var(--accent); }
        .new-chat { margin:12px; padding:10px 12px; background:var(--accent);
            color:var(--accent-ink); border:none; border-radius:10px; font-weight:600;
            cursor:pointer; }
        .convos { flex:1; overflow-y:auto; padding:4px 8px; }
        .convo { display:flex; align-items:center; gap:6px; padding:9px 10px;
            border-radius:9px; cursor:pointer; color:var(--text); font-size:14px; }
        .convo:hover { background:var(--panel-2); }
        .convo.active { background:var(--panel-2); }
        .convo .t { flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .convo .del { opacity:0; border:none; background:none; color:var(--muted);
            cursor:pointer; font-size:14px; }
        .convo:hover .del { opacity:1; }
        .sb-foot { border-top:1px solid var(--border); padding:10px; }
        .sb-foot button { width:100%; padding:9px; background:var(--panel-2);
            border:1px solid var(--border); color:var(--text); border-radius:9px; cursor:pointer; }

        /* main */
        .main { flex:1; display:flex; flex-direction:column; min-width:0; }
        .chat { flex:1; overflow-y:auto; padding:28px 0; }
        .wrap { max-width:780px; margin:0 auto; padding:0 20px; }
        .empty { color:var(--muted); text-align:center; margin-top:16vh; }
        .empty h1 { color:var(--text); font-weight:650; }
        .turn { display:flex; gap:12px; padding:14px 0; }
        .avatar { width:30px; height:30px; border-radius:7px; flex:0 0 30px;
            display:flex; align-items:center; justify-content:center; font-size:14px; }
        .turn.user .avatar { background:var(--user); }
        .turn.bot .avatar { background:var(--accent); color:var(--accent-ink); }
        .body { flex:1; min-width:0; line-height:1.55; }
        .body.markdown strong { font-weight:650; }
        .body code { background:var(--panel-2); padding:1px 5px; border-radius:5px; font-size:13px; }
        .body ul, .body ol { margin:8px 0; padding-left:22px; }
        .body li { margin:3px 0; }
        .tool-step { font-size:12.5px; color:var(--muted); background:var(--panel);
            border:1px solid var(--border); border-radius:8px; padding:5px 9px;
            margin:4px 0; display:inline-block; }
        .sources { margin-top:10px; display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
        .sources .label { color:var(--muted); font-size:12px; }
        .chip { background:var(--panel-2); border:1px solid var(--border); color:var(--text);
            padding:2px 9px; border-radius:999px; font-size:12px; cursor:pointer; }
        .chip:hover { border-color:var(--accent); color:var(--accent); }
        .snippet { margin-top:8px; display:none; }
        .snippet.show { display:block; }
        .snippet p { margin:4px 0; font-size:12.5px; color:var(--muted); background:var(--panel);
            border:1px solid var(--border); border-radius:8px; padding:6px 9px;
            max-height:160px; overflow:auto; white-space:pre-wrap; }

        .composer { border-top:1px solid var(--border); padding:14px 0 20px; }
        .composer form { max-width:780px; margin:0 auto; padding:0 20px; display:flex; gap:10px; }
        .composer textarea { flex:1; resize:none; background:var(--panel); color:var(--text);
            border:1px solid var(--border); border-radius:12px; padding:12px 14px; font-size:15px;
            outline:none; max-height:160px; font-family:inherit; }
        .composer textarea:focus { border-color:var(--accent); }
        .composer button { background:var(--accent); color:var(--accent-ink); border:none;
            border-radius:12px; padding:0 18px; font-weight:600; cursor:pointer; }
        .composer button:disabled { opacity:.5; cursor:default; }

        /* documents modal */
        .modal { position:fixed; inset:0; background:rgba(2,6,23,.6); display:none;
            align-items:center; justify-content:center; z-index:50; }
        .modal.show { display:flex; }
        .dlg { width:560px; max-width:calc(100vw - 32px); max-height:80vh; overflow:auto;
            background:var(--panel); border:1px solid var(--border); border-radius:14px; }
        .dlg-head { display:flex; justify-content:space-between; align-items:center;
            padding:14px 16px; border-bottom:1px solid var(--border); }
        .dlg-head b { font-size:15px; }
        .dlg-body { padding:16px; }
        .drop { border:2px dashed var(--border); border-radius:10px; padding:24px; text-align:center;
            color:var(--muted); cursor:pointer; }
        .drop.drag { border-color:var(--accent); color:var(--accent); }
        .doc { display:flex; align-items:center; justify-content:space-between; gap:10px;
            padding:8px 10px; border:1px solid var(--border); border-radius:9px; margin-top:8px; }
        .doc .name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13.5px; }
        .doc button { background:none; border:1px solid var(--danger); color:var(--danger);
            border-radius:8px; padding:3px 9px; cursor:pointer; font-size:12px; }
        .x { background:none; border:none; color:var(--muted); font-size:20px; cursor:pointer; }
        .muted { color:var(--muted); }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand"><span class="dot"></span> ZESCO Copilot</div>
        <button class="new-chat" id="newChat">+ New chat</button>
        <div class="convos" id="convos"></div>
        <div class="sb-foot"><button id="openDocs">📚 Knowledge base</button></div>
    </aside>

    <main class="main">
        <div class="chat" id="chat">
            <div class="wrap"><div class="empty" id="empty">
                <h1>ZESCO Copilot</h1>
                <p>Ask anything — answers come only from the ZESCO knowledge base.</p>
            </div></div>
        </div>
        <div class="composer">
            <form id="form">
                <textarea id="input" rows="1" placeholder="Message ZESCO Copilot…" autocomplete="off"></textarea>
                <button id="send" type="submit">Send</button>
            </form>
        </div>
    </main>

    <div class="modal" id="docsModal">
        <div class="dlg">
            <div class="dlg-head"><b>Knowledge base documents</b><button class="x" id="closeDocs">×</button></div>
            <div class="dlg-body">
                <div class="drop" id="drop">Drop files here, or click to choose
                    <input id="file" type="file" accept=".pdf,.docx,.txt,.md" multiple hidden></div>
                <ul id="upProgress" class="muted" style="list-style:none;padding:0;margin:8px 0 0;font-size:12.5px;"></ul>
                <div id="docList" style="margin-top:12px;"></div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const chat = document.getElementById('chat');
        const convosEl = document.getElementById('convos');
        const form = document.getElementById('form');
        const input = document.getElementById('input');
        const send = document.getElementById('send');
        let current = null; // conversation id

        const api = (url, opts = {}) => fetch(url, {
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            ...opts,
        });

        // ---------- markdown + citations ----------
        function escapeHtml(s) { return s.replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
        function renderMarkdown(src) {
            let t = escapeHtml(src);
            t = t.replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            const out = []; let list = null;
            const close = () => { if (list) { out.push('</' + list + '>'); list = null; } };
            for (const line of t.split('\n')) {
                const ul = line.match(/^\s*[-*]\s+(.*)$/), ol = line.match(/^\s*\d+\.\s+(.*)$/);
                if (ul) { if (list !== 'ul') { close(); out.push('<ul>'); list = 'ul'; } out.push('<li>' + ul[1] + '</li>'); }
                else if (ol) { if (list !== 'ol') { close(); out.push('<ol>'); list = 'ol'; } out.push('<li>' + ol[1] + '</li>'); }
                else { close(); out.push(line.trim() === '' ? '' : '<div>' + line + '</div>'); }
            }
            close(); return out.join('');
        }
        function citations(container, sources, passages) {
            if (!sources || !sources.length) return;
            const bySrc = {};
            (passages || []).forEach(p => { (bySrc[p.source] = bySrc[p.source] || []).push(p.text || ''); });
            const wrap = document.createElement('div'); wrap.className = 'sources';
            const lbl = document.createElement('span'); lbl.className = 'label'; lbl.textContent = 'Sources:'; wrap.appendChild(lbl);
            const snip = document.createElement('div'); snip.className = 'snippet';
            sources.forEach(s => {
                const chip = document.createElement('span'); chip.className = 'chip'; chip.textContent = s;
                chip.onclick = () => {
                    const t = bySrc[s] || [];
                    snip.innerHTML = t.length ? t.map(x => '<p>' + escapeHtml(x) + '</p>').join('') : '<p>No excerpt.</p>';
                    snip.classList.toggle('show');
                };
                wrap.appendChild(chip);
            });
            container.appendChild(wrap); container.appendChild(snip);
        }

        // ---------- rendering ----------
        function clearChat() { chat.innerHTML = '<div class="wrap" id="turns"></div>'; }
        function turnsEl() { return document.getElementById('turns') || (clearChat(), document.getElementById('turns')); }
        function addTurn(role) {
            const t = document.createElement('div'); t.className = 'turn ' + (role === 'user' ? 'user' : 'bot');
            const a = document.createElement('div'); a.className = 'avatar'; a.textContent = role === 'user' ? '🧑' : '✨';
            const b = document.createElement('div'); b.className = 'body markdown';
            t.appendChild(a); t.appendChild(b); turnsEl().appendChild(t);
            chat.scrollTop = chat.scrollHeight; return b;
        }
        function renderMessages(msgs) {
            clearChat();
            for (const m of msgs) {
                const b = addTurn(m.role);
                if (m.role === 'user') { b.textContent = m.content; }
                else { b.innerHTML = renderMarkdown(m.content || ''); const meta = m.meta || {}; citations(b, meta.sources, meta.passages); }
            }
            chat.scrollTop = chat.scrollHeight;
        }

        // ---------- conversations ----------
        async function loadConversations() {
            const list = await (await api('/api/conversations')).json();
            convosEl.innerHTML = '';
            for (const c of list) {
                const row = document.createElement('div'); row.className = 'convo' + (c.id === current ? ' active' : '');
                row.innerHTML = '<span class="t"></span><button class="del" title="Delete">🗑</button>';
                row.querySelector('.t').textContent = c.title || 'New chat';
                row.querySelector('.t').onclick = () => openConversation(c.id);
                row.querySelector('.del').onclick = (e) => { e.stopPropagation(); deleteConversation(c.id); };
                convosEl.appendChild(row);
            }
        }
        async function newChat() {
            const c = await (await api('/api/conversations', { method: 'POST' })).json();
            current = c.id; clearChat(); await loadConversations(); input.focus();
        }
        async function openConversation(id) {
            current = id;
            const msgs = await (await api(`/api/conversations/${id}/messages`)).json();
            renderMessages(msgs); loadConversations();
        }
        async function deleteConversation(id) {
            if (!confirm('Delete this conversation?')) return;
            await api(`/api/conversations/${id}`, { method: 'DELETE' });
            if (current === id) { current = null; clearChat(); chat.innerHTML = document.getElementById('empty') ? chat.innerHTML : '<div class="wrap"><div class="empty"><h1>ZESCO Copilot</h1></div></div>'; }
            loadConversations();
        }

        // ---------- send + stream ----------
        async function sendMessage(text) {
            if (!current) await newChat();
            addTurn('user').textContent = text;
            const bot = addTurn('bot'); bot.innerHTML = '<span class="muted">…</span>';
            let full = '', sources = [], passages = [], toolShown = false, buffer = '';

            try {
                const res = await fetch(`/api/conversations/${current}/stream`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
                    body: JSON.stringify({ message: text }),
                });
                if (!res.ok || !res.body) throw new Error('HTTP ' + res.status);
                bot.innerHTML = '';
                const reader = res.body.getReader(); const dec = new TextDecoder();
                for (;;) {
                    const { done, value } = await reader.read(); if (done) break;
                    buffer += dec.decode(value, { stream: true });
                    let i;
                    while ((i = buffer.indexOf('\n\n')) >= 0) {
                        const block = buffer.slice(0, i); buffer = buffer.slice(i + 2);
                        let ev = 'message', data = '';
                        for (const ln of block.split('\n')) {
                            if (ln.startsWith('event:')) ev = ln.slice(6).trim();
                            else if (ln.startsWith('data:')) data += ln.slice(5).trim();
                        }
                        let d = null; try { d = data ? JSON.parse(data) : null; } catch (_) {}
                        if (!d) continue;
                        if (ev === 'tool') {
                            const a = d.args || {};
                            const eng = a.engine ? ' (' + a.engine + ')' : '';
                            let detail = '';
                            if (a.query) detail = ': ' + a.query;
                            else if (a.sql) detail = eng + ': ' + a.sql;
                            else if (a.table) detail = eng + ': ' + a.table;
                            else if (a.engine) detail = eng + (a.filter ? ': ' + a.filter : '');
                            const s = document.createElement('div'); s.className = 'tool-step';
                            s.textContent = '🔎 ' + d.name + detail; bot.appendChild(s); toolShown = true;
                            chat.scrollTop = chat.scrollHeight;
                        } else if (ev === 'meta') { sources = d.sources || []; passages = d.passages || []; }
                        else if (ev === 'delta') { full += d.text || ''; renderAnswer(bot, full, toolShown); }
                        else if (ev === 'error') { bot.innerHTML = '<span style="color:var(--danger)">' + escapeHtml(d.error || 'Error') + '</span>'; }
                    }
                }
                renderAnswer(bot, full, toolShown);
                citations(bot, sources, passages);
                loadConversations(); // title may have been set
            } catch (err) {
                bot.innerHTML = '<span style="color:var(--danger)">Network error: ' + escapeHtml(err.message) + '</span>';
            }
        }
        function renderAnswer(bot, full, keepTool) {
            const tools = keepTool ? [...bot.querySelectorAll('.tool-step')].map(e => e.outerHTML).join('') : '';
            bot.innerHTML = tools + renderMarkdown(full);
            chat.scrollTop = chat.scrollHeight;
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const text = input.value.trim(); if (!text) return;
            input.value = ''; input.style.height = 'auto';
            sendMessage(text);
        });
        input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 160) + 'px'; });
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
        document.getElementById('newChat').onclick = newChat;

        // ---------- document library ----------
        const modal = document.getElementById('docsModal');
        const docList = document.getElementById('docList');
        const drop = document.getElementById('drop');
        const file = document.getElementById('file');
        const upProgress = document.getElementById('upProgress');

        async function loadDocs() {
            docList.innerHTML = '<span class="muted">Loading…</span>';
            try {
                const d = await (await api('/api/documents')).json();
                const srcs = d.sources || [];
                docList.innerHTML = srcs.length ? '' : '<span class="muted">No documents yet.</span>';
                for (const s of srcs) {
                    const row = document.createElement('div'); row.className = 'doc';
                    const n = document.createElement('span'); n.className = 'name'; n.textContent = s;
                    const b = document.createElement('button'); b.textContent = 'Delete';
                    b.onclick = async () => { if (!confirm('Delete "' + s + '"?')) return;
                        await api('/api/documents', { method: 'DELETE', body: JSON.stringify({ source: s }) }); loadDocs(); };
                    row.appendChild(n); row.appendChild(b); docList.appendChild(row);
                }
            } catch (e) { docList.innerHTML = '<span style="color:var(--danger)">' + escapeHtml(e.message) + '</span>'; }
        }
        document.getElementById('openDocs').onclick = () => { modal.classList.add('show'); loadDocs(); };
        document.getElementById('closeDocs').onclick = () => modal.classList.remove('show');
        modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };

        async function extract(f) {
            const ext = f.name.split('.').pop().toLowerCase();
            if (ext === 'txt' || ext === 'md') return await f.text();
            if (ext === 'docx') { const r = await mammoth.extractRawText({ arrayBuffer: await f.arrayBuffer() }); return r.value || ''; }
            if (ext === 'pdf') {
                const pdf = await pdfjsLib.getDocument({ data: await f.arrayBuffer() }).promise; let out = '';
                for (let p = 1; p <= pdf.numPages; p++) { const tc = await (await pdf.getPage(p)).getTextContent(); out += tc.items.map(i => i.str).join(' ') + '\n'; }
                return out;
            }
            throw new Error('unsupported .' + ext);
        }
        async function handleFiles(files) {
            for (const f of files) {
                const li = document.createElement('li'); li.textContent = f.name + ': extracting…'; upProgress.appendChild(li);
                try {
                    const text = await extract(f);
                    if (!text.trim()) { li.textContent = f.name + ': no text found'; continue; }
                    li.textContent = f.name + ': uploading…';
                    const r = await api('/api/documents', { method: 'POST', body: JSON.stringify({ source: f.name, text }) });
                    li.textContent = f.name + (r.ok ? ': added ✓' : ': failed');
                } catch (e) { li.textContent = f.name + ': ' + e.message; }
            }
            loadDocs();
        }
        drop.onclick = () => file.click();
        drop.ondragover = (e) => { e.preventDefault(); drop.classList.add('drag'); };
        drop.ondragleave = () => drop.classList.remove('drag');
        drop.ondrop = (e) => { e.preventDefault(); drop.classList.remove('drag'); handleFiles(Array.from(e.dataTransfer.files)); };
        file.onchange = (e) => { const fs = Array.from(e.target.files); e.target.value = ''; handleFiles(fs); };

        // ---------- boot ----------
        loadConversations();
        input.focus();
    })();
    </script>
</body>
</html>
