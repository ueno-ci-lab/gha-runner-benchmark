<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>タスク管理（動作確認）</title>
    <style>
        :root {
            --bg: #f6f7f9; --panel: #fff; --text: #1f2328; --muted: #656d76; --border: #d0d7de;
            --primary: #0969da; --danger: #cf222e; --ok: #1a7f37; --warn: #9a6700;
            --todo: #eaeef2; --doing: #ddf4ff; --done: #dafbe1;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Hiragino Sans", "Noto Sans JP", sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
        header { background: var(--text); color: #fff; padding: 12px 24px; display: flex; align-items: baseline; gap: 12px; }
        header h1 { font-size: 18px; margin: 0; }
        header span { color: #c9d1d9; font-size: 12px; }
        main { max-width: 1200px; margin: 0 auto; padding: 16px; display: grid; gap: 16px; grid-template-columns: 1fr 380px; }
        @media (max-width: 900px) { main { grid-template-columns: 1fr; } }
        section { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 16px; }
        h2 { font-size: 15px; margin: 0 0 12px; }
        .cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .card { border: 1px solid var(--border); border-radius: 6px; padding: 8px; text-align: center; }
        .card b { display: block; font-size: 20px; }
        .card small { color: var(--muted); }
        form.create { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 8px; align-items: end; }
        form.create .full { grid-column: 1 / -1; }
        label { display: grid; gap: 4px; font-size: 12px; color: var(--muted); }
        input, select, textarea, button { font: inherit; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px; background: #fff; color: var(--text); }
        button { cursor: pointer; background: var(--primary); color: #fff; border-color: var(--primary); }
        button.secondary { background: #fff; color: var(--text); border-color: var(--border); }
        button.danger { background: #fff; color: var(--danger); border-color: var(--danger); }
        .toolbar { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 6px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
        th { font-size: 12px; color: var(--muted); font-weight: 600; }
        td input, td select { width: 100%; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
        .badge.todo { background: var(--todo); } .badge.doing { background: var(--doing); } .badge.done { background: var(--done); }
        .overdue { color: var(--danger); font-weight: 600; }
        .desc { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: var(--muted); }
        .actions { display: flex; gap: 4px; }
        .empty { color: var(--muted); text-align: center; padding: 24px; }
        aside section { position: sticky; top: 16px; }
        #log { display: grid; gap: 8px; max-height: 75vh; overflow: auto; }
        .entry { border: 1px solid var(--border); border-left-width: 4px; border-radius: 6px; padding: 6px 8px; font-size: 12px; }
        .entry.s2 { border-left-color: var(--ok); } .entry.s4 { border-left-color: var(--warn); } .entry.s5 { border-left-color: var(--danger); }
        .entry pre { margin: 4px 0 0; white-space: pre-wrap; word-break: break-all; max-height: 200px; overflow: auto; background: var(--bg); padding: 6px; border-radius: 4px; }
        .entry .head { display: flex; justify-content: space-between; gap: 8px; font-weight: 600; }
    </style>
</head>
<body>
<header>
    <h1>タスク管理（動作確認）</h1>
    <span>API: /api/tasks ・ 右側に API のリクエストとレスポンスを表示</span>
</header>

<main>
    <div style="display: grid; gap: 16px; align-content: start;">
        <section>
            <h2>集計</h2>
            <div class="cards" id="summary"></div>
        </section>

        <section>
            <h2>タスク登録</h2>
            <form class="create" id="create-form">
                <label>タイトル<input name="title" placeholder="必須・255文字以内"></label>
                <label>優先度<input name="priority" type="number" value="3" placeholder="1〜5"></label>
                <label>期限<input name="due_date" type="date"></label>
                <button type="submit">登録</button>
                <label class="full">説明<textarea name="description" rows="2" placeholder="任意・2000文字以内"></textarea></label>
            </form>
        </section>

        <section>
            <div class="toolbar">
                <h2 style="margin: 0; flex: 1;">タスク一覧</h2>
                <label style="display: flex; align-items: center; gap: 6px;">ステータス
                    <select id="filter">
                        <option value="">すべて</option>
                        <option value="todo">未着手</option>
                        <option value="doing">進行中</option>
                        <option value="done">完了</option>
                    </select>
                </label>
                <button class="secondary" id="reload">再読み込み</button>
            </div>
            <table>
                <thead>
                <tr><th>ID</th><th>タイトル</th><th style="width: 70px;">優先度</th><th style="width: 140px;">期限</th><th style="width: 130px;">ステータス</th><th style="width: 130px;"></th></tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </div>

    <aside>
        <section>
            <div class="toolbar">
                <h2 style="margin: 0; flex: 1;">API ログ</h2>
                <button class="secondary" id="clear-log">クリア</button>
            </div>
            <div id="log"></div>
        </section>
    </aside>
</main>

<script>
    const API = '/api/tasks';
    const STATUSES = { todo: '未着手', doing: '進行中', done: '完了' };

    const el = (tag, attrs = {}, ...children) => {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else if (value !== null && value !== undefined && value !== false) node.setAttribute(key, value === true ? '' : value);
        }
        node.append(...children.flat().filter((c) => c !== null && c !== undefined));
        return node;
    };

    // API を呼び出し、結果をログ欄に表示する
    async function api(method, path = '', body) {
        const response = await fetch(API + path, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const text = await response.text();
        const data = text ? JSON.parse(text) : null;

        const log = document.getElementById('log');
        log.prepend(el('div', { class: `entry s${String(response.status)[0]}` },
            el('div', { class: 'head' },
                el('span', {}, `${method} ${API}${path}`),
                el('span', {}, `${response.status} ${response.statusText}`)),
            body ? el('pre', {}, 'req: ' + JSON.stringify(body, null, 2)) : null,
            data ? el('pre', {}, 'res: ' + JSON.stringify(data, null, 2)) : null,
        ));

        return { ok: response.ok, status: response.status, data };
    }

    async function loadSummary() {
        const { ok, data } = await api('GET', '/summary');
        if (!ok) return;
        const s = data.data;
        const card = (label, value, cls = '') => el('div', { class: 'card' }, el('b', { class: cls }, String(value)), el('small', {}, label));
        document.getElementById('summary').replaceChildren(
            card('合計', s.total),
            card('未着手', s.by_status.todo),
            card('進行中', s.by_status.doing),
            card('完了率', `${s.completion_rate}%`),
            card('期限切れ', s.overdue, s.overdue > 0 ? 'overdue' : ''),
        );
    }

    async function loadTasks() {
        const status = document.getElementById('filter').value;
        const { ok, data } = await api('GET', status ? `?status=${status}` : '');
        const rows = document.getElementById('rows');
        if (!ok) return;
        if (data.data.length === 0) {
            rows.replaceChildren(el('tr', {}, el('td', { colspan: 6, class: 'empty' }, 'タスクがありません')));
            return;
        }
        rows.replaceChildren(...data.data.map(taskRow));
    }

    function taskRow(task) {
        const title = el('input', { value: task.title });
        const priority = el('input', { type: 'number', value: task.priority });
        const due = el('input', { type: 'date', value: task.due_date ?? '' });
        // 不正な遷移も API に送れるよう全ステータスを選択可能にしている（422 になることを確認できる）
        const status = el('select', {},
            Object.entries(STATUSES).map(([value, label]) => el('option', { value, selected: value === task.status }, label)));

        const save = async () => {
            const payload = { title: title.value, priority: Number(priority.value), due_date: due.value || null, status: status.value };
            const { ok } = await api('PATCH', `/${task.id}`, payload);
            if (ok) await refresh();
        };
        const remove = async () => {
            if (!confirm(`「${task.title}」を削除しますか？`)) return;
            const { ok } = await api('DELETE', `/${task.id}`);
            if (ok) await refresh();
        };

        return el('tr', {},
            el('td', {}, String(task.id)),
            el('td', {}, title, task.description ? el('small', { class: 'desc', title: task.description }, task.description) : null),
            el('td', {}, priority),
            el('td', {}, due, task.is_overdue ? el('small', { class: 'overdue' }, '期限切れ') : null),
            el('td', {}, el('span', { class: `badge ${task.status}` }, task.status_label), status),
            el('td', {}, el('div', { class: 'actions' },
                el('button', { onclick: save }, '保存'),
                el('button', { class: 'danger', onclick: remove }, '削除'))),
        );
    }

    async function refresh() {
        await Promise.all([loadSummary(), loadTasks()]);
    }

    document.getElementById('create-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.target);
        const payload = { title: form.get('title'), priority: Number(form.get('priority')) };
        if (form.get('due_date')) payload.due_date = form.get('due_date');
        if (form.get('description')) payload.description = form.get('description');

        const { ok } = await api('POST', '', payload);
        if (ok) {
            event.target.reset();
            await refresh();
        }
    });
    document.getElementById('filter').addEventListener('change', loadTasks);
    document.getElementById('reload').addEventListener('click', refresh);
    document.getElementById('clear-log').addEventListener('click', () => document.getElementById('log').replaceChildren());

    refresh();
</script>
</body>
</html>
